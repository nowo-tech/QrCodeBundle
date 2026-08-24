<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Security;

use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;

use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

use const PHP_URL_HOST;

/**
 * Validates URLs encoded into QR codes (http/https only; blocks javascript/data schemes).
 *
 * Allowlist patterns:
 * - Empty list: any http(s) URL with a host is allowed.
 * - Pattern starting with `#`: PCRE applied to host, then full URL.
 * - Pattern containing `/`: substring match against the full URL (path allowlisting).
 * - Otherwise: host match — exact host or a subdomain of the pattern (not a raw substring).
 */
final readonly class QrUrlPolicy
{
    /**
     * @param list<string> $hostAllowlist Host patterns, URL path substrings, or `#regex`. Empty = any public http(s) host.
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
                if (str_contains($url, $pattern)) {
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
