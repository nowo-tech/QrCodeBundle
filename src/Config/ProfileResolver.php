<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Config;

use Nowo\QrCodeBundle\Exception\InvalidQrProfileException;

use function array_keys;
use function sprintf;

/**
 * Resolves named QR profiles from bundle configuration.
 */
final readonly class ProfileResolver
{
    /**
     * @param array<string, array{
     *     size: int,
     *     margin: int,
     *     error_correction: string,
     *     url_allowlist: list<string>
     * }> $profiles
     */
    public function __construct(
        private array $profiles,
        private string $defaultProfile,
    ) {
    }

    public function resolve(?string $profile = null): QrCodeProfile
    {
        $name = $profile ?? $this->defaultProfile;
        if (!isset($this->profiles[$name])) {
            throw new InvalidQrProfileException(sprintf('Profile "%s" is not defined in nowo_qr_code.profiles.', $name));
        }

        return QrCodeProfile::fromArray($name, $this->profiles[$name]);
    }

    public function getDefaultProfileKey(): string
    {
        return $this->defaultProfile;
    }

    /**
     * @return list<string>
     */
    public function getProfileNames(): array
    {
        return array_keys($this->profiles);
    }
}
