# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-08-03

### Added

- Admin Web UI look-and-feel (REQ-UI-001): `web_ui.css_framework` (`bootstrap5` / alias `bootstrap`, `bootstrap4`, `tabler`, `tailwind`, `foundation`, `custom`, `none`) and Twig global `nowo_qr_code_css_framework`.
- `admin/base.html.twig` page shell that extends `web_ui.layout_template` and stacks `nowo_ui_styles` / `nowo_ui_scripts` with `{{ parent() }}`.
- Semantic `nowo-ui-*` hooks on admin markup (alongside legacy `qr-*` classes).
- Symfony **8** FrankenPHP demo under `demo/symfony8` (PHP **8.5** + **gd**, `FRANKENPHP_MODE`, Twig helpers / UX component / `QrCodeService` samples).
- `docs/DEMO-FRANKENPHP.md` + aggregate `demo/Makefile` (`demo-smoke` / `verify`).
- Compile-time guard: when `use_database_config` is true and `security.allow_unauthenticated` is false, SecurityBundle must be present.
- `make release-check` now enforces **100%** PHP line coverage (`coverage-check`).

### Changed

- `web_ui.layout_template` is documented as the host integration point (cannot be empty); Twig global `nowo_qr_code_layout_template` is prepended (leading `@` escaped for DI).
- Admin CRUD templates extend `admin/base.html.twig` instead of the full-HTML layout directly.
- Flex recipe documents `web_ui.layout_template` / `web_ui.css_framework`.
- Dev dependencies: `nowo-tech/phpstan-frankenphp` 1.0.3, `rector/rector` 2.6.0, `friendsofphp/php-cs-fixer` 3.95.18.

### Documentation

- Updated [CONFIGURATION.md](CONFIGURATION.md), [USAGE.md](USAGE.md), [INSTALLATION.md](INSTALLATION.md), [UPGRADING.md](UPGRADING.md), and Spec Kit baseline for Web UI + demo.

## [1.2.0] - 2026-07-30

### Added

- Optional Doctrine profile storage: `use_database_config` + `doctrine.table_prefix`.
- Entity `QrCodeProfileConfig` (table `qr_code_profile`); DB rows with the same name **fully override** YAML profiles; DB-only names are also resolvable.
- Admin CRUD at `/admin/qr-code-profiles` (import from YAML, create/edit/delete) with role gate via `security.access_roles`.
- Flex recipe routes import (`config/routes/nowo_qr_code.yaml`) for the admin CRUD.
- Composer `suggest` entries for `doctrine/orm`, `doctrine/doctrine-bundle`, `symfony/form`, `symfony/validator`, and `symfony/security-bundle`.

## [1.1.0] - 2026-07-30

### Added

- Twig UX component **`NowoQrCode`** via `symfony/ux-twig-component` (`<twig:NowoQrCode content="…" />` / `url` + optional `profile`).
- Twig namespace **`@NowoQrCodeBundle`** and `TwigPathsPass` so apps can override `components/qr_code.html.twig` (REQ-TWIG-001 / REQ-TWIG-002).
- Toolkit-friendly `{# @prop #}` documentation on the component template for Symfony UX Toolkit workflows.
- Composer `suggest` for optional `symfony/ux-toolkit` (PHP 8.4+, experimental) to compose kit UI around the QR image.

### Changed

- Hard dependencies now include `symfony/twig-bundle` and `symfony/ux-twig-component` (`^2.20 || ^3.0`).

## [1.0.0] - 2026-07-30

Initial public release of **nowo-tech/qr-code-bundle**, extracted from [WalletQrBundle](https://github.com/nowo-tech/WalletQrBundle) for standalone PNG QR generation.

### Added

- PNG QR codes as data URIs via `QrCodeService` and `QrCodeDataUriRenderer` (`endroid/qr-code`).
- Named profiles: `default_profile` + `profiles` (REQ-CFG-001 / REQ-CFG-002).
- Flat root keys (`size`, `margin`, `error_correction`, `url_allowlist`) accepted and normalized into `profiles.<default_profile>` for BC with WalletQrBundle-style YAML.
- `ProfileResolver` / `QrCodeProfile`; optional `$profile` on service methods and Twig helpers.
- `QrUrlPolicy`: http(s) only; host exact/subdomain allowlist, path substrings, `#regex`.
- `QrErrorCorrection` enum with strict configuration validation.
- Twig helpers `qr_code_data_uri` and `qr_code_for_url`.
- Flex recipe under `.symfony/recipe/nowo-tech/qr-code-bundle/`.
- FrankenPHP-friendly worker mode claim; full Nowo docs / Spec Kit / CI scaffold.

[1.3.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.3.0
[1.2.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.0.0
