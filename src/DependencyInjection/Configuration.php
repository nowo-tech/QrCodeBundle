<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection;

use Nowo\QrCodeBundle\Enum\CssFramework;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_key_exists;
use function array_map;
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
                            ->info('Roles allowed to use /admin/qr-code-profiles when allow_unauthenticated is false')
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
                    ->children()
                        ->scalarNode('layout_template')
                            ->defaultValue('@NowoQrCodeBundle/admin/layout.html.twig')
                            ->cannotBeEmpty()
                            ->info('Twig layout extended by admin/base.html.twig (global nowo_qr_code_layout_template). Set to your app layout or a one-file bridge.')
                        ->end()
                        ->enumNode('css_framework')
                            ->values(CssFramework::values())
                            ->defaultValue(CssFramework::Custom->value)
                            ->info('Host CSS stack hint: bootstrap5 (alias bootstrap) | bootstrap4 | tabler | tailwind | foundation | custom | none. Default custom matches the demo standalone layout.')
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
