<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Twig;

use Nowo\QrCodeBundle\Service\QrCodeService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers to render QR codes in templates (optional profile argument).
 */
final class QrCodeExtension extends AbstractExtension
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('qr_code_data_uri', $this->qrCodeDataUri(...)),
            new TwigFunction('qr_code_for_url', $this->qrCodeForUrl(...)),
        ];
    }

    public function qrCodeDataUri(string $content, ?string $profile = null): string
    {
        return $this->qrCodeService->createDataUri($content, $profile);
    }

    public function qrCodeForUrl(string $url, ?string $profile = null): string
    {
        return $this->qrCodeService->createDataUriForUrl($url, $profile);
    }
}
