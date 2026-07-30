<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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
     * @return list<QrCodeProfileConfig>
     */
    public function findAllOrderedByName(): array
    {
        /** @var list<QrCodeProfileConfig> $rows */
        $rows = $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
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
