# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.8] - 2026-09-03

### Fixed

- Apply Rector `??=` null-coalesce assignment in `NowoQrCodeBundle::getContainerExtension()` so `composer rector-dry` stays clean on Rector 2.6.4+.

### Changed

- **Deps (dev):** bump `vincentlanglet/twig-cs-fixer`; refresh Composer lockfiles.

[1.4.8]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.8

## [1.4.7] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Refactor:** apply `ReadOnlyClassRector` for PHP 8.2 readonly classes.
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.4.7]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.7

## [1.4.6] - 2026-08-20

### Security

- **Flex recipe `when@prod`:** stop shipping `example.com` as a silent allowlist. `url_allowlist_required: true` now fails compilation when the default profile allowlist is empty **or** only the placeholder `example.com` / `www.example.com`. Replace with your production hosts before `cache:clear --env=prod`.

[1.4.6]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.6

## [1.4.5] - 2026-08-19

### Security

- **`url_allowlist_required` compiler pass:** ships on `main` with Flex recipe `when@prod` flag and validation tests (REQ-SEC-004). Prefer **`^1.4.5`** over `v1.4.4` if you rely on compile-time allowlist enforcement.

### Fixed

- **Tests:** coverage for `UrlAllowlistValidationPass` and `Configuration`/`NowoQrCodeExtension` wiring.

[1.4.5]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.5

## [1.4.4] - 2026-08-19

### Security

- **`url_allowlist_required`:** when `true`, container compilation fails if the default profile `url_allowlist` is empty (REQ-SEC-004). Flex recipe `when@prod` enables this flag with a placeholder allowlist.

[1.4.4]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.4

## [1.4.3] - 2026-08-19

### Security

- **Flex recipe `when@prod`:** `url_allowlist` placeholder for SSRF-safe QR generation in production.
- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

[1.4.3]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.3

## [1.4.2] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.4.2]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.2

## [1.4.1] - 2026-08-07

### Fixed

- CI coverage gate: raise measured coverage to **100%** with unit tests for FormKit / UiKit `prepend` seeding branches in `NowoQrCodeExtension`.

[1.4.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `qr_code` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.
- **UiKit:** Admin templates use `ui.btn` / `ui.row_actions` macros with `nowo_qr_code_css_framework` for toolbar, row, and form actions (keeps `qr-*` hooks).

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Requires **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** (`nowo-tech/ui-kit-bundle` `^1.4`). Admin `base.html.twig` loads `asset('css/nowo-ui.css', 'nowo_ui_kit')` and imports `@NowoUiKitBundle/macros/ui.html.twig` (flashes via `ui.flash`, admin index create via `ui.btn`). Extension seeds `nowo_ui_kit` defaults from `web_ui.css_framework` (and `bootstrap-icons` when `icon_set` is unset) when the host has not configured UiKit.

[1.4.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.0

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
