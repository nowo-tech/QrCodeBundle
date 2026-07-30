<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit;

use Nowo\QrCodeBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\QrCodeBundle\DependencyInjection\NowoQrCodeExtension;
use Nowo\QrCodeBundle\NowoQrCodeBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoQrCodeBundleTest extends TestCase
{
    public function testGetName(): void
    {
        $this->assertSame('NowoQrCodeBundle', (new NowoQrCodeBundle())->getName());
    }

    public function testGetContainerExtension(): void
    {
        $extension = (new NowoQrCodeBundle())->getContainerExtension();
        $this->assertInstanceOf(NowoQrCodeExtension::class, $extension);
        $this->assertSame('nowo_qr_code', $extension->getAlias());
    }

    public function testBuildRegistersTwigPathsPass(): void
    {
        $container = new ContainerBuilder();
        (new NowoQrCodeBundle())->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        $found  = false;
        foreach ($passes as $pass) {
            if ($pass instanceof TwigPathsPass) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }
}
