<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Unit\Security;

use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use Nowo\QrCodeBundle\Security\QrUrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testHostPathPatternRejectsQueryStringBypass(): void
    {
        $policy = new QrUrlPolicy(['good.com/path']);

        self::assertTrue($policy->isAllowed('https://good.com/path/pass.pkpass'));
        self::assertTrue($policy->isAllowed('https://cdn.good.com/path'));
        self::assertFalse($policy->isAllowed('https://evil.tld/?r=good.com/path'));
        self::assertFalse($policy->isAllowed('https://evil.tld/good.com/path'));
        self::assertFalse($policy->isAllowed('https://evil.tld/#good.com/path'));
        self::assertFalse($policy->isAllowed('https://good.com.evil.tld/path'));
        self::assertFalse($policy->isAllowed('https://good.com@evil.tld/path'));
        self::assertFalse($policy->isAllowed('https://good.com/other/path'));
        self::assertFalse($policy->isAllowed('https://good.com/path/../admin'));
        self::assertFalse($policy->isAllowed('https://good.com/path/%2e%2e/admin'));
    }

    public function testPathOnlyPatternIsPrefixOnParsedPath(): void
    {
        $policy = new QrUrlPolicy(['/wallet/']);

        self::assertFalse($policy->isAllowed('https://evil.tld/?next=/wallet/'));
        self::assertFalse($policy->isAllowed('https://evil.tld/x/wallet/'));
        self::assertFalse($policy->isAllowed('https://evil.tld'));
    }

    public function testSchemeAndPortInPathPattern(): void
    {
        $policy = new QrUrlPolicy(['https://good.com:8443/app/']);

        self::assertTrue($policy->isAllowed('https://good.com:8443/app/x'));
        self::assertFalse($policy->isAllowed('http://good.com:8443/app/x'));
        self::assertFalse($policy->isAllowed('https://good.com/app/x'));
        self::assertFalse($policy->isAllowed('https://evil.tld:8443/app/x'));
    }

    public function testPathPatternKeepsPathCaseWithAndWithoutScheme(): void
    {
        foreach (['HTTPS://Good.com/Docs/', 'Good.com/Docs/'] as $pattern) {
            $policy = new QrUrlPolicy([$pattern]);
            self::assertTrue($policy->isAllowed('https://good.com/Docs/file.pdf'), $pattern);
            self::assertFalse($policy->isAllowed('https://good.com/docs/file.pdf'), $pattern);
        }
    }

    public function testMalformedHostInPathPatternNeverMatches(): void
    {
        $policy = new QrUrlPolicy([':/app/']);

        self::assertFalse($policy->isAllowed('https://good.com/app/x'));
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

    /**
     * PHP parse_url() and phones / browsers read these authorities differently: they open evil.tld.
     *
     * @return iterable<string, array{string}>
     */
    public static function ambiguousAuthorities(): iterable
    {
        yield 'backslash before allowed suffix' => ['https://evil.tld\\.good.com/'];
        yield 'backslash userinfo' => ['https://evil.tld\\@good.com/'];
        yield 'userinfo' => ['https://evil.tld@good.com/'];
        yield 'user and password' => ['https://u:p@good.com/'];
        yield 'tab in authority' => ["https://good.com\t.evil.tld/"];
        yield 'space' => ['https://good.com /x'];
        yield 'percent-encoded host' => ['https://evil.tld%2egood.com/'];
        yield 'newline' => ["https://good.com/\nx"];
        yield 'DEL' => ["https://good.com/\x7Fx"];
    }

    #[DataProvider('ambiguousAuthorities')]
    public function testAmbiguousAuthoritiesAreNeverAllowed(string $url): void
    {
        self::assertFalse((new QrUrlPolicy(['good.com']))->isAllowed($url));
        self::assertFalse((new QrUrlPolicy())->isAllowed($url), 'also without an allowlist');
    }

    public function testPlainHostsAndIpLiteralsStillPass(): void
    {
        $policy = new QrUrlPolicy();

        self::assertTrue($policy->isAllowed('https://sub.good.com:8443/p?q=a%20b#f'));
        self::assertTrue($policy->isAllowed('https://192.0.2.1/x'));
        self::assertTrue($policy->isAllowed('https://[2001:db8::1]/x'));
        self::assertTrue($policy->isAllowed('https://xn--bcher-kva.de/'));
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
