<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\EntityManager;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\QrCodeBundle\NowoQrCodeBundle;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function getmypid;
use function sys_get_temp_dir;

/**
 * DoctrineBundle 3.4 regression layout: `nowo_qr_code.doctrine.entity_manager: bundle` and a host "default" manager
 * WITHOUT `mappings` and `auto_mapping` (DoctrineBundle 3.4 `auto_discover_entities` gives its chain a default attribute
 * driver). With `$explicitDefaultDriver` the host also adds a catch-all default driver to that chain itself, which
 * reproduces the regression on every DoctrineBundle version.
 */
final class HostDefaultDriverTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly bool $explicitDefaultDriver)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new TwigComponentBundle();
        yield new DoctrineBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoQrCodeBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-qr-code-host-default-driver/' . getmypid() . '/' . ($this->explicitDefaultDriver ? 'explicit' : 'auto') . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-qr-code-host-default-driver/' . getmypid() . '/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        if (!$this->explicitDefaultDriver) {
            return;
        }

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // An attribute driver answers "not transient" for any class carrying #[ORM\Entity], whatever its paths
                // (it only lists the host entities).
                $container->getDefinition('doctrine.orm.default_metadata_driver')
                    ->addMethodCall('setDefaultDriver', [new Definition(AttributeDriver::class, [[__DIR__ . '/Entity'], true])]);
            }
        });
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'router'                => ['utf8' => true],
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'form'                  => ['enabled' => true],
            'csrf_protection'       => ['enabled' => true],
            'php_errors'            => ['log' => true],
        ]);
        $container->extension('twig_component', [
            'anonymous_template_directory' => 'components/',
            'defaults'                     => ['Nowo\\QrCodeBundle\\Twig\\Component\\' => 'components/'],
        ]);
        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['url' => 'sqlite:///:memory:'],
                    'bundle'  => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default' => ['connection' => 'default'],
                    'bundle'  => ['connection' => 'bundle'],
                ],
            ],
        ]);
        $container->extension('nowo_qr_code', [
            'use_database_config' => true,
            'security'            => ['allow_unauthenticated' => true],
            'doctrine'            => ['entity_manager' => 'bundle'],
            'profiles'            => ['default' => ['size' => 300]],
        ]);
        $container->services()->alias('test.host_default_driver.admin_service', QrCodeProfileAdminService::class)->public();
        $container->services()->alias('test.host_default_driver.repository', QrCodeProfileConfigRepository::class)->public();
        $container->services()->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoQrCodeBundle/Resources/config/routing.yaml');
    }
}
