<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\DependencyInjection;

use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_key_exists;
use function is_array;
use function sprintf;

/**
 * Configuration definition for QR Code Bundle (`nowo_qr_code`).
 *
 * Canonical shape: `default_profile` + `profiles` (REQ-CFG-001).
 * Flat root keys (`size`, `margin`, …) are normalized into `profiles.<default_profile>`.
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
                ->ifTrue(static function ($v): bool {
                    return is_array($v) && (!isset($v['profiles']) || $v['profiles'] === []);
                })
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
                ->scalarNode('default_profile')
                    ->info('Profile used when no explicit profile is passed to the service / Twig helpers')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('profiles')
                    ->info('Named QR rendering profiles')
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
