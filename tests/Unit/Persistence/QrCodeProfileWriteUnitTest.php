<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Persistence;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\UnitOfWork;
use LogicException;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Persistence\QrCodeProfileWriteUnit;
use Nowo\QrCodeBundle\Tests\Integration\EntityManager\Entity\HostNote;
use Nowo\QrCodeBundle\Tests\Support\SqliteManagerRegistry;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

use function dirname;

#[CoversClass(QrCodeProfileWriteUnit::class)]
final class QrCodeProfileWriteUnitTest extends TestCase
{
    private const BUNDLE = ['Nowo\\QrCodeBundle\\Entity' => __DIR__ . '/../../../src/Entity'];

    public function testTheHostManagerUnitFlushesTheProfileAndCommits(): void
    {
        [$registry, $manager] = $this->hostManager();
        $unit                 = new QrCodeProfileWriteUnit($manager, $registry);

        $id = $unit->run(static function (EntityManagerInterface $em): int {
            $profile = (new QrCodeProfileConfig())->setName('compact');
            $em->persist($profile);

            return 7;
        });

        self::assertSame(7, $id);
        self::assertFalse($manager->getConnection()->isTransactionActive());
        self::assertSame(['compact'], $manager->getConnection()->fetchFirstColumn('SELECT name FROM qr_code_profile'));
    }

    public function testAProfileFormBoundBeforeTheUnitIsTheBundlesOwnPendingWork(): void
    {
        [$registry, $manager] = $this->hostManager();
        $profile              = (new QrCodeProfileConfig())->setName('compact');
        $manager->persist($profile);
        $manager->flush();

        $profile->setSize(512); // bound by the form before the unit starts
        (new QrCodeProfileWriteUnit($manager, $registry))->run(static fn (EntityManagerInterface $em): null => null);

        self::assertSame(512, (int) $manager->getConnection()->fetchOne('SELECT size FROM qr_code_profile'));
        self::assertSame(2, $profile->getVersion(), 'the optimistic version moved on');
    }

    public function testPendingHostWorkRefusesTheUnitOnTheHostManager(): void
    {
        [$registry, $manager] = $this->hostManager();
        $note                 = new HostNote();
        $manager->persist($note);

        try {
            (new QrCodeProfileWriteUnit($manager, $registry))->run(static fn (EntityManagerInterface $em): null => null);
            self::fail('The host work must not be carried.');
        } catch (LogicException $refused) {
            self::assertSame(QrCodeProfileWriteUnit::HOST_PENDING_MESSAGE, $refused->getMessage());
        }

        self::assertTrue($manager->contains($note), 'the host work is left alone');
        self::assertSame(0, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'));
    }

    public function testPendingHostCollectionChangesRefuseTheUnit(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([new stdClass()]);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);

        $this->expectExceptionObject(new LogicException(QrCodeProfileWriteUnit::HOST_PENDING_MESSAGE));
        (new QrCodeProfileWriteUnit($manager))->run(static fn (EntityManagerInterface $em): null => null);
    }

    public function testADomainFailureOnTheHostManagerForgetsOnlyTheProfileRows(): void
    {
        [$registry, $manager] = $this->hostManager();
        $note                 = new HostNote();
        $manager->persist($note);
        $manager->flush();

        $failure = new RuntimeException('refused');
        $caught  = $this->failureOf(static fn (): mixed => (new QrCodeProfileWriteUnit($manager, $registry))->run(static function (EntityManagerInterface $em) use ($failure): never {
            $em->persist((new QrCodeProfileConfig())->setName('compact'));
            $em->flush();

            throw $failure;
        }));

        self::assertSame($failure, $caught);
        self::assertTrue($manager->isOpen(), 'an open host manager is never closed');
        self::assertTrue($manager->contains($note), 'nor cleared');
        self::assertSame([], $manager->getUnitOfWork()->getIdentityMap()[QrCodeProfileConfig::class] ?? [], 'the rolled-back profile is forgotten');
        self::assertSame(0, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM qr_code_profile'));
    }

    public function testAStorageFailureThatClosedTheHostManagerReplacesIt(): void
    {
        [$registry, $manager] = $this->hostManager();
        $manager->persist((new QrCodeProfileConfig())->setName('compact'));
        $manager->flush();
        $manager->clear();

        $caught = $this->failureOf(static fn () => (new QrCodeProfileWriteUnit($manager, $registry))->run(static function (EntityManagerInterface $em): void {
            $em->persist((new QrCodeProfileConfig())->setName('compact'));
        }));

        self::assertInstanceOf(UniqueConstraintViolationException::class, $caught, 'a unique violation is not a conflict: rethrown unchanged');
        self::assertFalse($manager->isOpen());
        $replacement = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $replacement);
        self::assertNotSame($manager, $replacement, 'the closed manager was replaced');
        self::assertTrue($replacement->isOpen());
    }

