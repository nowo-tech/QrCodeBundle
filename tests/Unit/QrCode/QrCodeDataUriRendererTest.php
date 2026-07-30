<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\QrCode;

use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\QrCode\QrCodeDataUriRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

final class QrCodeDataUriRendererTest extends TestCase
{
    public function testRenderDataUri(): void
    {
        $renderer = new QrCodeDataUriRenderer(size: 200, margin: 5, errorCorrection: 'medium');
        $dataUri  = $renderer->renderDataUri('https://example.com/demo');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testRenderDataUriWithEnum(): void
    {
        $renderer = new QrCodeDataUriRenderer(errorCorrection: QrErrorCorrection::Low);
        $dataUri  = $renderer->renderDataUri('enum-payload');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    #[DataProvider('errorCorrectionProvider')]
    public function testRenderDataUriWithErrorCorrectionLevels(string $level): void
    {
        $renderer = new QrCodeDataUriRenderer(size: 120, margin: 2, errorCorrection: $level);
        $dataUri  = $renderer->renderDataUri('https://example.com/' . $level);

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testInvalidErrorCorrectionThrows(): void
    {
        $this->expectException(ValueError::class);
        new QrCodeDataUriRenderer(errorCorrection: 'invalid');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function errorCorrectionProvider(): iterable
    {
        yield 'low' => ['low'];
        yield 'quartile' => ['quartile'];
        yield 'high' => ['high'];
    }
}
