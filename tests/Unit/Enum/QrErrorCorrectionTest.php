<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Enum;

use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use PHPUnit\Framework\TestCase;

final class QrErrorCorrectionTest extends TestCase
{
    public function testCasesHaveExpectedValues(): void
    {
        self::assertSame('low', QrErrorCorrection::Low->value);
        self::assertSame('medium', QrErrorCorrection::Medium->value);
        self::assertSame('quartile', QrErrorCorrection::Quartile->value);
        self::assertSame('high', QrErrorCorrection::High->value);
        self::assertCount(4, QrErrorCorrection::cases());
    }
}
