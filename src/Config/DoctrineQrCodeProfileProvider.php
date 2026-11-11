<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Config;

use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;

/**
 * {@see QrCodeProfileProviderInterface} over the bundle's `QrCodeProfileConfig` rows (`use_database_config`). Reads
 * the current row on every call (no identity map), so admin edits made in any worker apply to the next render.
 */
final readonly class DoctrineQrCodeProfileProvider implements QrCodeProfileProviderInterface
{
    public function __construct(
        private QrCodeProfileConfigRepository $repository,
    ) {
    }

    public function find(string $name): ?QrCodeProfile
    {
        $stored = $this->repository->findProfileArrayByName($name);

        return $stored === null ? null : QrCodeProfile::fromArray($name, $stored);
    }

    public function names(): array
    {
        return $this->repository->findAllNames();
    }
}
