<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Exception;

use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use PHPUnit\Framework\TestCase;

final class InvalidQrUrlExceptionTest extends TestCase
{
    public function testMessageIsPreserved(): void
    {
        $exception = new InvalidQrUrlException('bad url');

        $this->assertSame('bad url', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }
}
