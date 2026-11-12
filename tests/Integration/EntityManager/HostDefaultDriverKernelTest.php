<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\EntityManager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use Nowo\QrCodeBundle\Tests\Integration\EntityManager\Entity\HostNote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function dirname;

/**
 * DoctrineBundle 3.4 regression: `auto_discover_entities` (on by default for a manager without `mappings` and
 * `auto_mapping`) gives the host manager's chain a default attribute driver, which claims every `#[ORM\Entity]` class,
 * so `getManagerForClass()` answered the host manager and the repository queried its database ("no such table"). The
 * explicit catch-all default driver reproduces it on every DoctrineBundle version.
 */
final class HostDefaultDriverKernelTest extends TestCase
{
    private ?HostDefaultDriverTestKernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel instanceof HostDefaultDriverTestKernel) {
            $cacheDir = $this->kernel->getCacheDir();
            $this->kernel->shutdown();
            (new Filesystem())->remove(dirname($cacheDir));
            $this->kernel = null;
        }
        parent::tearDown();
    }

    #[DataProvider('hostManagersWithADefaultDriver')]
    public function testConfiguredManagerWinsOverADefaultDriverOfTheHostManager(bool $explicitDefaultDriver): void
    {
        $this->kernel = new HostDefaultDriverTestKernel($explicitDefaultDriver);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        $doctrine  = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $bundle = $doctrine->getManager('bundle');

        // Neither manager is initialized yet: DoctrineBundle 3.4 asks the metadata drivers.
        self::assertSame($bundle, $doctrine->getManagerForClass(QrCodeProfileConfig::class));

        $host = $doctrine->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $host);
        self::assertInstanceOf(EntityManagerInterface::class, $bundle);
        self::assertTrue($host->getMetadataFactory()->isTransient(QrCodeProfileConfig::class), 'not mapped on the host manager');
        if ($explicitDefaultDriver) {
            self::assertSame($host, $doctrine->getManagerForClass(HostNote::class), 'host entities still go to the default driver');
        }
        self::assertSame($bundle, $doctrine->getManagerForClass(QrCodeProfileConfig::class));

        (new SchemaTool($bundle))->createSchema([$bundle->getClassMetadata(QrCodeProfileConfig::class)]);
        $admin = $container->get('test.host_default_driver.admin_service');
        self::assertInstanceOf(QrCodeProfileAdminService::class, $admin);
        self::assertSame(1, $admin->importFromYaml());
        self::assertSame(1, (int) $bundle->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $bundle->getClassMetadata(QrCodeProfileConfig::class)->getTableName()), 'written to the bundle database');
        $repository = $container->get('test.host_default_driver.repository');
        self::assertInstanceOf(QrCodeProfileConfigRepository::class, $repository);
        self::assertInstanceOf(QrCodeProfileConfig::class, $repository->findOneByName('default'), 'the repository reads the bundle database');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function hostManagersWithADefaultDriver(): iterable
    {
        yield 'host manager without mappings (DoctrineBundle 3.4 auto-discovery)' => [false];
        yield 'host manager with a catch-all default driver' => [true];
    }
}
