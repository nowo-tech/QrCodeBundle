<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection;

use LogicException;
use Nowo\QrCodeBundle\Enum\CssFramework;
use Nowo\QrCodeBundle\Security\AllowAllQrCodeAccessChecker;
use Nowo\QrCodeBundle\Security\ConfigurableQrCodeAccessChecker;
use Nowo\QrCodeBundle\Security\QrCodeAccessCheckerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

use function class_exists;
use function is_string;

/**
 * Dependency injection extension for the QR Code bundle.
 */
final class NowoQrCodeExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('twig')) {
            return;
        }

        $configs = $container->getExtensionConfig($this->getAlias());
        $config  = $this->processConfiguration(new Configuration(), $configs);

        $layoutTemplate = $config['web_ui']['layout_template'];
        // Escape leading "@" so DI does not treat Twig logical names as service refs.
        if (is_string($layoutTemplate) && str_starts_with($layoutTemplate, '@')) {
            $layoutTemplate = '@' . $layoutTemplate;
        }

        $cssFramework = CssFramework::from($config['web_ui']['css_framework'])->normalized()->value;

        $container->prependExtensionConfig('twig', [
            'globals' => [
                'nowo_qr_code_layout_template' => $layoutTemplate,
                'nowo_qr_code_css_framework'   => $cssFramework,
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $defaultProfile = $config['default_profile'];
        $default        = $config['profiles'][$defaultProfile];
        $cssFramework   = CssFramework::from($config['web_ui']['css_framework'])->normalized()->value;

        $config['web_ui']['css_framework'] = $cssFramework;

        $container->setParameter('nowo_qr_code.config', $config);
        $container->setParameter('nowo_qr_code.default_profile', $defaultProfile);
        $container->setParameter('nowo_qr_code.profiles', $config['profiles']);
        $container->setParameter('nowo_qr_code.size', $default['size']);
        $container->setParameter('nowo_qr_code.margin', $default['margin']);
        $container->setParameter('nowo_qr_code.error_correction', $default['error_correction']);
        $container->setParameter('nowo_qr_code.url_allowlist', $default['url_allowlist']);
        $container->setParameter('nowo_qr_code.use_database_config', $config['use_database_config']);
        $container->setParameter('nowo_qr_code.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter('nowo_qr_code.security.access_roles', $config['security']['access_roles']);
        $container->setParameter('nowo_qr_code.security.access_checker', $config['security']['access_checker']);
        $container->setParameter('nowo_qr_code.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo_qr_code.web_ui.layout_template', $config['web_ui']['layout_template']);
        $container->setParameter('nowo_qr_code.web_ui.css_framework', $cssFramework);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (class_exists(AsTwigComponent::class)) {
            $loader->load('services_twig_component.yaml');
        }

        if ($config['use_database_config']) {
            if (
                !$config['security']['allow_unauthenticated']
                && !$this->isSecurityBundleAvailable($container)
            ) {
                throw new LogicException('NowoQrCodeBundle admin UI requires symfony/security-bundle when security.allow_unauthenticated is false.');
            }

            // Requires doctrine/orm + doctrine/doctrine-bundle (see composer suggest).
            $loader->load('services_database.yaml');
            $this->registerAccessChecker($container, $config['security']);

            $tablePrefix = (string) $config['doctrine']['table_prefix'];
            if ($tablePrefix !== '') {
                $definition = new Definition(TablePrefixListener::class, [$tablePrefix]);
                $definition->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
                $container->setDefinition(TablePrefixListener::class, $definition);
            }
        }
    }

    public function getAlias(): string
    {
        return 'nowo_qr_code';
    }

    /**
     * @param array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        if ($security['allow_unauthenticated']) {
            $accessCheckerId = 'nowo_qr_code.access_checker.allow_all';
            $container->setDefinition($accessCheckerId, new Definition(AllowAllQrCodeAccessChecker::class));
            $container->setAlias(QrCodeAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $accessCheckerId = $security['access_checker'] ?? null;
        if (is_string($accessCheckerId) && $accessCheckerId !== '') {
            $container->setAlias(QrCodeAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $hasAuthorizationChecker = $container->hasDefinition('security.authorization_checker')
            || $container->hasAlias('security.authorization_checker');

        $accessCheckerId = 'nowo_qr_code.access_checker.default';
        $definition      = new Definition(ConfigurableQrCodeAccessChecker::class);
        $definition->setArgument('$accessRoles', $security['access_roles']);
        if ($hasAuthorizationChecker) {
            $definition->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        } else {
            $definition->setAutowired(true);
        }
        $container->setDefinition($accessCheckerId, $definition);
        $container->setAlias(QrCodeAccessCheckerInterface::class, $accessCheckerId);
    }

    /**
     * Prefer kernel.bundles: ContainerBuilder::hasExtension() can be false while SecurityBundle
     * is already registered (e.g. during early Flex cache:clear boots).
     */
    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
    }
}
