<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Twig;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Nowo\QrCodeBundle\Twig\QrCodeExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

final class QrCodeExtensionTest extends TestCase
{
    public function testQrCodeDataUri(): void
    {
        $extension = new QrCodeExtension(new QrCodeService($this->resolver()));
        $dataUri   = $extension->qrCodeDataUri('https://example.com');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testQrCodeDataUriWithProfile(): void
    {
        $extension = new QrCodeExtension(new QrCodeService($this->resolver()));
        $dataUri   = $extension->qrCodeDataUri('https://example.com', 'compact');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testQrCodeForUrl(): void
    {
        $extension = new QrCodeExtension(new QrCodeService($this->resolver()));
        $dataUri   = $extension->qrCodeForUrl('https://example.com/pass');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testGetFunctions(): void
    {
        $extension = new QrCodeExtension(new QrCodeService($this->resolver()));
        $functions = $extension->getFunctions();

        $this->assertCount(2, $functions);
        $this->assertContainsOnlyInstancesOf(TwigFunction::class, $functions);
        $this->assertSame(
            ['qr_code_data_uri', 'qr_code_for_url'],
            array_map(static fn (TwigFunction $function): string => $function->getName(), $functions),
        );
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
                    'url_allowlist'    => [],
                ],
            ],
            'default',
        );
    }
}
