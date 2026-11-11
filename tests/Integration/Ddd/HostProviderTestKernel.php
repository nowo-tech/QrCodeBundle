<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Integration\Ddd;

use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\QrCodeBundle\NowoQrCodeBundle;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Nowo\QrCodeBundle\Tests\Support\Host\HostQrController;
use Nowo\QrCodeBundle\Tests\Support\Host\TenantQrProfiles;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function getmypid;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * A DDD host without Doctrine (DoctrineBundle not registered, doctrine/orm installed): QR profiles come from the
 * host's {@see TenantQrProfiles} through `profile_provider`.
 */
final class HostProviderTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test_ddd', false);
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir() . '/nowo-qr-code-ddd/' . getmypid();
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new TwigComponentBundle();
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
        return self::baseDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir() . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'ddd-test', 'test' => true, 'http_method_override' => false, 'router' => ['utf8' => true]]);
        $container->extension('nowo_qr_code', [
            'profile_provider' => TenantQrProfiles::class,
            'profiles'         => ['default' => ['size' => 300, 'margin' => 10, 'url_allowlist' => ['yaml.example']]],
        ]);
        $services = $container->services();
        $services->set(TenantQrProfiles::class)->args([service('request_stack')])->public();
        $services->set(HostQrController::class)->args([service(QrCodeService::class)])->public()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('host_qr', '/qr/{tenant}')->controller(HostQrController::class);
    }
}
