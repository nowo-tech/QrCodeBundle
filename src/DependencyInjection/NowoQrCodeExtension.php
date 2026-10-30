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

use function array_key_exists;
use function class_exists;
use function is_array;
use function is_string;
use function str_starts_with;

/**
 * Dependency injection extension for the QR Code bundle.
 */
final class NowoQrCodeExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependFormKitDefaults($container);
        if ($container->hasExtension('twig')) {
            $configs = $container->getExtensionConfig($this->getAlias());
            $config  = $this->processConfiguration(new Configuration(), $configs);

            $layoutTemplate = $config['web_ui']['layout_template'];
            // Escape leading "@" so DI does not treat Twig logical names as service refs.
            if (is_string($layoutTemplate) && str_starts_with($layoutTemplate, '@')) {
                $layoutTemplate = '@' . $layoutTemplate;
            }

            // Deprecated since 1.11 (REQ-UI-003: the pages extend UiKit scaffolds and read neither); kept for one minor
            // for host templates that still reference them.
            $container->prependExtensionConfig('twig', [
                'globals' => [
                    'nowo_qr_code_layout_template' => $layoutTemplate,
                    'nowo_qr_code_css_framework'   => $this->cssFramework($config['web_ui']['css_framework']) ?? CssFramework::Custom->value,
                ],
            ]);
        }

        $this->prependUiKitDefaults($container);
    }

    /**
     * When FormKit is installed, register the qr_code profile. Forms select it via #[FormKitConfig].
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasProfile      = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('qr_code', $profiles)) {
                $hostHasProfile = true;
            }
        }

        $seed = [];

        if (!$hostHasCssFramework) {
            $seed['css_framework'] = 'bootstrap';
        }

        if (!$hostHasProfile) {
            $seed['profiles'] = [
                'qr_code' => [
                    'alias'              => 'qr_code',
                    'translation_domain' => 'NowoQrCodeBundle',
                    // Placeholder / help convention keys only where the catalogue has them (no raw keys).
                    'convention_requires_translation' => true,
                    // REQ-UI-003: no framework utility classes in bundle markup; the host form theme (or this profile,
                    // which the host may redefine) styles the fields. `nowo-ui-input` is the UiKit semantic hook.
                    'defaults' => [
                        'attr'     => ['class' => 'nowo-ui-input'],
                        'row_attr' => [],
                    ],
                ],
            ];
        }

        if ($seed !== []) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }

    /**
     * When UiKit is installed and the host set the deprecated web_ui.css_framework (REQ-UI-003: the CSS stack is
     * inherited from nowo_ui_kit), copy it to nowo_ui_kit.css_framework unless the host set that key there.
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (is_array($cfg) && array_key_exists('css_framework', $cfg)) {
                return;
            }
        }

        $config    = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        $framework = $this->cssFramework($config['web_ui']['css_framework']);
        if ($framework !== null) {
            $container->prependExtensionConfig('nowo_ui_kit', ['css_framework' => $framework]);
        }
    }

    /**
     * Normalised deprecated web_ui.css_framework (bootstrap => bootstrap5), null when not set.
     */
    private function cssFramework(mixed $value): ?string
    {
        return is_string($value) ? CssFramework::from($value)->normalized()->value : null;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $defaultProfile = $config['default_profile'];
        $default        = $config['profiles'][$defaultProfile];
        $cssFramework   = $this->cssFramework($config['web_ui']['css_framework']);

        $config['web_ui']['css_framework'] = $cssFramework;

        $container->setParameter('nowo_qr_code.config', $config);
        $container->setParameter('nowo_qr_code.default_profile', $defaultProfile);
        $container->setParameter('nowo_qr_code.profiles', $config['profiles']);
        $container->setParameter('nowo_qr_code.size', $default['size']);
        $container->setParameter('nowo_qr_code.margin', $default['margin']);
        $container->setParameter('nowo_qr_code.error_correction', $default['error_correction']);
        $container->setParameter('nowo_qr_code.url_allowlist', $default['url_allowlist']);
        $container->setParameter('nowo_qr_code.url_allowlist_required', $config['url_allowlist_required']);
        $container->setParameter('nowo_qr_code.use_database_config', $config['use_database_config']);
        $container->setParameter('nowo_qr_code.doctrine.table_prefix', $config['doctrine']['table_prefix']);
        $container->setParameter('nowo_qr_code.security.access_roles', $config['security']['access_roles']);
        $container->setParameter('nowo_qr_code.security.access_checker', $config['security']['access_checker']);
        $container->setParameter('nowo_qr_code.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo_qr_code.web_ui.layout_template', $config['web_ui']['layout_template']);
        $container->setParameter('nowo_qr_code.web_ui.templates', $config['web_ui']['templates']);
        $container->setParameter('nowo_qr_code.web_ui.css_framework', $cssFramework);
        $container->setParameter('nowo_qr_code.web_ui.list_page_size', $config['web_ui']['list_page_size']);

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
            $this->refuseAllowUnauthenticatedInProduction($container);
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

    /**
     * `allow_unauthenticated` opens the QR code profile admin to anonymous users: a dev / demo switch, refused in the prod environment and in
     * any non-debug kernel outside the test environments (test*), as in ContactFormBundle.
     */
    private function refuseAllowUnauthenticatedInProduction(ContainerBuilder $container): void
    {
        $environment = $container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : '';
        $environment = is_string($environment) ? $environment : '';
        $debug       = $container->hasParameter('kernel.debug') ? (bool) $container->getParameter('kernel.debug') : true;

        if ($environment === 'prod' || (!$debug && !str_starts_with($environment, 'test'))) {
            throw new LogicException('nowo_qr_code.security.allow_unauthenticated is a dev/demo switch: it opens the QR code profile admin to anonymous users and is refused in the prod environment and in non-debug kernels (test* environments excepted).');
        }
    }
}
