<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\QrCodeBundle\DependencyInjection\Compiler\EntityManagerOptionPass;
use Nowo\QrCodeBundle\Doctrine\ExcludedEntityNamespaceDriver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;

/**
 * Audit LD: the named entity manager must exist and map the bundle entities.
 */
final class EntityManagerOptionPassTest extends TestCase
{
    private const ENTITIES = 'Nowo\\QrCodeBundle\\Entity';

    public function testNoOptionIsANoop(): void
    {
        $container = $this->container(null, ['default' => [self::ENTITIES]]);

        (new EntityManagerOptionPass())->process($container);

        self::assertCount(1, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    public function testUnknownManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('no such Doctrine entity manager');

        (new EntityManagerOptionPass())->process($this->container('missing', ['default' => [self::ENTITIES]]));
    }

    public function testManagerThatDoesNotMapTheEntitiesFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not map');

        (new EntityManagerOptionPass())->process($this->container('bundle', ['default' => [self::ENTITIES], 'bundle' => ['App\\Entity']]));
    }

    public function testParentNamespaceMappingCounts(): void
    {
        $container = $this->container('bundle', ['default' => ['App\\Entity'], 'bundle' => ['Nowo']]);

        (new EntityManagerOptionPass())->process($container);

        self::assertCount(1, $container->getDefinition('doctrine.orm.bundle_metadata_driver')->getMethodCalls());
    }

    public function testNamedManagerKeepsTheEntities(): void
    {
        $container = $this->container('bundle', ['default' => ['App\\Entity', self::ENTITIES], 'bundle' => [self::ENTITIES], 'other' => []]);
        $container->removeDefinition('doctrine.orm.other_metadata_driver');

        (new EntityManagerOptionPass())->process($container);

        self::assertCount(1, $container->getDefinition('doctrine.orm.bundle_metadata_driver')->getMethodCalls());
        self::assertCount(2, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls(), 'App\\Entity + the excluded bundle namespace');
    }

    /**
     * DoctrineBundle 3.4 `auto_discover_entities`: the other manager's chain has a default driver that claims every
     * `#[ORM\Entity]` class. A driver that maps nothing comes first for the bundle namespace, before the host's drivers
     * and before that default driver; the configured manager is unchanged; a second run changes nothing.
     */
    public function testOtherManagersExcludeTheBundleNamespaceBeforeTheirDefaultDriver(): void
    {
        $container = $this->container('bundle', ['default' => ['App\\Entity', self::ENTITIES], 'bundle' => [self::ENTITIES]]);
        $container->getDefinition('doctrine.orm.default_metadata_driver')->addMethodCall('setDefaultDriver', [new Reference('auto_discover')]);

        (new EntityManagerOptionPass())->process($container);

        $default = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame(
            [['addDriver', self::ENTITIES], ['addDriver', 'App\\Entity'], ['setDefaultDriver', null]],
            array_map(static fn (array $call): array => [$call[0], $call[1][1] ?? null], $default),
        );
        self::assertInstanceOf(Definition::class, $default[0][1][0]);
        self::assertSame(ExcludedEntityNamespaceDriver::class, $default[0][1][0]->getClass());
        $bundle = $container->getDefinition('doctrine.orm.bundle_metadata_driver')->getMethodCalls();
        self::assertCount(1, $bundle);
        self::assertInstanceOf(Reference::class, $bundle[0][1][0]);

        (new EntityManagerOptionPass())->process($container);
        self::assertCount(3, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls(), 'idempotent');
        self::assertCount(1, $container->getDefinition('doctrine.orm.bundle_metadata_driver')->getMethodCalls(), 'idempotent');
    }

    public function testOtherDriverCallsAreKept(): void
    {
        $container = $this->container('bundle', ['default' => [], 'bundle' => []]);
        $container->getDefinition('doctrine.orm.bundle_metadata_driver')
            ->addMethodCall('setDefaultDriver', [new Reference('driver')])
            ->addMethodCall('addDriver', [new Reference('driver'), self::ENTITIES]);

        (new EntityManagerOptionPass())->process($container);

        self::assertCount(2, $container->getDefinition('doctrine.orm.bundle_metadata_driver')->getMethodCalls());
    }

    /**
     * @param array<string, list<string>> $managers manager => mapped namespaces
     */
    private function container(?string $option, array $managers): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(EntityManagerOptionPass::PARAMETER, $option);
        $names = [];
        foreach ($managers as $manager => $namespaces) {
            $names[$manager] = 'doctrine.orm.' . $manager . '_entity_manager';
            $chain           = $container->register('doctrine.orm.' . $manager . '_metadata_driver');
            foreach ($namespaces as $namespace) {
                $chain->addMethodCall('addDriver', [new Reference('driver'), $namespace]);
            }
        }
        $container->setParameter('doctrine.entity_managers', $names);

        return $container;
    }
}
