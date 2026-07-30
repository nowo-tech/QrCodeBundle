<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Service;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use Nowo\QrCodeBundle\Service\QrCodeService;
use PHPUnit\Framework\TestCase;

final class QrCodeServiceTest extends TestCase
{
    public function testCreateDataUri(): void
    {
        $service = new QrCodeService($this->resolver());
        $dataUri = $service->createDataUri('plain-text-payload');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testCreateDataUriWithProfile(): void
    {
        $service = new QrCodeService($this->resolver());
        $dataUri = $service->createDataUri('plain-text-payload', 'compact');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testCreateDataUriForUrl(): void
    {
        $service = new QrCodeService($this->resolver());
        $dataUri = $service->createDataUriForUrl('https://example.com/path');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testCreateDataUriForUrlUsesProfileAllowlist(): void
    {
        $this->expectException(InvalidQrUrlException::class);

        $service = new QrCodeService($this->resolver());
        $service->createDataUriForUrl('https://evil.com/path', 'compact');
    }

    public function testCreateDataUriForUrlRejectsUnsafeScheme(): void
    {
        $this->expectException(InvalidQrUrlException::class);

        $service = new QrCodeService($this->resolver());
        $service->createDataUriForUrl('javascript:alert(1)');
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
