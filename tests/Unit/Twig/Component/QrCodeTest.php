<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Twig\Component;

use InvalidArgumentException;
use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Nowo\QrCodeBundle\Twig\Component\QrCode;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    public function testMountWithContent(): void
    {
        $component = new QrCode(new QrCodeService($this->resolver()));
        $component->mount(content: 'EVENT-42', alt: 'Ticket', class: 'qr');

        self::assertStringStartsWith('data:image/png;base64,', $component->dataUri);
        self::assertSame('Ticket', $component->alt);
        self::assertSame('qr', $component->class);
    }

    public function testMountWithUrlAndProfile(): void
    {
        $component = new QrCode(new QrCodeService($this->resolver()));
        $component->mount(url: 'https://example.com/pass', profile: 'compact');

        self::assertStringStartsWith('data:image/png;base64,', $component->dataUri);
    }

    public function testMountForUrlUsesPolicy(): void
    {
        $this->expectException(InvalidQrUrlException::class);

        $component = new QrCode(new QrCodeService($this->resolver()));
        $component->mount(content: 'javascript:alert(1)', forUrl: true);
    }

    public function testMountRequiresContentOrUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $component = new QrCode(new QrCodeService($this->resolver()));
        $component->mount();
    }

    public function testMountForUrlRequiresNonEmptyTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty url');

        $component = new QrCode(new QrCodeService($this->resolver()));
        $component->mount(url: '', forUrl: true);
    }

    private function resolver(): ProfileResolver
    {
        return new ProfileResolver(
            [
                'default' => [
                    'size'             => 200,
                    'margin'           => 4,
                    'error_correction' => 'high',
                    'url_allowlist'    => [],
                ],
                'compact' => [
                    'size'             => 128,
                    'margin'           => 2,
                    'error_correction' => 'medium',
                    'url_allowlist'    => ['example.com'],
                ],
            ],
            'default',
        );
    }
}
