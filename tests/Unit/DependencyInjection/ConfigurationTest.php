<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection;

use Nowo\QrCodeBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor     = new Processor();
    }

    public function testGetConfigTreeBuilder(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();
        $this->assertSame('nowo_qr_code', $treeBuilder->buildTree()->getName());
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertSame('default', $config['default_profile']);
        $this->assertArrayHasKey('default', $config['profiles']);
        $this->assertSame(300, $config['profiles']['default']['size']);
        $this->assertSame(10, $config['profiles']['default']['margin']);
        $this->assertSame('high', $config['profiles']['default']['error_correction']);
        $this->assertSame([], $config['profiles']['default']['url_allowlist']);
        $this->assertFalse($config['use_database_config']);
        $this->assertSame('', $config['doctrine']['table_prefix']);
        $this->assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        $this->assertFalse($config['security']['allow_unauthenticated']);
    }

    public function testDatabaseConfigOptions(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [[
            'use_database_config' => true,
            'doctrine'            => ['table_prefix' => 'nowo_'],
            'security'            => [
                'access_roles'          => ['ROLE_SUPER_ADMIN'],
                'allow_unauthenticated' => true,
            ],
            'profiles' => [
                'default' => [],
            ],
        ]]);

        $this->assertTrue($config['use_database_config']);
        $this->assertSame('nowo_', $config['doctrine']['table_prefix']);
        $this->assertSame(['ROLE_SUPER_ADMIN'], $config['security']['access_roles']);
        $this->assertTrue($config['security']['allow_unauthenticated']);
    }

    public function testFlatLegacyConfigurationIsNormalizedIntoDefaultProfile(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [[
            'size'             => 400,
            'margin'           => 5,
            'error_correction' => 'medium',
            'url_allowlist'    => ['example.com'],
        ]]);

        $this->assertSame('default', $config['default_profile']);
        $this->assertSame(400, $config['profiles']['default']['size']);
        $this->assertSame(5, $config['profiles']['default']['margin']);
        $this->assertSame('medium', $config['profiles']['default']['error_correction']);
        $this->assertSame(['example.com'], $config['profiles']['default']['url_allowlist']);
    }

    public function testNamedProfiles(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [[
            'default_profile' => 'compact',
            'profiles'        => [
                'default' => [
                    'size' => 300,
                ],
                'compact' => [
                    'size'             => 128,
                    'margin'           => 2,
                    'error_correction' => 'low',
                    'url_allowlist'    => ['nowo.tech'],
                ],
            ],
        ]]);

        $this->assertSame('compact', $config['default_profile']);
        $this->assertSame(128, $config['profiles']['compact']['size']);
        $this->assertSame(['nowo.tech'], $config['profiles']['compact']['url_allowlist']);
    }

    public function testUnknownDefaultProfileIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('default_profile "missing" must exist under profiles.');

        $this->processor->processConfiguration($this->configuration, [[
            'default_profile' => 'missing',
            'profiles'        => [
                'default' => [],
            ],
        ]]);
    }

    public function testSizeMustBeWithinBounds(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[
            'profiles' => [
                'default' => ['size' => 32],
            ],
        ]]);
    }

    public function testInvalidErrorCorrectionIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->configuration, [[
            'profiles' => [
                'default' => ['error_correction' => 'invalid'],
            ],
        ]]);
    }
}
