<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Config;

use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Exception\InvalidQrProfileException;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;

use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function sort;
use function sprintf;

/**
 * Resolves named QR profiles from YAML with optional Doctrine overrides.
 *
 * When use_database_config is true and a DB row shares a profile name, the DB row
 * fully replaces the YAML profile. DB-only names are also resolvable.
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
        private bool $useDatabaseConfig = false,
        private ?QrCodeProfileConfigRepository $profileRepository = null,
    ) {
    }

    public function resolve(?string $profile = null): QrCodeProfile
    {
        $name = $profile ?? $this->defaultProfile;

        if ($this->useDatabaseConfig && $this->profileRepository instanceof QrCodeProfileConfigRepository) {
            $stored = $this->profileRepository->findOneByName($name);
            if ($stored instanceof QrCodeProfileConfig) {
                return QrCodeProfile::fromArray($name, $stored->toProfileArray());
            }
        }

        if (!isset($this->profiles[$name])) {
            throw new InvalidQrProfileException(sprintf('Profile "%s" is not defined in nowo_qr_code.profiles%s.', $name, $this->useDatabaseConfig ? ' or the database' : ''));
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
        $names = array_keys($this->profiles);

        if ($this->useDatabaseConfig && $this->profileRepository instanceof QrCodeProfileConfigRepository) {
            $names = array_merge($names, $this->profileRepository->findAllNames());
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    public function usesDatabaseConfig(): bool
    {
        return $this->useDatabaseConfig;
    }
}
