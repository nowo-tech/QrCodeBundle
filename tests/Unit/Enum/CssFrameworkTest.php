<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Enum;

use Nowo\QrCodeBundle\Enum\CssFramework;
use PHPUnit\Framework\TestCase;

final class CssFrameworkTest extends TestCase
{
    public function testValuesIncludeCanonicalSet(): void
    {
        $values = CssFramework::values();

        self::assertContains('bootstrap', $values);
        self::assertContains('bootstrap4', $values);
        self::assertContains('bootstrap5', $values);
        self::assertContains('tabler', $values);
        self::assertContains('tailwind', $values);
        self::assertContains('foundation', $values);
        self::assertContains('custom', $values);
        self::assertContains('none', $values);
    }

    public function testBootstrapNormalizesToBootstrap5(): void
    {
        self::assertSame(CssFramework::Bootstrap5, CssFramework::Bootstrap->normalized());
        self::assertSame(CssFramework::Custom, CssFramework::Custom->normalized());
    }
}
