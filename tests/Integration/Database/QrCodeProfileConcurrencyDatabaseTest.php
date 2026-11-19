<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\Database;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\EventListener\QrCodeProfileConflictListener;
use Nowo\QrCodeBundle\Persistence\QrCodeProfileWriteUnit;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function dirname;
use function getenv;
use function is_string;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * 1.15 (REQ-RUNTIME-003): two connections on real MySQL 8.4, MariaDB 11.4 and PostgreSQL 16 servers, on the bundle's
 * write paths: admin create and edit of a profile, and the YAML import.
 *
 * Every test session sets a 2 s `innodb_lock_wait_timeout` / `lock_timeout`, so a lock wait fails fast instead of
 * hanging. Set QR_MYSQL_URL, QR_MARIADB_URL and QR_POSTGRES_URL; CI runs the `database` group with `--fail-on-skipped`.
 */
#[Group('database')]
final class QrCodeProfileConcurrencyDatabaseTest extends TestCase
{
    private const ADMIN_CONTROLLER = 'Nowo\\QrCodeBundle\\Controller\\QrCodeProfileAdminController::edit';

    private const IMPORT_CONTROLLER = 'Nowo\\QrCodeBundle\\Controller\\QrCodeProfileAdminController::importYaml';

    private const YAML = [
        'compact' => ['size' => 200, 'margin' => 4, 'error_correction' => 'low', 'url_allowlist' => []],
        'poster'  => ['size' => 900, 'margin' => 20, 'error_correction' => 'high', 'url_allowlist' => ['example.com']],
    ];

    /** @var list<EntityManagerInterface> */
    private array $managers = [];

    /** @var array<string, mixed> */
    private array $params = [];

    protected function tearDown(): void
    {
        foreach ($this->managers as $manager) {
            $manager->getConnection()->close();
        }

        $this->managers = [];
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function databases(): iterable
    {
        foreach (['mysql' => 'QR_MYSQL_URL', 'mariadb' => 'QR_MARIADB_URL', 'postgres' => 'QR_POSTGRES_URL'] as $name => $variable) {
            $url = getenv($variable);
            yield $name => [is_string($url) && $url !== '' ? $url : null];
        }
    }

    /**
     * Two admins create a profile with the same name. While A's insert is not committed, B's insert waits on the unique
     * key and meets the 2 s lock timeout: 409 + Retry-After + CONFLICT_001, nothing of B stored, B's manager replaced.
     * Once A committed, B's retry is refused by the unique key: the domain 409 (`X-Error-Code`), never a CONFLICT_*
     * code. Exactly one row.
     */
    #[DataProvider('databases')]
    public function testTwoAdminsCreatingOneNameKeepOneRowAndAnswerConflict001ThenTheDomain409(?string $url): void
    {
        $a      = $this->freshDatabase($url);
        $holder = new ManagerHolder($this);

        $a->getConnection()->beginTransaction();
        $a->persist($this->profile('compact', 200));
        $a->flush();

        $failure = $this->failureOf(fn () => $this->unit($holder)->run(fn (EntityManagerInterface $em) => $em->persist($this->profile('compact', 512))));
        self::assertInstanceOf(TransientStorageFailure::class, $failure);
        $answer = $this->answer($failure, new QrCodeProfileConflictListener(), self::ADMIN_CONTROLLER);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());
        self::assertSame(503, $this->answer($failure, new QrCodeProfileConflictListener(new ConflictResponsePolicy(503)), self::ADMIN_CONTROLLER)->getStatusCode(), 'host option');
        self::assertSame(1, $holder->resets, 'the closed manager was replaced');

        $a->getConnection()->commit();

        $duplicate = $this->failureOf(fn () => $this->unit($holder)->run(fn (EntityManagerInterface $em) => $em->persist($this->profile('compact', 512))));
        self::assertInstanceOf(UniqueConstraintViolationException::class, $duplicate, 'not a lock conflict: never wrapped');
        $domain = $this->answer($duplicate, new QrCodeProfileConflictListener(), self::IMPORT_CONTROLLER);
        self::assertInstanceOf(ConflictHttpException::class, $domain);
        self::assertSame([QrCodeProfileConflictListener::ERROR_CODE_HEADER => QrCodeProfileConflictListener::DUPLICATE_NAME], $domain->getHeaders());

        self::assertSame([['compact', 200]], $this->rows($a), 'exactly one row, A\'s');
        self::assertSame([['compact', 200]], $this->rows($holder->manager), 'B\'s connection usable');
    }

