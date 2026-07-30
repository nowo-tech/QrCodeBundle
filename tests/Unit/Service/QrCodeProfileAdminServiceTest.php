<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Service\QrCodeProfileAdminService;
use PHPUnit\Framework\TestCase;

final class QrCodeProfileAdminServiceTest extends TestCase
{
    public function testYamlProfileNamesAndDefault(): void
    {
        $service = new QrCodeProfileAdminService(
            $this->createMock(QrCodeProfileConfigRepository::class),
            $this->createMock(EntityManagerInterface::class),
            [
                'zebra' => ['size' => 100, 'margin' => 1, 'error_correction' => 'low', 'url_allowlist' => []],
                'alpha' => ['size' => 200, 'margin' => 2, 'error_correction' => 'high', 'url_allowlist' => []],
            ],
            'alpha',
        );

        self::assertSame(['alpha', 'zebra'], $service->yamlProfileNames());
        self::assertSame('alpha', $service->defaultProfile());
    }

    public function testYamlProfileNamesFallsBackToDefault(): void
    {
        $service = new QrCodeProfileAdminService(
            $this->createMock(QrCodeProfileConfigRepository::class),
            $this->createMock(EntityManagerInterface::class),
            [],
            'default',
        );

        self::assertSame(['default'], $service->yamlProfileNames());
    }

    public function testImportFromYamlCreatesAndUpdates(): void
    {
        $existing = (new QrCodeProfileConfig())->setName('default')->setSize(100);

        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->method('findAllOrderedByName')->willReturn([$existing]);

        $em        = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $em->expects(self::exactly(2))->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $em->expects(self::once())->method('flush');

        $service = new QrCodeProfileAdminService(
            $repo,
            $em,
            [
                'default' => [
                    'size'             => 300,
                    'margin'           => 10,
                    'error_correction' => 'high',
                    'url_allowlist'    => ['a.com'],
                ],
                'compact' => [
                    'size'             => 128,
                    'margin'           => 2,
                    'error_correction' => 'medium',
                    'url_allowlist'    => [],
                ],
            ],
            'default',
        );

        self::assertSame(2, $service->importFromYaml());
        self::assertSame(300, $existing->getSize());
        self::assertSame(['a.com'], $existing->getUrlAllowlist());
        self::assertCount(2, $persisted);
    }

    public function testImportFromYamlWithEmptyProfiles(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $service = new QrCodeProfileAdminService(
            $this->createMock(QrCodeProfileConfigRepository::class),
            $em,
            [],
            'default',
        );

        self::assertSame(0, $service->importFromYaml());
    }
}
