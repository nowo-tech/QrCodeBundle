<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Exception;

use RuntimeException;

/**
 * Thrown when a URL cannot be encoded into a QR code.
 */
final class InvalidQrUrlException extends RuntimeException
{
}
