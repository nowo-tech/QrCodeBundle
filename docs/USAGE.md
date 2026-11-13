# Usage

## Table of contents

- [Service](#service)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
- [Database profiles (optional)](#database-profiles-optional)
- [Translations](#translations)
- [Low-level renderer](#low-level-renderer)
- [Twig](#twig)
  - [Functions](#functions)
  - [Twig Component (`NowoQrCode`)](#twig-component-nowoqrcode)
  - [Symfony UX Toolkit](#symfony-ux-toolkit)

## Service

```php
use Nowo\QrCodeBundle\Service\QrCodeServiceInterface;

final class TicketController
{
    public function __construct(
        private readonly QrCodeServiceInterface $qrCodeService,
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

`QrCodeServiceInterface` is aliased to the shipped `QrCodeService` (the class id still works). Type-hint the interface so a test double or a replacement can be plugged by overriding the alias.

Omit the profile argument (or pass `null`) to use `default_profile`. An unknown profile name throws `InvalidQrProfileException`.

## Using it from a DDD / hexagonal application

QrCodeBundle is a **presentation / infrastructure** component: it turns a string into a PNG data URI. It has no
relation to your entities; its only table (`qr_code_profile`) is optional and holds rendering profiles.

**Two ways to configure the profiles**

- **Standard path (default).** YAML `profiles`, optionally overridden by database rows edited in the admin UI
  (`use_database_config: true`, Doctrine ORM).
- **DDD path (opt-in, 1.13).** Your own `Nowo\QrCodeBundle\Config\QrCodeProfileProviderInterface` service as
  `profile_provider`: profiles from your settings store or per tenant, without Doctrine (DoctrineBundle need not be
  registered). It is the only source and fails closed: a name it returns `null` for throws (no YAML fallback), and
  sizes / margins outside 64–1024 / 0–64 are refused. Throw on lookup errors rather than returning `null` for a tenant
  you do know.

```php
namespace App\Infrastructure\Qr;

use Nowo\QrCodeBundle\Config\QrCodeProfile;
use Nowo\QrCodeBundle\Config\QrCodeProfileProviderInterface;
use Nowo\QrCodeBundle\Enum\QrErrorCorrection;

final readonly class TenantQrProfiles implements QrCodeProfileProviderInterface
{
    public function __construct(private TenantSettings $settings) {} // your port

    public function find(string $name): ?QrCodeProfile
    {
        $qr = $this->settings->current()->qr($name); // resolved per call: no state between requests

        return $qr === null ? null : new QrCodeProfile($name, $qr->size, $qr->margin, QrErrorCorrection::Medium, $qr->allowedHosts);
    }

    public function names(): array
    {
        return $this->settings->current()->qrProfileNames();
    }
}
```

```yaml
# config/packages/nowo_qr_code.yaml
nowo_qr_code:
    profile_provider: App\Infrastructure\Qr\TenantQrProfiles   # exclusive with use_database_config
```

**Keep QR generation out of the domain**

- If an application service needs a QR image (ticket, pass, invoice), define your own port, for example
  `QrImageGenerator::forTicketUrl(string): string`, and implement it in infrastructure with `QrCodeServiceInterface`.
- Use `createDataUriForUrl()` for URLs so the profile's `url_allowlist` applies; catch `InvalidQrUrlException` and
  `InvalidQrProfileException` in the adapter.
- In templates, call `qr_code_data_uri()` / `qr_code_for_url()` or `<twig:NowoQrCode>` with values your view model
  already prepared; do not pass entities.
- Admin access is decided by your own `QrCodeAccessCheckerInterface` implementation (`security.access_checker`).

```php
namespace App\Infrastructure\Qr;

use App\Application\Ticket\QrImageGenerator;   // your port
use Nowo\QrCodeBundle\Service\QrCodeServiceInterface;

final readonly class BundleQrImageGenerator implements QrImageGenerator
{
    public function __construct(private QrCodeServiceInterface $qr) {}

    public function forTicketUrl(string $url): string
    {
        return $this->qr->createDataUriForUrl($url, 'wallet');
    }
}
```

**Known limitations**

- `QrCodeService` stays `final`; type-hint `QrCodeServiceInterface` (aliased) for test doubles and replacements. Wrapping it in your own port (as above) keeps your domain free of the bundle.
- Output is a PNG data URI only; decode it if you need raw bytes.
- No events are dispatched when database profiles change.
- With DoctrineBundle configured, `QrCodeProfileConfig` stays mapped even when `use_database_config` is off (BC: an
  unmapped table would show up as a DROP in schema diffs); 2.0 maps it only with `use_database_config`.

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
