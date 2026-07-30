<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;

use function array_keys;
use function sort;

/**
 * Seeds / imports QR profiles for the admin CRUD.
 */
final readonly class QrCodeProfileAdminService
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
        private QrCodeProfileConfigRepository $repository,
        private EntityManagerInterface $entityManager,
        private array $profiles,
        private string $defaultProfile,
    ) {
    }

    /**
     * @return list<string>
     */
    public function yamlProfileNames(): array
    {
        $names = array_keys($this->profiles);
        if ($names === []) {
            return [$this->defaultProfile];
        }

        sort($names);

        return $names;
    }

    public function defaultProfile(): string
    {
        return $this->defaultProfile;
    }

    /**
     * Upserts every YAML profile into the database.
     *
     * @return int Number of profiles created or updated
     */
    public function importFromYaml(): int
    {
        if ($this->profiles === []) {
            return 0;
        }

        $byName = [];
        foreach ($this->repository->findAllOrderedByName() as $row) {
            $byName[$row->getName()] = $row;
        }

        $touched = 0;
        foreach ($this->profiles as $name => $definition) {
            $row = $byName[$name] ?? (new QrCodeProfileConfig())->setName((string) $name);
            $row
                ->setSize((int) $definition['size'])
                ->setMargin((int) $definition['margin'])
                ->setErrorCorrection((string) ($definition['error_correction'] ?? QrErrorCorrection::High->value))
                ->setUrlAllowlist($definition['url_allowlist'] ?? []);

            $this->entityManager->persist($row);
            ++$touched;
        }

        $this->entityManager->flush();

        return $touched;
    }
}
