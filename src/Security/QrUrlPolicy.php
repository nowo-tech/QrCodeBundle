<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Security;

use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;

use function explode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function rawurldecode;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_PORT;

/**
 * Validates URLs encoded into QR codes (http/https only; blocks javascript/data schemes).
 *
 * Allowlist patterns:
 * - Empty list: any http(s) URL with a host is allowed.
 * - Pattern starting with `#`: PCRE applied to host, then full URL.
 * - Pattern containing `/`: `[scheme://]host[:port]/path-prefix` — the URL is parsed; the host must match exactly
 *   (or be a subdomain, as below), the port/scheme must match when given, and the URL path must start with the
 *   pattern path. A pattern with an empty host (e.g. `/wallet/`) matches that path prefix on any host.
 *   Paths containing dot segments (`/../`, `/./`, also percent-encoded) never match.
 * - Otherwise: host match — exact host or a subdomain of the pattern (not a raw substring).
 */
final readonly class QrUrlPolicy
{
    /**
     * @param list<string> $hostAllowlist Host patterns, `[scheme://]host[:port]/path-prefix` patterns, or `#regex`.
     *                                    Empty = any http(s) URL with a non-empty host (no public/private host or IP check is performed).
     */
    public function __construct(
        private array $hostAllowlist = [],
    ) {
    }

    /**
     * @throws InvalidQrUrlException when the URL is rejected by this policy
     */
    public function assertAllowed(string $url): void
    {
        if (!$this->isAllowed($url)) {
            throw new InvalidQrUrlException(sprintf('URL is not allowed for QR encoding: %s', $url));
        }
    }

    public function isAllowed(string $url): bool
    {
        $scheme = $this->extractScheme($url);
        if ($scheme === null || !in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        return $this->isAllowedHost($host, $url);
    }

    private function isAllowedHost(string $host, string $url): bool
    {
        if ($this->hostAllowlist === []) {
            return true;
        }

        $host = strtolower($host);

        foreach ($this->hostAllowlist as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '#')) {
                if (preg_match($pattern, $host) === 1 || preg_match($pattern, $url) === 1) {
                    return true;
                }
                continue;
            }
            if (str_contains($pattern, '/')) {
                if ($this->urlMatchesPathPattern($host, $url, $pattern)) {
                    return true;
                }
                continue;
            }
            if ($this->hostMatchesPattern($host, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Host (+ optional scheme/port) and path-prefix match on the parsed URL — never a substring of the raw URL,
     * so `https://evil.tld/?r=good.com/path` does not match `good.com/path`.
     */
    private function urlMatchesPathPattern(string $host, string $url, string $pattern): bool
    {
        $patternScheme = null;
        if (str_contains($pattern, '://')) {
            // Only the scheme (and the host, below) are case-insensitive: the path prefix keeps its case.
            [$patternScheme, $pattern] = explode('://', $pattern, 2);
            $patternScheme             = strtolower($patternScheme);
        }

        [$authority, $patternPath] = explode('/', $pattern, 2);
        $patternPath               = '/' . $patternPath;

        if ($patternScheme !== null && $patternScheme !== $this->extractScheme($url)) {
            return false;
        }

        if ($authority !== '') {
            $patternParts = parse_url('//' . strtolower($authority));
            $patternHost  = is_array($patternParts) ? ($patternParts['host'] ?? '') : '';
            if ($patternHost === '' || !$this->hostMatchesPattern($host, $patternHost)) {
                return false;
            }
            if (isset($patternParts['port']) && $patternParts['port'] !== parse_url($url, PHP_URL_PORT)) {
                return false;
            }
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        if ($this->hasDotSegment($path)) {
            return false;
        }

        return str_starts_with($path, $patternPath);
    }

    private function hasDotSegment(string $path): bool
    {
        return preg_match('#(^|/)\.{1,2}(/|$)#', rawurldecode($path)) === 1;
    }

    /**
     * Exact host or subdomain of the pattern (e.g. example.com matches cdn.example.com, not evil-example.com).
     */
    private function hostMatchesPattern(string $host, string $pattern): bool
    {
        return $host === $pattern || str_ends_with($host, '.' . $pattern);
    }

    private function extractScheme(string $url): ?string
    {
        if (!preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }
}
