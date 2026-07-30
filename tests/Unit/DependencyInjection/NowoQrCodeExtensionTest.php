<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Nowo\QrCodeBundle\Service\QrCodeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoQrCodeExtensionTest extends TestCase
{
    private NowoQrCodeExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new NowoQrCodeExtension();
    }

    public function testAlias(): void
    {
        $this->assertSame('nowo_qr_code', $this->extension->getAlias());
    }

    public function testLoadRegistersServicesAndParameters(): void
    {
        $container = new ContainerBuilder();
        $this->extension->load([], $container);

        $this->assertTrue($container->hasDefinition(QrCodeService::class));
        $this->assertTrue($container->hasDefinition(ProfileResolver::class));
        $this->assertSame('default', $container->getParameter('nowo_qr_code.default_profile'));
        $this->assertSame(300, $container->getParameter('nowo_qr_code.size'));
        $this->assertSame(10, $container->getParameter('nowo_qr_code.margin'));
        $this->assertSame('high', $container->getParameter('nowo_qr_code.error_correction'));
        $this->assertSame([], $container->getParameter('nowo_qr_code.url_allowlist'));
    }

    public function testLoadFlatLegacyConfig(): void
    {
        $container = new ContainerBuilder();
        $this->extension->load([[
            'size'             => 256,
            'margin'           => 4,
            'error_correction' => 'low',
            'url_allowlist'    => ['nowo.tech'],
        ]], $container);

        $this->assertSame(256, $container->getParameter('nowo_qr_code.size'));
        $this->assertSame(4, $container->getParameter('nowo_qr_code.margin'));
        $this->assertSame('low', $container->getParameter('nowo_qr_code.error_correction'));
        $this->assertSame(['nowo.tech'], $container->getParameter('nowo_qr_code.url_allowlist'));

        /** @var array<string, array{url_allowlist: list<string>}> $profiles */
        $profiles = $container->getParameter('nowo_qr_code.profiles');
        $this->assertSame(['nowo.tech'], $profiles['default']['url_allowlist']);
    }

    public function testLoadNamedProfiles(): void
    {
        $container = new ContainerBuilder();
        $this->extension->load([[
            'default_profile' => 'wallet',
            'profiles'        => [
                'default' => ['size' => 300],
                'wallet'  => [
                    'size'          => 200,
                    'url_allowlist' => ['pay.google.com'],
                ],
            ],
        ]], $container);

        $this->assertSame('wallet', $container->getParameter('nowo_qr_code.default_profile'));
        $this->assertSame(200, $container->getParameter('nowo_qr_code.size'));
        $this->assertSame(['pay.google.com'], $container->getParameter('nowo_qr_code.url_allowlist'));
    }
}
