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
 * With a host `profile_provider` there is no YAML fallback (1.13, audit LR-Q-L1): a name the provider does not return
 * (an unknown tenant, a lookup that failed softly) throws instead of silently getting a YAML profile whose allow-list
 * may be wider. Every profile from a source is checked against the YAML bounds (size 64–1024, margin 0–64; audit
 * LR-Q-L2), so a host or a database row can never ask the renderer for a huge image.
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
     * @param bool $yamlFallback whether a name the source does not define falls back to YAML (false for a host
     *                           `profile_provider`: fail closed)
     */
    public function __construct(
        private array $profiles,
        private string $defaultProfile,
        private bool $useDatabaseConfig = false,
        ?QrCodeProfileConfigRepository $profileRepository = null,
        ?QrCodeProfileProviderInterface $profileProvider = null,
        private bool $yamlFallback = true,
    ) {
        $this->source = $profileProvider
            ?? ($useDatabaseConfig && $profileRepository instanceof QrCodeProfileConfigRepository ? new DoctrineQrCodeProfileProvider($profileRepository) : null);
    }

    public function resolve(?string $profile = null): QrCodeProfile
    {
        $name = $profile ?? $this->defaultProfile;

        $found = $this->source?->find($name);
        if ($found instanceof QrCodeProfile) {
            return $this->checked($found);
        }

        if (!$this->yamlFallback) {
            throw new InvalidQrProfileException(sprintf('Profile "%s" is not defined by the profile provider (no YAML fallback with nowo_qr_code.profile_provider).', $name));
        }

        if (!isset($this->profiles[$name])) {
            throw new InvalidQrProfileException(sprintf('Profile "%s" is not defined in nowo_qr_code.profiles%s.', $name, $this->useDatabaseConfig ? ' or the database' : ($this->source instanceof QrCodeProfileProviderInterface ? ' or the profile provider' : '')));
        }

        return QrCodeProfile::fromArray($name, $this->profiles[$name]);
    }

    /**
     * The profile, when its size and margin are within the bounds of the YAML tree.
     */
    private function checked(QrCodeProfile $profile): QrCodeProfile
    {
        if ($profile->size < QrCodeProfile::MIN_SIZE || $profile->size > QrCodeProfile::MAX_SIZE || $profile->margin < 0 || $profile->margin > QrCodeProfile::MAX_MARGIN) {
            throw new InvalidQrProfileException(sprintf('Profile "%s" has size %d and margin %d; allowed: size %d-%d, margin 0-%d.', $profile->name, $profile->size, $profile->margin, QrCodeProfile::MIN_SIZE, QrCodeProfile::MAX_SIZE, QrCodeProfile::MAX_MARGIN));
        }

        return $profile;
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
        $names = $this->yamlFallback ? array_keys($this->profiles) : [];

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