    /**
     * Two admins edit one profile; B read it before A saved. B's save is refused by the optimistic version (the UPDATE
     * finds no row of the version B read): no lost update, A's row stays whole, B's manager is replaced. B's form
     * carried version 1; checked before the write (lock()) it is refused the same way without touching the row.
     */
    #[DataProvider('databases')]
    public function testAnEditOfAStaleProfileIsRefusedAndNeverOverwritesTheNewerOne(?string $url): void
    {
        $a      = $this->freshDatabase($url);
        $id     = $this->seed($a, 'compact', 200);
        $holder = new ManagerHolder($this);

        $stale = $holder->manager->find(QrCodeProfileConfig::class, $id); // B opens the edit form
        self::assertInstanceOf(QrCodeProfileConfig::class, $stale);
        self::assertSame(1, $stale->getVersion());

        $this->unit(new ManagerHolder($this, $a))->run(static function (EntityManagerInterface $em) use ($id): void {
            $row = $em->find(QrCodeProfileConfig::class, $id);
            self::assertInstanceOf(QrCodeProfileConfig::class, $row);
            $row->setMargin(30); // A saves first
        });

        $stale->setSize(999);
        $failure = $this->failureOf(fn () => $this->unit($holder)->run(static fn (EntityManagerInterface $em): null => null));
        self::assertInstanceOf(OptimisticLockException::class, $failure);
        self::assertSame(QrCodeProfileConflictListener::STALE_PROFILE, QrCodeProfileConflictListener::domainCode($failure));
        self::assertSame([['compact', 200]], $this->rows($a), 'B\'s size never reached the row');
        self::assertSame(30, (int) $a->getConnection()->fetchOne('SELECT margin FROM qr_code_profile'), 'A\'s change kept');
        self::assertSame(2, (int) $a->getConnection()->fetchOne('SELECT version FROM qr_code_profile'));

        $reread = $holder->manager->find(QrCodeProfileConfig::class, $id); // B reloads the page, form at version 2
        self::assertInstanceOf(QrCodeProfileConfig::class, $reread);
        $refused = $this->failureOf(fn () => $this->unit($holder)->run(static fn (EntityManagerInterface $em) => $em->lock($reread, LockMode::OPTIMISTIC, 1)));
        self::assertInstanceOf(OptimisticLockException::class, $refused, 'a form opened on version 1 is refused before writing');

        $current = $holder->manager->find(QrCodeProfileConfig::class, $id); // the refusal forgot the row: read it again
        self::assertInstanceOf(QrCodeProfileConfig::class, $current);
        $this->unit($holder)->run(static fn (EntityManagerInterface $em) => $em->lock($current->setSize(999), LockMode::OPTIMISTIC, 2));
        self::assertSame([['compact', 999]], $this->rows($a), 'the retry on the current version saves');
        self::assertSame(30, (int) $a->getConnection()->fetchOne('SELECT margin FROM qr_code_profile'));
    }

    /**
     * An edit that meets a row lock (another write of the profile in flight) answers 409 + CONFLICT_001 with nothing
     * written, and the retry once the lock is gone writes exactly once.
     */
    #[DataProvider('databases')]
    public function testAnEditThatMeetsALockedRowAnswersConflict001AndTheRetrySaves(?string $url): void
    {
        $a      = $this->freshDatabase($url);
        $id     = $this->seed($a, 'compact', 200);
        $holder = new ManagerHolder($this);

        $a->getConnection()->beginTransaction();
        $a->getConnection()->fetchOne('SELECT id FROM qr_code_profile WHERE id = ? FOR UPDATE', [$id]);

        $edit = static function (EntityManagerInterface $em) use ($id): void {
            $row = $em->find(QrCodeProfileConfig::class, $id);
            self::assertInstanceOf(QrCodeProfileConfig::class, $row);
            $row->setSize(512);
        };
        $failure = $this->failureOf(fn () => $this->unit($holder)->run($edit));
        $answer  = $this->answer($failure, new QrCodeProfileConflictListener(), self::ADMIN_CONTROLLER);
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());

