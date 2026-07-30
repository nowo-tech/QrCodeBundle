<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Dependency injection extension for the QR Code bundle.
 */
final class NowoQrCodeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $defaultProfile = $config['default_profile'];
        $default        = $config['profiles'][$defaultProfile];

        $container->setParameter('nowo_qr_code.config', $config);
        $container->setParameter('nowo_qr_code.default_profile', $defaultProfile);
        $container->setParameter('nowo_qr_code.profiles', $config['profiles']);
        $container->setParameter('nowo_qr_code.size', $default['size']);
        $container->setParameter('nowo_qr_code.margin', $default['margin']);
        $container->setParameter('nowo_qr_code.error_correction', $default['error_correction']);
        $container->setParameter('nowo_qr_code.url_allowlist', $default['url_allowlist']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'nowo_qr_code';
    }
}
