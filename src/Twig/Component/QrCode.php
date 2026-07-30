<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Twig\Component;

use InvalidArgumentException;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

use function sprintf;

/**
 * Twig UX component that renders a PNG QR code as an {@code <img>} data URI.
 *
 * Compatible with Symfony UX Twig Component and UX Toolkit prop documentation conventions.
 */
#[AsTwigComponent(
    name: 'NowoQrCode',
    template: '@NowoQrCodeBundle/components/qr_code.html.twig',
)]
final class QrCode
{
    public string $dataUri = '';

    public string $alt = 'QR code';

    public string $class = '';

    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {
    }

    /**
     * @param string|null $content Arbitrary payload (used when not encoding a policy-checked URL)
     * @param string|null $url http(s) URL to encode with {@see QrCodeService::createDataUriForUrl()}
     * @param string|null $profile Named profile; null uses {@code default_profile}
     * @param bool $forUrl Force URL policy path using {@code $url} or {@code $content}
     */
    public function mount(
        ?string $content = null,
        ?string $url = null,
        ?string $profile = null,
        string $alt = 'QR code',
        string $class = '',
        bool $forUrl = false,
    ): void {
        $this->alt   = $alt;
        $this->class = $class;

        if ($url !== null || $forUrl) {
            $target = $url ?? $content;
            if ($target === null || $target === '') {
                throw new InvalidArgumentException('NowoQrCode requires a non-empty url (or content with forUrl=true).');
            }
            $this->dataUri = $this->qrCodeService->createDataUriForUrl($target, $profile);

            return;
        }

        if ($content === null || $content === '') {
            throw new InvalidArgumentException(sprintf('NowoQrCode requires content=… or url=… (optional profile=%s).', $profile ?? 'default'));
        }

        $this->dataUri = $this->qrCodeService->createDataUri($content, $profile);
    }
}
