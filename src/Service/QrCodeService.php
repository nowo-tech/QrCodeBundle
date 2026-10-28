<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Service;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Config\QrCodeProfile;
use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use Nowo\QrCodeBundle\QrCode\QrCodeDataUriRenderer;
use Nowo\QrCodeBundle\Security\QrUrlPolicy;

/**
 * High-level API to create PNG QR codes as data URIs (profile-aware).
 */
final readonly class QrCodeService
{
    /**
     * @param bool $urlAllowlistRequired `url_allowlist_required`: every profile used for URLs (YAML or database) must
     *                                   have a non-empty allowlist, checked when the QR code is created
     */
    public function __construct(
        private ProfileResolver $profileResolver,
        private bool $urlAllowlistRequired = false,
    ) {
    }

    /**
     * Encode arbitrary content (URL, text, etc.) as a PNG data URI.
     *
     * @param string|null $profile Named profile; null uses `default_profile`
     */
    public function createDataUri(string $content, ?string $profile = null): string
    {
        return $this->rendererFor($profile)->renderDataUri($content);
    }

    /**
     * Encode an http(s) URL as a PNG data URI after URL policy checks.
     *
     * @param string|null $profile Named profile; null uses `default_profile`
     */
    public function createDataUriForUrl(string $url, ?string $profile = null): string
    {
        $resolved = $this->profileResolver->resolve($profile);
        // The compile-time check only sees the YAML default profile: other YAML profiles and admin-edited database
        // profiles are checked here.
        if ($this->urlAllowlistRequired && $resolved->urlAllowlist === []) {
            throw new InvalidQrUrlException('URL QR codes require a URL allowlist on this profile (url_allowlist_required).');
        }
        $this->policyFor($resolved)->assertAllowed($url);

        return $this->rendererForProfile($resolved)->renderDataUri($url);
    }

    private function rendererFor(?string $profile): QrCodeDataUriRenderer
    {
        return $this->rendererForProfile($this->profileResolver->resolve($profile));
    }

    private function rendererForProfile(QrCodeProfile $profile): QrCodeDataUriRenderer
    {
        return new QrCodeDataUriRenderer(
            size: $profile->size,
            margin: $profile->margin,
            errorCorrection: $profile->errorCorrection,
        );
    }

    private function policyFor(QrCodeProfile $profile): QrUrlPolicy
    {
        return new QrUrlPolicy($profile->urlAllowlist);
    }
}
