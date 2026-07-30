# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[1.2.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.0.0
