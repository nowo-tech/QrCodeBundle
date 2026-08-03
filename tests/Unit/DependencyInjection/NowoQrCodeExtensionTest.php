<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Nowo\QrCodeBundle\Enum\CssFramework;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Nowo\QrCodeBundle\Twig\Component\QrCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

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
        $this->assertTrue($container->hasDefinition(QrCode::class));
        $this->assertSame('default', $container->getParameter('nowo_qr_code.default_profile'));
        $this->assertSame(300, $container->getParameter('nowo_qr_code.size'));
        $this->assertSame(10, $container->getParameter('nowo_qr_code.margin'));
        $this->assertSame('high', $container->getParameter('nowo_qr_code.error_correction'));
        $this->assertSame([], $container->getParameter('nowo_qr_code.url_allowlist'));
        $this->assertSame('@NowoQrCodeBundle/admin/layout.html.twig', $container->getParameter('nowo_qr_code.web_ui.layout_template'));
        $this->assertSame(CssFramework::Custom->value, $container->getParameter('nowo_qr_code.web_ui.css_framework'));
    }

    public function testLoadNormalizesBootstrapAliasToBootstrap5(): void
    {
        $container = new ContainerBuilder();
        $this->extension->load([[
            'web_ui' => [
                'css_framework' => 'bootstrap',
            ],
        ]], $container);

        $this->assertSame(CssFramework::Bootstrap5->value, $container->getParameter('nowo_qr_code.web_ui.css_framework'));
    }

    public function testPrependAddsTwigGlobals(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->createExtension('twig'));
        $container->registerExtension(new NowoQrCodeExtension());
        $container->loadFromExtension('nowo_qr_code', [
            'web_ui' => [
                'layout_template' => 'base.html.twig',
                'css_framework'   => 'bootstrap5',
            ],
        ]);

        (new NowoQrCodeExtension())->prepend($container);

        self::assertSame([
            [
                'globals' => [
                    'nowo_qr_code_layout_template' => 'base.html.twig',
                    'nowo_qr_code_css_framework'   => 'bootstrap5',
                ],
            ],
        ], $container->getExtensionConfig('twig'));
    }

    public function testPrependEscapesAtSignInLayoutTemplateForDependencyInjection(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->createExtension('twig'));
        $container->registerExtension(new NowoQrCodeExtension());
        $container->loadFromExtension('nowo_qr_code', [
            'web_ui' => [
                'layout_template' => '@NowoQrCodeBundle/admin/layout.html.twig',
            ],
        ]);

        (new NowoQrCodeExtension())->prepend($container);

        $twigConfig = $container->getExtensionConfig('twig');
        self::assertSame(
            '@@NowoQrCodeBundle/admin/layout.html.twig',
            $twigConfig[0]['globals']['nowo_qr_code_layout_template'],
        );
    }

    public function testPrependNormalizesBootstrapAliasInTwigGlobal(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->createExtension('twig'));
        $container->registerExtension(new NowoQrCodeExtension());
        $container->loadFromExtension('nowo_qr_code', [
            'web_ui' => [
                'css_framework' => 'bootstrap',
            ],
        ]);

        (new NowoQrCodeExtension())->prepend($container);

        $twigConfig = $container->getExtensionConfig('twig');
        self::assertSame(
            CssFramework::Bootstrap5->value,
            $twigConfig[0]['globals']['nowo_qr_code_css_framework'],
        );
    }

    public function testPrependIsNoOpWithoutTwigExtension(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new NowoQrCodeExtension());
        $container->loadFromExtension('nowo_qr_code', []);

        (new NowoQrCodeExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('twig'));
    }

    private function createExtension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $aliasName)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->aliasName;
            }
        };
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