    public function testAClosedHostManagerWithoutARegistryIsLeftToTheHost(): void
    {
        [, $manager] = $this->hostManager();
        $manager->persist((new QrCodeProfileConfig())->setName('compact'));
        $manager->flush();
        $manager->clear();

        $caught = $this->failureOf(static fn () => (new QrCodeProfileWriteUnit($manager))->run(static function (EntityManagerInterface $em): void {
            $em->persist((new QrCodeProfileConfig())->setName('compact'));
        }));

        self::assertInstanceOf(UniqueConstraintViolationException::class, $caught);
        self::assertFalse($manager->isOpen());
    }

    public function testALockConflictLeavesAsATransientStorageFailureWithoutHint(): void
    {
        [$registry, $manager] = $this->hostManager();
        $deadlock             = new DeadlockException(new class('Deadlock found', '40001', 1213) extends AbstractException {
        }, null);

        $caught = $this->failureOf(static fn (): mixed => (new QrCodeProfileWriteUnit($manager, $registry))->run(static fn (EntityManagerInterface $em): never => throw $deadlock));

        self::assertInstanceOf(TransientStorageFailure::class, $caught);
        self::assertSame($deadlock, $caught->getPrevious());
        self::assertNull($caught->retryAfterSeconds(), 'the configured conflict_response decides');
    }

    public function testTheBundlesOwnManagerIsResetAfterAStorageFailure(): void
    {
        $registry = new SqliteManagerRegistry(['default' => ['Nowo\\QrCodeBundle\\Tests\\Integration\\EntityManager\\Entity' => dirname(__DIR__, 2) . '/Integration/EntityManager/Entity'], 'qr' => self::BUNDLE]);
        $manager  = $registry->getManager('qr');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $unit = new QrCodeProfileWriteUnit($manager, $registry, 'qr', true);

        $unit->run(static fn (EntityManagerInterface $em) => $em->persist((new QrCodeProfileConfig())->setName('compact')));
        self::assertSame(1, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM qr_code_profile'));

        $caught = $this->failureOf(static fn () => $unit->run(static fn (EntityManagerInterface $em) => $em->persist((new QrCodeProfileConfig())->setName('compact'))));

        self::assertInstanceOf(UniqueConstraintViolationException::class, $caught);
        self::assertNotSame($manager, $registry->getManager('qr'), 'the bundle\'s own manager was reset');
        self::assertSame(1, (int) $manager->getConnection()->fetchOne('SELECT COUNT(*) FROM qr_code_profile'));
    }

    /**
     * @return array{0: SqliteManagerRegistry, 1: EntityManagerInterface}
     */
    private function hostManager(): array
    {
        $registry = new SqliteManagerRegistry(['default' => self::BUNDLE + ['Nowo\\QrCodeBundle\\Tests\\Integration\\EntityManager\\Entity' => dirname(__DIR__, 2) . '/Integration/EntityManager/Entity']]);
        $manager  = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());

        return [$registry, $manager];
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

        self::fail('The unit must fail.');
    }
}
