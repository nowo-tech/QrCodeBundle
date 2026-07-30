<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\QrCode;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;

/**
 * Renders arbitrary content as PNG QR codes encoded as data URIs.
 */
final class QrCodeDataUriRenderer
{
    private readonly QrErrorCorrection $errorCorrection;

    /**
     * @param QrErrorCorrection|string $errorCorrection Enum or string value (`low`|`medium`|`quartile`|`high`)
     */
    public function __construct(
        private readonly int $size = 300,
        private readonly int $margin = 10,
        QrErrorCorrection|string $errorCorrection = QrErrorCorrection::High,
    ) {
        $this->errorCorrection = $errorCorrection instanceof QrErrorCorrection
            ? $errorCorrection
            : QrErrorCorrection::from(strtolower($errorCorrection));
    }

    public function renderDataUri(string $content): string
    {
        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $content,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $this->resolveErrorCorrectionLevel(),
            size: $this->size,
            margin: $this->margin,
        );

        $result = $builder->build();

        return $result->getDataUri();
    }

    private function resolveErrorCorrectionLevel(): ErrorCorrectionLevel
    {
        return match ($this->errorCorrection) {
            QrErrorCorrection::Low      => ErrorCorrectionLevel::Low,
            QrErrorCorrection::Medium   => ErrorCorrectionLevel::Medium,
            QrErrorCorrection::Quartile => ErrorCorrectionLevel::Quartile,
            QrErrorCorrection::High     => ErrorCorrectionLevel::High,
        };
    }
}
