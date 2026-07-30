<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Config;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\Exception\InvalidQrProfileException;
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
        self::assertSame(['default', 'compact'], $resolver->getProfileNames());
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

    private function resolver(): ProfileResolver
    {
        return new ProfileResolver(
            [
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
            ],
            'default',
        );
    }
}
