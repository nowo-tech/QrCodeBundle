# Usage

## Table of contents

- [Service](#service)
- [Database profiles (optional)](#database-profiles-optional)
- [Translations](#translations)
- [Low-level renderer](#low-level-renderer)
- [Twig](#twig)
  - [Functions](#functions)
  - [Twig Component (`NowoQrCode`)](#twig-component-nowoqrcode)
  - [Symfony UX Toolkit](#symfony-ux-toolkit)

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

## Database profiles (optional)

When `use_database_config: true`, rows in `qr_code_profile` override YAML profiles that share the same **name**. See [CONFIGURATION.md](CONFIGURATION.md#database-overrides-optional).

Admin CRUD: `/admin/qr-code-profiles` (requires Doctrine + form component and route import). The pages follow the
UiKit page contract (list and form scaffolds in `nowo_ui_kit.layouts.admin`); deletes go through the UiKit confirm
`<dialog>`. Pages, data blocks and overrides: [TEMPLATES.md](TEMPLATES.md).

## Translations

The admin UI is translated in the **`NowoQrCodeBundle`** domain (REQ-I18N-003). Supported locales: **en** (reference),
**es**, **fr**, **it**, **pt**, **de**, **nl** (`src/Resources/translations/NowoQrCodeBundle.<locale>.yaml`, same keys
in every locale; `make validate-translations` checks syntax, the seven locales and key parity).

To override a text, create the same domain file in the application — the application catalogue wins and the bundle is
the fallback for keys you do not define (REQ-I18N-001):

1. Use the domain `NowoQrCodeBundle`.
2. Create `translations/NowoQrCodeBundle.<locale>.yaml` (e.g. `translations/NowoQrCodeBundle.es.yaml`).
3. Override only the keys you need, then `php bin/console cache:clear`.

```yaml
# translations/NowoQrCodeBundle.es.yaml
nowo_qr_code:
    admin:
        index:
            title: "Perfiles QR de la tienda"
        actions:
            delete: "Borrar"
```

Under FrankenPHP worker mode without kernel reset, DB-backed render and admin stay correct without Doctrine `kernel.reset` — see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

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

### Functions

```twig
<img src="{{ qr_code_data_uri('Hello') }}" alt="QR">
<img src="{{ qr_code_data_uri('Hello', 'compact') }}" alt="QR">
<img src="{{ qr_code_for_url(passDownloadUrl) }}" alt="QR">
<img src="{{ qr_code_for_url(passDownloadUrl, 'wallet') }}" alt="QR">
```

`qr_code_for_url` applies `QrUrlPolicy` for the selected profile (same as `createDataUriForUrl`).

### Twig Component (`NowoQrCode`)

Requires `symfony/ux-twig-component` (already a package dependency).

```twig
<twig:NowoQrCode content="EVENT-42" alt="Ticket QR" class="qr-img" />
<twig:NowoQrCode url="{{ downloadUrl }}" profile="compact" />
{{ component('NowoQrCode', { content: 'Hello', profile: 'default' }) }}
```

| Prop | Type | Description |
| --- | --- | --- |
| `content` | string | Arbitrary payload (no URL policy) |
| `url` | string | http(s) URL encoded via URL policy |
| `profile` | string\|null | Named profile; omit for `default_profile` |
| `alt` | string | `<img alt>` (default `QR code`) |
| `class` | string | Optional CSS classes |
| `forUrl` | bool | Force URL-policy path using `url` or `content` |

### Symfony UX Toolkit

[`symfony/ux-toolkit`](https://symfony.com/bundles/ux-toolkit/current/index.html) is **optional** (PHP **8.4+**, experimental). Install it in the host app to use Shadcn (or other) kit components alongside `NowoQrCode`:

```bash
composer require symfony/ux-toolkit
php bin/console ux:install button --kit=shadcn
```

`NowoQrCode` follows Toolkit-friendly `{# @prop #}` documentation in its Twig template so it fits the same design-system workflow. The toolkit does **not** replace this bundle’s component; it complements layout/UI primitives around the QR image.

See [CONFIGURATION.md](CONFIGURATION.md) for profiles and [Twig overrides](TEMPLATES.md#overriding-templates-req-twig-001).
