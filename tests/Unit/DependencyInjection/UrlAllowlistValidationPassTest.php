<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\DependencyInjection;

use Nowo\QrCodeBundle\DependencyInjection\UrlAllowlistValidationPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @covers \Nowo\QrCodeBundle\DependencyInjection\UrlAllowlistValidationPass
 */
final class UrlAllowlistValidationPassTest extends TestCase
{
    public function testFailsWhenRequiredAndAllowlistEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_qr_code.url_allowlist', []);
        $container->setParameter('nowo_qr_code.url_allowlist_required', true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('url_allowlist_required is true');

        (new UrlAllowlistValidationPass())->process($container);
    }

    public function testPassesWhenRequiredAndAllowlistNonEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_qr_code.url_allowlist', ['example.com']);
        $container->setParameter('nowo_qr_code.url_allowlist_required', true);

        (new UrlAllowlistValidationPass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testPassesWhenNotRequiredAndAllowlistEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_qr_code.url_allowlist', []);
        $container->setParameter('nowo_qr_code.url_allowlist_required', false);

        (new UrlAllowlistValidationPass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testSkipsWhenAllowlistParameterMissing(): void
    {
        (new UrlAllowlistValidationPass())->process(new ContainerBuilder());

        $this->expectNotToPerformAssertions();
    }
}
