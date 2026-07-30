<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Enum;

/**
 * Supported QR error-correction levels (maps to endroid/qr-code).
 */
enum QrErrorCorrection: string
{
    case Low      = 'low';
    case Medium   = 'medium';
    case Quartile = 'quartile';
    case High     = 'high';
}
