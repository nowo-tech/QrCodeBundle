<?php

declare(strict_types=1);

namespace Nowo\QrCodeBundle\Tests\Support\Host;

use Nowo\QrCodeBundle\Exception\InvalidQrUrlException;
use Nowo\QrCodeBundle\Service\QrCodeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function strlen;

/**
 * A host page rendering a URL QR code for `/qr/{tenant}?url=…`: answers the PNG width, or 422 when the tenant's
 * profile refuses the URL.
 */
final readonly class HostQrController
{
    public function __construct(
        private QrCodeService $qrCodes,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $dataUri = $this->qrCodes->createDataUriForUrl((string) $request->query->get('url'));
        } catch (InvalidQrUrlException) {
            return new JsonResponse(['refused' => true], 422);
        }
        $size = getimagesizefromstring((string) base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true));

        return new JsonResponse(['width' => $size === false ? 0 : $size[0]]);
    }
}
