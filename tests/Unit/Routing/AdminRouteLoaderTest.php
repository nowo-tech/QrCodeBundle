<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Routing;

use Nowo\QrCodeBundle\Routing\AdminRouteLoader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use function array_keys;
use function array_map;
use function dirname;
use function sort;
use function strval;

final class AdminRouteLoaderTest extends TestCase
{
    public function testSupportsOnlyItsType(): void
    {
        $loader = new AdminRouteLoader(true);

        self::assertTrue($loader->supports('.', AdminRouteLoader::TYPE));
        self::assertFalse($loader->supports('.', 'attribute'));
        self::assertFalse($loader->supports('.'));
    }

    public function testDatabaseModeImportsTheAdminRoutes(): void
    {
        $routes = $this->loadBundleRouting(true);
        $names  = array_keys($routes->all());
        sort($names);

        self::assertSame([
            'nowo_qr_code_admin_delete',
            'nowo_qr_code_admin_edit',
            'nowo_qr_code_admin_import_yaml',
            'nowo_qr_code_admin_index',
            'nowo_qr_code_admin_new',
        ], $names);
        self::assertSame('/admin/qr-code-profiles', $routes->get('nowo_qr_code_admin_index')?->getPath());
        self::assertContains(
            (string) new ContainerParametersResource(['nowo_qr_code.use_database_config' => true]),
            array_map(strval(...), $routes->getResources()),
        );
    }

    /**
     * SEC-004 Appendix BK I1: the Flex recipe imports routing.yaml even when the admin controller service does not
     * exist (use_database_config false); the routes must not be registered (404, not a 500).
     */
    public function testYamlOnlyModeImportsNoRoute(): void
    {
        $routes = $this->loadBundleRouting(false);

        self::assertSame([], $routes->all());
        self::assertContains(
            (string) new ContainerParametersResource(['nowo_qr_code.use_database_config' => false]),
            array_map(strval(...), $routes->getResources()),
        );
    }

    public function testNonCollectionImportResultFallsBackToAnEmptyCollection(): void
    {
        $loader = new AdminRouteLoader(true);
        $loader->setResolver(new LoaderResolver([new class extends Loader {
            public function load(mixed $resource, ?string $type = null): mixed
            {
                return null;
            }

            public function supports(mixed $resource, ?string $type = null): bool
            {
                return $type === 'attribute';
            }
        }]));

        self::assertSame([], $loader->load('.', AdminRouteLoader::TYPE)->all());
    }

    private function loadBundleRouting(bool $useDatabaseConfig): RouteCollection
    {
        $configDir = dirname(__DIR__, 3) . '/src/Resources/config';
        $loader    = new YamlFileLoader(new FileLocator($configDir));
        $loader->setResolver(new LoaderResolver([
            $loader,
            new AdminRouteLoader($useDatabaseConfig, 'test'),
            new class extends AttributeClassLoader {
                /**
                 * @param ReflectionClass<object> $class
                 */
                protected function configureRoute(Route $route, ReflectionClass $class, ReflectionMethod $method, object $attr): void
                {
                    $route->setDefault('_controller', $class->getName() . '::' . $method->getName());
                }
            },
        ]));

        return $loader->load('routing.yaml');
    }
}
