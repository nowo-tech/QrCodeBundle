<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\Routing;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\QrCodeBundle\NowoQrCodeBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;

/**
 * Host kernel for REQ-SF-004 rule 7. Its routing is the Symfony default `controllers: { resource: routing.controllers }`;
 * with $importBundleRoutes it also imports the documented `@NowoQrCodeBundle/Resources/config/routing.yaml`.
 */
final class OptInRoutesKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly bool $importBundleRoutes)
    {
        parent::__construct('test', true);
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
        return self::cacheRoot() . '/' . ($this->importBundleRoutes ? 'imported' : 'default');
    }

    public function getLogDir(): string
    {
        return self::cacheRoot() . '/log';
    }

    /**
     * Own cache root outside the repository, removed by the test before and after each run.
     */
    public static function cacheRoot(): string
    {
        return sys_get_temp_dir() . '/qr-code-opt-in-routes';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'opt-in-routes-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'router'                => ['utf8' => true],
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'form'                  => ['enabled' => true],
            'csrf_protection'       => ['enabled' => true],
            'php_errors'            => ['log' => true],
        ]);
        // Newer symfony/ux-twig-component releases (resolved by the CI update lanes) require both keys.
        $container->extension('twig_component', [
            'anonymous_template_directory' => 'components/',
            'defaults'                     => ['Nowo\\QrCodeBundle\\Twig\\Component\\' => 'components/'],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => [],
        ]);
        $container->extension('nowo_qr_code', [
            'use_database_config' => true,
            'security'            => ['allow_unauthenticated' => true],
        ]);
        $container->services()->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('routing.controllers');

        if ($this->importBundleRoutes) {
            $routes->import('@NowoQrCodeBundle/Resources/config/routing.yaml');
        }
    }
}
