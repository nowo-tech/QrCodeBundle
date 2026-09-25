<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use PHPUnit\Framework\TestCase;

final class QrCodeProfileConfigRepositoryTest extends TestCase
{
    public function testFindAllOrderedByNameRefreshesFromDatabase(): void
    {
        $entity = (new QrCodeProfileConfig())->setName('compact');

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getArrayResult', 'setHint', 'getResult'])
            ->getMock();
        $query->expects(self::once())->method('setHint')->with(Query::HINT_REFRESH, true)->willReturnSelf();
        $query->method('getResult')->willReturn([$entity]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->with('p.name', 'ASC')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(QrCodeProfileConfig::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repo = new class($registry, $qb) extends QrCodeProfileConfigRepository {
            public function __construct(ManagerRegistry $registry, private readonly QueryBuilder $qb)
            {
                parent::__construct($registry);
            }

            public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
            {
                return $this->qb;
            }
        };

        self::assertSame([$entity], $repo->findAllOrderedByName());
    }

    public function testFindProfileArrayByNameUsesArrayHydration(): void
    {
        $row = ['size' => 200, 'margin' => 4, 'error_correction' => 'low', 'url_allowlist' => ['example.com']];

        self::assertSame($row, $this->repository([$row])->findProfileArrayByName('compact'));
    }

    public function testFindProfileArrayByNameReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repository([])->findProfileArrayByName('compact'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function repository(array $rows): QrCodeProfileConfigRepository
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getArrayResult', 'setHint', 'getResult'])
            ->getMock();
        $query->method('getArrayResult')->willReturn($rows);
        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('select')
            ->with('p.size AS size', 'p.margin AS margin', 'p.errorCorrection AS error_correction', 'p.urlAllowlist AS url_allowlist')
            ->willReturnSelf();
        $qb->method('andWhere')->with('p.name = :name')->willReturnSelf();
        $qb->method('setParameter')->with('name', 'compact')->willReturnSelf();
        $qb->method('setMaxResults')->with(1)->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(QrCodeProfileConfig::class));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return new class($registry, $qb) extends QrCodeProfileConfigRepository {
            public function __construct(ManagerRegistry $registry, private readonly QueryBuilder $qb)
            {
                parent::__construct($registry);
            }

            public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
            {
                return $this->qb;
            }
        };
    }
}
