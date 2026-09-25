<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\QrCodeBundle\Config\QrCodeProfile;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;

/**
 * @extends ServiceEntityRepository<QrCodeProfileConfig>
 */
class QrCodeProfileConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QrCodeProfileConfig::class);
    }

    public function findOneByName(string $name): ?QrCodeProfileConfig
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Profile values for {@see QrCodeProfile::fromArray()}, read with array hydration.
     *
     * Bypasses the identity map so that a long-running worker never renders with a managed entity
     * loaded in an earlier request (stale `url_allowlist`, size, …) after another worker edited it.
     *
     * @return array{size: int, margin: int, error_correction: string, url_allowlist: list<string>}|null
     */
    public function findProfileArrayByName(string $name): ?array
    {
        /** @var list<array{size: int, margin: int, error_correction: string, url_allowlist: list<string>}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p.size AS size', 'p.margin AS margin', 'p.errorCorrection AS error_correction', 'p.urlAllowlist AS url_allowlist')
            ->andWhere('p.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return $rows[0] ?? null;
    }

    /**
     * @return list<QrCodeProfileConfig>
     */
    public function findAllOrderedByName(): array
    {
        /** @var list<QrCodeProfileConfig> $rows */
        $rows = $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function findAllNames(): array
    {
        /** @var list<string> $names */
        $names = $this->createQueryBuilder('p')
            ->select('p.name')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $names;
    }
}
