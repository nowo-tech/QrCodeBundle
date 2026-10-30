<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Security;

use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Classifies and validates `url_allowlist` patterns (YAML profiles and the admin form share these rules).
 *
 * - `#…#`    host regex: PCRE applied to the parsed host only.
 * - `url#…#` full-URL regex (explicit opt-in): PCRE applied to the whole URL; it must be anchored at the start
 *            (`^` or `\A` right after the delimiter) so `https://evil.tld/?x=good.com` cannot match a substring.
 */
final class UrlAllowlistPattern
{
    public const URL_REGEX_PREFIX = 'url#';

    public static function isHostRegex(string $pattern): bool
    {
        return str_starts_with($pattern, '#');
    }

    public static function isUrlRegex(string $pattern): bool
    {
        return str_starts_with($pattern, self::URL_REGEX_PREFIX);
    }

    /**
     * The PCRE of a `url#…#` pattern (the `url` prefix stripped), or null for any other pattern.
     */
    public static function urlRegex(string $pattern): ?string
    {
        return self::isUrlRegex($pattern) ? substr($pattern, 3) : null;
    }

    /**
     * Whether a full-URL regex is anchored at the start of the URL (`#^…` or `#\A…`).
     */
    public static function isAnchoredUrlRegex(string $regex): bool
    {
        return preg_match('/^#(?:\^|\\\\A)/', $regex) === 1;
    }

    /**
     * Returns a human-readable error for an invalid pattern, or null when it is valid (or not a regex pattern).
     */
    public static function validate(string $pattern): ?string
    {
        if (self::isHostRegex($pattern)) {
            if (str_contains($pattern, '/')) {
                return sprintf('Regex pattern "%s" contains "/": #…# regex patterns match the host only. Use a path pattern (host/path-prefix) or an anchored full-URL regex (url#^https://…#).', $pattern);
            }

            return self::validateRegex($pattern);
        }

        $regex = self::urlRegex($pattern);
        if ($regex === null) {
            return null;
        }
        if (!self::isAnchoredUrlRegex($regex)) {
            return sprintf('Full-URL regex pattern "%s" must use the # delimiter and be anchored at the start (url#^https://host/…#).', $pattern);
        }

        return self::validateRegex($regex);
    }

    private static function validateRegex(string $regex): ?string
    {
        // Regex patterns run on every QR render: reject invalid ones and catastrophic backtracking (nested quantifiers).
        if (@preg_match($regex, '') === false || preg_match('/\((?:[^()\\\\]|\\\\.)*[+*}]\)[+*{]/', $regex) === 1) {
            return sprintf('Invalid regex pattern: %s', $regex);
        }

        return null;
    }
}
