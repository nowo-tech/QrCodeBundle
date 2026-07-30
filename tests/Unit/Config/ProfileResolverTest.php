<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Config;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Exception\InvalidQrProfileException;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use PHPUnit\Framework\TestCase;

final class ProfileResolverTest extends TestCase
{
    public function testResolveDefaultProfile(): void
    {
        $resolver = $this->resolver();
        $profile  = $resolver->resolve();

        self::assertSame('default', $profile->name);
        self::assertSame(300, $profile->size);
        self::assertSame(QrErrorCorrection::High, $profile->errorCorrection);
        self::assertSame('default', $resolver->getDefaultProfileKey());
        self::assertSame(['compact', 'default'], $resolver->getProfileNames());
    }

    public function testResolveNamedProfile(): void
    {
        $resolver = $this->resolver();
        $profile  = $resolver->resolve('compact');

        self::assertSame('compact', $profile->name);
        self::assertSame(128, $profile->size);
        self::assertSame(['example.com'], $profile->urlAllowlist);
    }

    public function testUnknownProfileThrows(): void
    {
        $this->expectException(InvalidQrProfileException::class);

        $this->resolver()->resolve('missing');
    }

    public function testDatabaseProfileOverridesYamlByName(): void
    {
        $stored = (new QrCodeProfileConfig())
            ->setName('compact')
            ->setSize(200)
            ->setMargin(4)
            ->setErrorCorrection(QrErrorCorrection::Low->value)
            ->setUrlAllowlist(['db.example.com']);

        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->method('findOneByName')->willReturnCallback(
            static fn (string $name): ?QrCodeProfileConfig => $name === 'compact' ? $stored : null,
        );
        $repo->method('findAllNames')->willReturn(['compact', 'from_db']);

        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', true, $repo);
        $profile  = $resolver->resolve('compact');

        self::assertSame(200, $profile->size);
        self::assertSame(QrErrorCorrection::Low, $profile->errorCorrection);
        self::assertSame(['db.example.com'], $profile->urlAllowlist);
        self::assertSame(['compact', 'default', 'from_db'], $resolver->getProfileNames());
    }

    public function testDatabaseOnlyProfileIsResolvable(): void
    {
        $stored = (new QrCodeProfileConfig())
            ->setName('from_db')
            ->setSize(256)
            ->setMargin(8)
            ->setErrorCorrection(QrErrorCorrection::Medium->value)
            ->setUrlAllowlist([]);

        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->method('findOneByName')->willReturnCallback(
            static fn (string $name): ?QrCodeProfileConfig => $name === 'from_db' ? $stored : null,
        );
        $repo->method('findAllNames')->willReturn(['from_db']);

        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', true, $repo);
        $profile  = $resolver->resolve('from_db');

        self::assertSame('from_db', $profile->name);
        self::assertSame(256, $profile->size);
    }

    public function testDatabaseDisabledIgnoresRepository(): void
    {
        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->expects(self::never())->method('findOneByName');

        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', false, $repo);
        $profile  = $resolver->resolve('compact');

        self::assertSame(128, $profile->size);
        self::assertFalse($resolver->usesDatabaseConfig());
    }

    public function testUsesDatabaseConfigFlag(): void
    {
        self::assertTrue(
            (new ProfileResolver($this->yamlProfiles(), 'default', true))->usesDatabaseConfig(),
        );
    }

    /**
     * @return array<string, array{size: int, margin: int, error_correction: string, url_allowlist: list<string>}>
     */
    private function yamlProfiles(): array
    {
        return [
            'default' => [
                'size'             => 300,
                'margin'           => 10,
                'error_correction' => 'high',
                'url_allowlist'    => [],
            ],
            'compact' => [
                'size'             => 128,
                'margin'           => 2,
                'error_correction' => 'medium',
                'url_allowlist'    => ['example.com'],
            ],
        ];
    }

    private function resolver(): ProfileResolver
    {
        return new ProfileResolver($this->yamlProfiles(), 'default');
    }
}
