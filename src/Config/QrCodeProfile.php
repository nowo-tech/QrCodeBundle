<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Config;

use Nowo\QrCodeBundle\Enum\QrErrorCorrection;

/**
 * Resolved settings for one named QR profile.
 */
final readonly class QrCodeProfile
{
    /** Bounds of `size` and `margin` (the YAML tree and the admin form use the same; 1.13 checks every source). */
    public const MIN_SIZE = 64;

    public const MAX_SIZE = 1024;

    public const MAX_MARGIN = 64;

    /**
     * @param list<string> $urlAllowlist
     */
    public function __construct(
        public string $name,
        public int $size,
        public int $margin,
        public QrErrorCorrection $errorCorrection,
        public array $urlAllowlist,
    ) {
    }

    /**
     * @param array{
     *     size: int,
     *     margin: int,
     *     error_correction: string,
     *     url_allowlist: list<string>
     * } $config
     */
    public static function fromArray(string $name, array $config): self
    {
        return new self(
            name: $name,
            size: $config['size'],
            margin: $config['margin'],
            errorCorrection: QrErrorCorrection::from($config['error_correction']),
            urlAllowlist: $config['url_allowlist'],
        );
    }
}