        $a->getConnection()->rollBack();
        self::assertSame([['compact', 200]], $this->rows($a), 'nothing written');

        $this->unit($holder)->run($edit);
        self::assertSame([['compact', 512]], $this->rows($a));
        self::assertSame(2, (int) $a->getConnection()->fetchOne('SELECT version FROM qr_code_profile'), 'written exactly once');
    }

    /**
     * The YAML import is all or nothing: when one of its rows is locked by an admin save in flight it answers
     * 409 + CONFLICT_001 and creates none of the other profiles. Run again, it upserts all of them once.
     */
    #[DataProvider('databases')]
    public function testAYamlImportThatMeetsALockedRowWritesNothingAndTheRetryWritesAll(?string $url): void
    {
        $a      = $this->freshDatabase($url);
        $id     = $this->seed($a, 'compact', 300);
        $holder = new ManagerHolder($this);

        $a->getConnection()->beginTransaction();
        $a->getConnection()->fetchOne('SELECT id FROM qr_code_profile WHERE id = ? FOR UPDATE', [$id]);

        $failure = $this->failureOf(fn (): int => $this->importer($holder)->importFromYaml());
        $answer  = $this->answer($failure, new QrCodeProfileConflictListener(), self::IMPORT_CONTROLLER);
        self::assertSame(['X-Conflict-Code' => ConflictCode::RETRY_SAFE, 'Retry-After' => '1'], $answer->getHeaders());

        $a->getConnection()->rollBack();
        self::assertSame([['compact', 300]], $this->rows($a), '"poster" was not created');

        self::assertSame(2, $this->importer($holder)->importFromYaml());
        self::assertSame([['compact', 200], ['poster', 900]], $this->rows($a));
    }

    /**
     * A lock conflict after a commit earlier in the same request answers CONFLICT_002 without Retry-After: the commit
     * is counted by the real commit-tracking middleware on the bundle's connection.
     */
    #[DataProvider('databases')]
    public function testALockConflictAfterACommitEarlierInTheRequestAnswersConflict002(?string $url): void
    {
        $a        = $this->freshDatabase($url);
        $id       = $this->seed($a, 'compact', 200);
        $ledger   = new CommitLedger();
        $holder   = new ManagerHolder($this, null, $ledger);
        $listener = new QrCodeProfileConflictListener(null, $ledger);
        $request  = $this->request(self::IMPORT_CONTROLLER);
        $listener->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->unit($holder)->run(fn (EntityManagerInterface $em) => $em->persist($this->profile('first', 200)));

        $a->getConnection()->beginTransaction();
        $a->getConnection()->fetchOne('SELECT id FROM qr_code_profile WHERE id = ? FOR UPDATE', [$id]);
        $failure = $this->failureOf(fn () => $this->unit($holder)->run(static function (EntityManagerInterface $em) use ($id): void {
            $row = $em->find(QrCodeProfileConfig::class, $id);
            self::assertInstanceOf(QrCodeProfileConfig::class, $row);
            $row->setSize(512);
        }));
        $a->getConnection()->rollBack();

        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $failure);
        $listener->onException($event);
        $answer = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => ConflictCode::COMMITTED], $answer->getHeaders());
        self::assertSame([['compact', 200], ['first', 200]], $this->rows($a), 'the first write is committed, the second is not');
    }

    private function freshDatabase(?string $url): EntityManagerInterface
    {
        if ($url === null) {
            self::markTestSkipped('Set QR_MYSQL_URL, QR_MARIADB_URL and QR_POSTGRES_URL to run the real-database tests.');
        }

        $this->params = (new DsnParser(['mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        $manager      = $this->manager();
        $schemaTool   = new SchemaTool($manager);
        $metadata     = $manager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $manager;
    }

    public function manager(?CommitLedger $ledger = null): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/src/Entity'], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/nowo-qr-database-tests/proxies');
            $config->setProxyNamespace('NowoQrDatabaseTestProxies');
        }

        $dbal = new DbalConfiguration();
        if ($ledger instanceof CommitLedger) {
            $dbal->setMiddlewares([new CommitTrackingMiddleware($ledger)]);
        }

        /** @phpstan-ignore argument.type (DsnParser output) */
        $connection = DriverManager::getConnection($this->params, $dbal);
        $connection->executeStatement($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? 'SET SESSION innodb_lock_wait_timeout = 2' : "SET lock_timeout = '2s'");

        $manager          = new EntityManager($connection, $config);
        $this->managers[] = $manager;

        return $manager;
    }

    public function registryStub(): ManagerRegistry&Stub
    {
        return $this->createStub(ManagerRegistry::class);
    }

    private function unit(ManagerHolder $holder): QrCodeProfileWriteUnit
    {
        return new QrCodeProfileWriteUnit($holder->manager, $holder->registry());
    }

    private function importer(ManagerHolder $holder): QrCodeProfileAdminService
    {
        return new QrCodeProfileAdminService(new QrCodeProfileConfigRepository($holder->registry()), $holder->manager, self::YAML, 'compact', $this->unit($holder));
    }

    private function profile(string $name, int $size): QrCodeProfileConfig
    {
        return (new QrCodeProfileConfig())->setName($name)->setSize($size);
    }

    private function seed(EntityManagerInterface $manager, string $name, int $size): int
    {
        $profile = $this->profile($name, $size);
        $manager->persist($profile);
        $manager->flush();
        $manager->clear();

        return (int) $profile->getId();
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function rows(EntityManagerInterface $manager): array
    {
        $rows = [];
        foreach ($manager->getConnection()->fetchAllAssociative('SELECT name, size FROM qr_code_profile ORDER BY name') as $row) {
            $rows[] = [(string) $row['name'], (int) $row['size']];
        }

        return $rows;
    }

    /**
     * @param callable(): mixed $write
     */
    private function failureOf(callable $write): Throwable
    {
        try {
            $write();
        } catch (Throwable $failure) {
            return $failure;
        }

        self::fail('The write must fail.');
    }

    private function request(string $controller): Request
    {
        $request = Request::create('/admin/qr-code-profiles/1/edit', 'POST');
        $request->attributes->set('_controller', $controller);

        return $request;
    }

    private function answer(Throwable $failure, QrCodeProfileConflictListener $listener, string $controller): HttpException
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $this->request($controller), HttpKernelInterface::MAIN_REQUEST, $failure);
        $listener->onException($event);
        $answer = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $answer, 'mapped: ' . $failure::class . ': ' . $failure->getMessage());

        return $answer;
    }
}

/**
 * A manager behind a registry: resetManager() replaces it by a new manager on a new connection, as DoctrineBundle does
 * for a closed manager.
 */
final class ManagerHolder
{
    public EntityManagerInterface $manager;

    public int $resets = 0;

    public function __construct(
        private readonly QrCodeProfileConcurrencyDatabaseTest $test,
        ?EntityManagerInterface $manager = null,
        private readonly ?CommitLedger $ledger = null,
    ) {
        $this->manager = $manager ?? $test->manager($ledger);
    }

    public function registry(): ManagerRegistry
    {
        $registry = $this->test->registryStub();
        $registry->method('getManagerForClass')->willReturnCallback(fn (): EntityManagerInterface => $this->manager);
        $registry->method('getManager')->willReturnCallback(fn (): EntityManagerInterface => $this->manager);
        $registry->method('resetManager')->willReturnCallback(function (): EntityManagerInterface {
            ++$this->resets;
            $this->manager->getConnection()->close();
            $this->manager = $this->test->manager($this->ledger);

            return $this->manager;
        });

        return $registry;
    }
}
