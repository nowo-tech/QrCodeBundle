<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Security;

use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use Nowo\QrCodeBundle\Security\QrUrlPolicy;
use PHPUnit\Framework\TestCase;

final class QrUrlPolicyTest extends TestCase
{
    public function testAllowsHttpsUrl(): void
    {
        $policy = new QrUrlPolicy();

        self::assertTrue($policy->isAllowed('https://example.com/save'));
    }

    public function testAllowsHttpUrl(): void
    {
        $policy = new QrUrlPolicy();

        self::assertTrue($policy->isAllowed('http://example.com/save'));
    }

    public function testBlocksJavascriptUrl(): void
    {
        $policy = new QrUrlPolicy();

        self::assertFalse($policy->isAllowed('javascript:alert(1)'));
    }

    public function testBlocksDataUrl(): void
    {
        $policy = new QrUrlPolicy();

        self::assertFalse($policy->isAllowed('data:text/html,<script>alert(1)</script>'));
    }

    public function testBlocksUrlWithoutScheme(): void
    {
        $policy = new QrUrlPolicy();

        self::assertFalse($policy->isAllowed('example.com/save'));
    }

    public function testBlocksUrlWithoutHost(): void
    {
        $policy = new QrUrlPolicy();

        self::assertFalse($policy->isAllowed('https:///path'));
    }

    public function testHostAllowlist(): void
    {
        $policy = new QrUrlPolicy(['example.com']);

        self::assertTrue($policy->isAllowed('https://example.com/path'));
        self::assertTrue($policy->isAllowed('https://cdn.example.com/path'));
        self::assertFalse($policy->isAllowed('https://evil.com/path'));
        self::assertFalse($policy->isAllowed('https://evil-example.com/path'));
    }

    public function testHostAllowlistMatchesUrlPathSubstring(): void
    {
        $policy = new QrUrlPolicy(['/wallet/']);

        self::assertTrue($policy->isAllowed('https://cdn.example.com/wallet/pass.pkpass'));
        self::assertFalse($policy->isAllowed('https://cdn.example.com/other/pass.pkpass'));
    }

    public function testHostAllowlistRegexOnHost(): void
    {
        $policy = new QrUrlPolicy(['#^cdn\\.example\\.com$#']);

        self::assertTrue($policy->isAllowed('https://cdn.example.com/pass.pkpass'));
        self::assertFalse($policy->isAllowed('https://evil.example.com/pass.pkpass'));
    }

    public function testHostAllowlistRegexOnUrl(): void
    {
        $policy = new QrUrlPolicy(['#^https://cdn\\.example\\.com/#']);

        self::assertTrue($policy->isAllowed('https://cdn.example.com/pass.pkpass'));
        self::assertFalse($policy->isAllowed('https://other.example.com/pass.pkpass'));
    }

    public function testEmptyPatternIsSkippedInAllowlist(): void
    {
        $policy = new QrUrlPolicy(['', 'example.com']);

        self::assertTrue($policy->isAllowed('https://example.com/path'));
        self::assertFalse($policy->isAllowed('https://evil.com/path'));
    }

    public function testAssertAllowedThrows(): void
    {
        $policy = new QrUrlPolicy();

        $this->expectException(InvalidQrUrlException::class);
        $policy->assertAllowed('javascript:alert(1)');
    }

    public function testAssertAllowedAcceptsValidUrl(): void
    {
        $policy = new QrUrlPolicy();

        $policy->assertAllowed('https://example.com/save');

        $this->addToAssertionCount(1);
    }
}
