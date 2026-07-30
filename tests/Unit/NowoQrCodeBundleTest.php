<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit;

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

    public function testBuild(): void
    {
        (new NowoQrCodeBundle())->build(new ContainerBuilder());
        $this->addToAssertionCount(1);
    }
}
