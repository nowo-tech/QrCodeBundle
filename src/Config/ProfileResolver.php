<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Config;

use Nowo\QrCodeBundle\Exception\InvalidQrProfileException;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;

use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function sort;
use function sprintf;

/**
 * Resolves named QR profiles from YAML, overridden by a profile source.
 *
 * The source is {@see QrCodeProfileProviderInterface}: the bundle's Doctrine rows with use_database_config
 * ({@see DoctrineQrCodeProfileProvider}), or the host's `profile_provider` (1.13). A profile of the source fully
 * replaces the YAML profile with the same name; names only the source knows are also resolvable.
 *
 * Nothing is memoized: every call asks the source again, so edits made in any worker apply to the next render without
 * a kernel reset.
 */
final readonly class ProfileResolver
{
    private ?QrCodeProfileProviderInterface $source;

    /**
     * @param array<string, array{
     *     size: int,
     *     margin: int,
     *     error_correction: string,
     *     url_allowlist: list<string>
     * }> $profiles
     * @param QrCodeProfileConfigRepository|null $profileRepository deprecated since 1.13 (removed in 2.0): pass
     *                                                              `$profileProvider` (a {@see DoctrineQrCodeProfileProvider})
     * @param QrCodeProfileProviderInterface|null $profileProvider the profile source (1.13); wins over $profileRepository
     */
    public function __construct(
        private array $profiles,
        private string $defaultProfile,
        private bool $useDatabaseConfig = false,
        ?QrCodeProfileConfigRepository $profileRepository = null,
        ?QrCodeProfileProviderInterface $profileProvider = null,
    ) {
        $this->source = $profileProvider
            ?? ($useDatabaseConfig && $profileRepository instanceof QrCodeProfileConfigRepository ? new DoctrineQrCodeProfileProvider($profileRepository) : null);
    }

    public function resolve(?string $profile = null): QrCodeProfile
    {
        $name = $profile ?? $this->defaultProfile;

        $found = $this->source?->find($name);
        if ($found instanceof QrCodeProfile) {
            return $found;
        }

        if (!isset($this->profiles[$name])) {
            throw new InvalidQrProfileException(sprintf('Profile "%s" is not defined in nowo_qr_code.profiles%s.', $name, $this->useDatabaseConfig ? ' or the database' : ($this->source instanceof QrCodeProfileProviderInterface ? ' or the profile provider' : '')));
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

        if ($this->source instanceof QrCodeProfileProviderInterface) {
            $names = array_merge($names, $this->source->names());
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
