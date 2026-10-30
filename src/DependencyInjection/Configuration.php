<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection;

use Nowo\QrCodeBundle\Enum\CssFramework;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Ui\QrCodePages;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function implode;
use function is_array;
use function sprintf;

/**
 * Configuration definition for QR Code Bundle (`nowo_qr_code`).
 *
 * Canonical shape: `default_profile` + `profiles` (REQ-CFG-001).
 * Flat root keys (`size`, `margin`, …) are normalized into `profiles.<default_profile>`.
 * Optional Doctrine storage: `use_database_config` + `doctrine.table_prefix`.
 */
final class Configuration implements ConfigurationInterface
{
    /** 1.x default layout (removed in 1.11; mapped to null = nowo_ui_kit.layouts.admin). */
    public const LEGACY_DEFAULT_LAYOUT = '@NowoQrCodeBundle/admin/layout.html.twig';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('nowo_qr_code');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->beforeNormalization()
                ->ifTrue(static fn ($v): bool => is_array($v) && (!isset($v['profiles']) || $v['profiles'] === []))
                ->then(static function (array $v): array {
                    $flatKeys = ['size', 'margin', 'error_correction', 'url_allowlist'];
                    $profile  = [];
                    foreach ($flatKeys as $key) {
                        if (array_key_exists($key, $v)) {
                            $profile[$key] = $v[$key];
                            unset($v[$key]);
                        }
                    }
                    $defaultName          = $v['default_profile'] ?? 'default';
                    $v['default_profile'] = $defaultName;
                    $v['profiles']        = [$defaultName => $profile];

                    return $v;
                })
            ->end()
            ->children()
                ->booleanNode('url_allowlist_required')
                    ->defaultFalse()
                    ->info('When true, container compilation fails if the default profile url_allowlist is empty (production hardening).')
                ->end()
                ->booleanNode('use_database_config')
                    ->info('When true, Doctrine rows with the same profile name fully override YAML profiles; enables admin CRUD and requires doctrine/orm')
                    ->defaultFalse()
                ->end()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('table_prefix')
                            ->info('Optional prefix for the qr_code_profile table (e.g. nowo_ → nowo_qr_code_profile)')
                            ->defaultValue('')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                            ->info('Roles allowed to use /admin/qr-code-profiles when allow_unauthenticated is false. Empty = deny everyone (fail-closed).')
                        ->end()
                        ->scalarNode('access_checker')
                            ->defaultNull()
                            ->info('Optional service id implementing QrCodeAccessCheckerInterface')
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->defaultFalse()
                            ->info('When true, admin CRUD is open (demo/dev only)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function (array $v): array {
                            // The 1.x default layout was removed (REQ-UI-003): null = nowo_ui_kit.layouts.admin.
                            if (($v['layout_template'] ?? null) === self::LEGACY_DEFAULT_LAYOUT) {
                                $v['layout_template'] = null;
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('layout_template')
                            ->defaultNull()
                            ->info('Layout the admin pages extend (REQ-UI-003). Null (default) = nowo_ui_kit.layouts.admin. A 1.x layout without the UiKit canonical blocks is wrapped in a deprecated bridge.')
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template overrides (page id => template): index, form. Extend @!NowoQrCodeBundle/... and override a data block.')
                            ->useAttributeAsKey('page')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff(array_keys($v), array_keys(QrCodePages::TEMPLATES)) !== [])
                                ->then(static function (array $v): never {
                                    throw new InvalidConfigurationException(sprintf('Unknown web_ui.templates page(s) "%s"; known: %s.', implode('", "', array_diff(array_keys($v), array_keys(QrCodePages::TEMPLATES))), implode(', ', array_keys(QrCodePages::TEMPLATES))));
                                })
                            ->end()
                        ->end()
                        ->enumNode('css_framework')
                            ->values(CssFramework::values())
                            ->defaultNull()
                            ->setDeprecated('nowo-tech/qr-code-bundle', '1.11', 'The "%path%" option is deprecated, set "nowo_ui_kit.css_framework" instead (REQ-UI-003).')
                            ->info('Deprecated since 1.11: the CSS stack is inherited from nowo_ui_kit.css_framework (copied there when the host did not set it).')
                        ->end()
                        ->integerNode('list_page_size')
                            ->defaultValue(50)
                            ->min(1)
                            ->max(500)
                            ->info('Profiles per page on the admin list (?page=N) (REQ-PERF-001).')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_profile')
                    ->info('Profile used when no explicit profile is passed to the service / Twig helpers')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('profiles')
                    ->info('Named QR rendering profiles (YAML baseline; overridden by DB when names match)')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('size')
                                ->defaultValue(300)
                                ->min(64)
                                ->max(1024)
                                ->info('QR code size in pixels')
                            ->end()
                            ->integerNode('margin')
                                ->defaultValue(10)
                                ->min(0)
                                ->max(64)
                                ->info('QR code quiet zone margin in pixels')
                            ->end()
                            ->enumNode('error_correction')
                                ->values(array_map(
                                    static fn (QrErrorCorrection $case): string => $case->value,
                                    QrErrorCorrection::cases(),
                                ))
                                ->defaultValue(QrErrorCorrection::High->value)
                                ->info('QR error correction level: low, medium, quartile, high')
                            ->end()
                            ->arrayNode('url_allowlist')
                                ->defaultValue([])
                                ->info('Optional host/URL patterns for createDataUriForUrl. Host patterns match exact host or subdomains; patterns with / match URL substrings; #… are regex. Empty = any http(s) URL.')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'default' => [
                            'size'             => 300,
                            'margin'           => 10,
                            'error_correction' => QrErrorCorrection::High->value,
                            'url_allowlist'    => [],
                        ],
                    ])
                ->end()
            ->end()
            ->validate()
                ->always(static function (array $v): array {
                    if (!isset($v['profiles'][$v['default_profile']])) {
                        throw new InvalidConfigurationException(sprintf('default_profile "%s" must exist under profiles.', $v['default_profile']));
                    }

                    return $v;
                })
            ->end();

        return $treeBuilder;
    }
}
