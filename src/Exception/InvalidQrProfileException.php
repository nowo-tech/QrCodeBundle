<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Exception;

use InvalidArgumentException;

/**
 * Thrown when a QR profile name is not defined in configuration.
 */
final class InvalidQrProfileException extends InvalidArgumentException
{
}
