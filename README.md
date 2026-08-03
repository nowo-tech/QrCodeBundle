# QR Code Bundle

[![CI](https://github.com/nowo-tech/QrCodeBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/QrCodeBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/qr-code-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/qr-code-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/qr-code-bundle.svg)](https://packagist.org/packages/nowo-tech/qr-code-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/qr-code-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/QrCodeBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/qr-code-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/QrCodeBundle) so more developers can find it.

**Symfony bundle to generate PNG QR codes as data URIs**, with optional URL validation (`http`/`https` only) and host allowlisting.

Used by [WalletQrBundle](https://github.com/nowo-tech/WalletQrBundle) for wallet save-link QRs, and usable standalone in any Symfony app.

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

### FrankenPHP demo

```bash
make -C demo/symfony8 up
# http://localhost:8012/demo
```

See [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md) (`FRANKENPHP_MODE=worker` default; PHP **8.5** + **gd**).

## Features

- ✅ PNG QR codes as data URIs (`QrCodeService` / `QrCodeDataUriRenderer`)
- ✅ Named profiles (`default_profile` + `profiles`) for size, ECC, and allowlists
- ✅ Optional Doctrine storage: DB profiles override YAML by name (`use_database_config`)
- ✅ Admin CRUD at `/admin/qr-code-profiles` (when DB storage is enabled)
- ✅ URL safety policy (`QrUrlPolicy`; blocks `javascript:`, `data:`, etc.)
- ✅ Optional host/URL allowlist (exact host or subdomain; path substrings; `#regex`)
- ✅ Typed `QrErrorCorrection` enum (`low` | `medium` | `quartile` | `high`)
- ✅ Twig helpers `qr_code_data_uri` and `qr_code_for_url` (optional profile argument)
- ✅ Twig UX component `<twig:NowoQrCode>` (`symfony/ux-twig-component`)
- ✅ Compatible with Symfony UX Toolkit (optional host dependency, PHP 8.4+)
- ✅ Symfony configuration under `nowo_qr_code`

## Quick start

```bash
composer require nowo-tech/qr-code-bundle
```

Requires PHP **gd** (or another writer backend supported by `endroid/qr-code`).

```yaml
# config/packages/nowo_qr_code.yaml
nowo_qr_code:
    default_profile: default
    profiles:
        default:
            size: 300
            margin: 10
            error_correction: high
            url_allowlist: []
        compact:
            size: 128
            margin: 2
            error_correction: medium
            url_allowlist:
                - example.com
```

```php
use Nowo\QrCodeBundle\Service\QrCodeService;

$dataUri = $qrCodeService->createDataUri('https://example.com');
$safeUri = $qrCodeService->createDataUriForUrl('https://example.com/path', 'compact');
```

```twig
<img src="{{ qr_code_data_uri('Hello') }}" alt="QR">
<img src="{{ qr_code_for_url(downloadUrl, 'compact') }}" alt="QR">
<twig:NowoQrCode content="Hello" class="qr" />
<twig:NowoQrCode url="{{ downloadUrl }}" profile="compact" />
```

## Related

- [WalletQrBundle](https://github.com/nowo-tech/WalletQrBundle) — Google Wallet / Apple Wallet save links + QR (depends on this bundle)

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [FrankenPHP demos](docs/DEMO-FRANKENPHP.md)
- [GitHub CI](docs/GITHUB_CI.md)

## Tests and coverage

| Stack | Coverage | How to run |
| --- | --- | --- |
| PHP | **100%** lines (`src/`) | `make test-coverage` / `composer test-coverage` |
| TypeScript / JavaScript | N/A (no frontend assets) | — |
| Python | N/A | — |

CI enforces PHP coverage at **100%** (Clover elements / line gate via `.scripts/coverage-check-100.php`).

## License

MIT © [Nowo.tech](https://nowo.tech)
