<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Config;

use Closure;
use Nowo\QrCodeBundle\Config\DoctrineQrCodeProfileProvider;
use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Config\QrCodeProfile;
use Nowo\QrCodeBundle\Config\QrCodeProfileProviderInterface;
use Nowo\QrCodeBundle\Entity\QrCodeProfileConfig;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Exception\InvalidQrProfileException;
use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository;
use Nowo\QrCodeBundle\Service\QrCodeService;
use PHPUnit\Framework\Attributes\DataProvider;
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
            ->setUrlAllowlist(['db.example.com'])
            ->toProfileArray();

        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->method('findProfileArrayByName')->willReturnCallback(
            static fn (string $name): ?array => $name === 'compact' ? $stored : null,
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
            ->setUrlAllowlist([])
            ->toProfileArray();

        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->method('findProfileArrayByName')->willReturnCallback(
            static fn (string $name): ?array => $name === 'from_db' ? $stored : null,
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
        $repo->expects(self::never())->method('findProfileArrayByName');

        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', false, $repo);
        $profile  = $resolver->resolve('compact');

        self::assertSame(128, $profile->size);
        self::assertFalse($resolver->usesDatabaseConfig());
    }

    public function testConsecutiveRequestsWithoutResetSeeTightenedAllowlist(): void
    {
        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->expects(self::exactly(3))->method('findProfileArrayByName')->with('compact')->willReturnOnConsecutiveCalls(
            ['size' => 200, 'margin' => 4, 'error_correction' => 'low', 'url_allowlist' => ['example.com', 'evil.com']],
            ['size' => 240, 'margin' => 4, 'error_correction' => 'low', 'url_allowlist' => ['example.com']],
            null,
        );

        $service = new QrCodeService(new ProfileResolver($this->yamlProfiles(), 'default', true, $repo));

        // Request 1: the stored allowlist still accepts evil.com.
        self::assertStringStartsWith('data:image/png;base64,', $service->createDataUriForUrl('https://evil.com/x', 'compact'));

        // Request 2 on the same service instances (no reset): the admin tightened the allowlist in another worker.
        try {
            $service->createDataUriForUrl('https://evil.com/x', 'compact');
            self::fail('The tightened allowlist must be enforced on the next request.');
        } catch (InvalidQrUrlException) {
        }

        // Request 3: the row was deleted, the YAML profile applies again.
        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', true, $repo);
        self::assertSame(128, $resolver->resolve('compact')->size);
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

    public function testHostProviderOverridesYamlAndFallsBack(): void
    {
        $provider = new class implements QrCodeProfileProviderInterface {
            public function find(string $name): ?QrCodeProfile
            {
                return $name === 'compact' || $name === 'tenant'
                    ? new QrCodeProfile($name, 99, 1, QrErrorCorrection::Quartile, ['tenant.example'])
                    : null;
            }

            public function names(): array
            {
                return ['tenant', 'compact'];
            }
        };
        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', false, null, $provider);

        self::assertSame(99, $resolver->resolve('compact')->size);
        self::assertSame(['tenant.example'], $resolver->resolve('tenant')->urlAllowlist);
        self::assertSame(300, $resolver->resolve()->size, 'unknown to the provider: YAML');
        self::assertSame(['compact', 'default', 'tenant'], $resolver->getProfileNames());
        self::assertFalse($resolver->usesDatabaseConfig());

        $this->expectException(InvalidQrProfileException::class);
        $this->expectExceptionMessage('Profile "missing" is not defined in nowo_qr_code.profiles or the profile provider.');
        $resolver->resolve('missing');
    }

    public function testHostProviderWithoutYamlFallbackFailsClosed(): void
    {
        $provider = $this->provider(static fn (string $name): ?QrCodeProfile => $name === 'tenant'
            ? new QrCodeProfile('tenant', 200, 2, QrErrorCorrection::Medium, ['tenant.example'])
            : null);
        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', false, null, $provider, false);

        self::assertSame(200, $resolver->resolve('tenant')->size);
        self::assertSame(['tenant'], $resolver->getProfileNames(), 'YAML names are not reachable');

        $this->expectException(InvalidQrProfileException::class);
        $this->expectExceptionMessage('Profile "default" is not defined by the profile provider');
        $resolver->resolve();
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'huge size' => [20000, 4];
        yield 'tiny size' => [20, 4];
        yield 'negative margin' => [300, -1];
        yield 'huge margin' => [300, 65];
    }

    #[DataProvider('outOfBounds')]
    public function testProviderProfilesAreCheckedAgainstTheYamlBounds(int $size, int $margin): void
    {
        $provider = $this->provider(static fn (string $name): QrCodeProfile => new QrCodeProfile($name, $size, $margin, QrErrorCorrection::Medium, []));
        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', false, null, $provider, false);

        $this->expectException(InvalidQrProfileException::class);
        $this->expectExceptionMessage('allowed: size 64-1024, margin 0-64');
        $resolver->resolve('tenant');
    }

    public function testDoctrineProviderReadsTheRepository(): void
    {
        $repo = $this->createMock(QrCodeProfileConfigRepository::class);
        $repo->method('findProfileArrayByName')->willReturnCallback(
            static fn (string $name): ?array => $name === 'db' ? ['size' => 150, 'margin' => 3, 'error_correction' => 'low', 'url_allowlist' => []] : null,
        );
        $repo->method('findAllNames')->willReturn(['db']);
        $resolver = new ProfileResolver($this->yamlProfiles(), 'default', true, null, new DoctrineQrCodeProfileProvider($repo));

        self::assertSame(150, $resolver->resolve('db')->size);
        self::assertSame(['compact', 'db', 'default'], $resolver->getProfileNames());

        $this->expectExceptionMessage('or the database.');
        $resolver->resolve('missing');
    }

    /**
     * @param Closure(string): ?QrCodeProfile $find
     */
    private function provider(Closure $find): QrCodeProfileProviderInterface
    {
        return new class($find) implements QrCodeProfileProviderInterface {
            public function __construct(private readonly Closure $find)
            {
            }

            public function find(string $name): ?QrCodeProfile
            {
                return ($this->find)($name);
            }

            public function names(): array
            {
                return ['tenant'];
            }
        };
    }

    private function resolver(): ProfileResolver
    {
        return new ProfileResolver($this->yamlProfiles(), 'default');
    }
}
