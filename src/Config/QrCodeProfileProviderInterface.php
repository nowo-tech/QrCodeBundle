<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Config;

/**
 * Port (1.13, architecture bar rule 8): a source of named QR profiles that overrides the YAML `profiles`.
 *
 * The bundle ships {@see DoctrineQrCodeProfileProvider} (its own `QrCodeProfileConfig` table, `use_database_config`);
 * a host sets `nowo_qr_code.profile_provider` to its own service (tenant settings, a configuration service, …) and
 * needs neither Doctrine nor the bundle's table. A profile returned here fully replaces the YAML profile of the same
 * name; names it does not know fall back to YAML.
 *
 * Called on every render (FrankenPHP worker mode, kernel kept alive): resolve per call and keep no request, user or
 * tenant state in the implementation.
 */
interface QrCodeProfileProviderInterface
{
    /**
     * The profile with this name, or null when this source does not define it (YAML then applies).
     */
    public function find(string $name): ?QrCodeProfile;

    /**
     * The names this source defines (listed next to the YAML ones).
     *
     * @return list<string>
     */
    public function names(): array;
}
