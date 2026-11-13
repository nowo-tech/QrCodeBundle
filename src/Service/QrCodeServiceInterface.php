<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Service;

use Nowo\QrCodeBundle\Exception\InvalidQrProfileException;
use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;

/**
 * Port: renders QR codes as PNG data URIs.
 *
 * The bundle ships {@see QrCodeService} and aliases this interface to it. Type-hint the interface in your application
 * or infrastructure code (test doubles, decoration); a host replaces the alias to render QR codes differently. The
 * Twig functions and the NowoQrCode Twig component depend on the interface too.
 */
interface QrCodeServiceInterface
{
    /**
     * Renders arbitrary text as a PNG data URI.
     *
     * @param string $content Text to encode
     * @param string|null $profile Named profile; null uses `default_profile`
     *
     * @throws InvalidQrProfileException When the profile does not exist
     *
     * @return string `data:image/png;base64,...`
     */
    public function createDataUri(string $content, ?string $profile = null): string;

    /**
     * Renders an http(s) URL as a PNG data URI, enforcing the profile's `url_allowlist`.
     *
     * @param string $url http(s) URL to encode
     * @param string|null $profile Named profile; null uses `default_profile`
     *
     * @throws InvalidQrProfileException When the profile does not exist
     * @throws InvalidQrUrlException When the URL is not allowed by the profile
     *
     * @return string `data:image/png;base64,...`
     */
    public function createDataUriForUrl(string $url, ?string $profile = null): string;
}
