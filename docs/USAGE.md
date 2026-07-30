# Usage

## Service

```php
use Nowo\QrCodeBundle\Service\QrCodeService;

final class TicketController
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {
    }

    public function qr(): string
    {
        // Default profile
        return $this->qrCodeService->createDataUri('EVENT-42');

        // Named profile
        // return $this->qrCodeService->createDataUri('EVENT-42', 'compact');

        // http(s) only — throws InvalidQrUrlException otherwise
        // return $this->qrCodeService->createDataUriForUrl('https://example.com/pass', 'wallet');
    }
}
```

Omit the profile argument (or pass `null`) to use `default_profile`. An unknown profile name throws `InvalidQrProfileException`.

## Low-level renderer

```php
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;
use Nowo\QrCodeBundle\QrCode\QrCodeDataUriRenderer;

$renderer = new QrCodeDataUriRenderer(
    size: 256,
    margin: 4,
    errorCorrection: QrErrorCorrection::Medium, // or 'medium'
);
$dataUri = $renderer->renderDataUri('payload');
```

## Twig

```twig
<img src="{{ qr_code_data_uri('Hello') }}" alt="QR">
<img src="{{ qr_code_data_uri('Hello', 'compact') }}" alt="QR">
<img src="{{ qr_code_for_url(passDownloadUrl) }}" alt="QR">
<img src="{{ qr_code_for_url(passDownloadUrl, 'wallet') }}" alt="QR">
```

`qr_code_for_url` applies `QrUrlPolicy` for the selected profile (same as `createDataUriForUrl`). Only `http`/`https` URLs with a host are accepted; optional `url_allowlist` further restricts hosts/paths.

See [CONFIGURATION.md](CONFIGURATION.md) for the profiles shape.
