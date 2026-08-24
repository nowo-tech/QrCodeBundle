<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Service;

use Nowo\QrCodeBundle\Config\ProfileResolver;
use Nowo\QrCodeBundle\Config\QrCodeProfile;
use Nowo\QrCodeBundle\QrCode\QrCodeDataUriRenderer;
use Nowo\QrCodeBundle\Security\QrUrlPolicy;

/**
 * High-level API to create PNG QR codes as data URIs (profile-aware).
 */
final readonly class QrCodeService
{
    public function __construct(
        private ProfileResolver $profileResolver,
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
