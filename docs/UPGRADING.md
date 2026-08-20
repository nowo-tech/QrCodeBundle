# Upgrading

## To 1.4.6

From **1.4.5** — **Production compile can fail.** Flex `when@prod` no longer lists `example.com`. With `url_allowlist_required: true`, container compilation fails if the default profile `url_allowlist` is empty **or** only `example.com` / `www.example.com` (leftover recipe placeholder).

1. Set real hosts (or path/regex patterns) on `nowo_qr_code.profiles.default.url_allowlist`.
2. Keep `url_allowlist_required: true` in production.
3. Local demos may set `url_allowlist_required: false` (not for production).

Existing apps that copied the old recipe and left `example.com` **will fail compile after this upgrade** until the allowlist is replaced. That is intentional (fail-fast instead of encoding only `example.com` URLs).

```yaml
# config/packages/prod/nowo_qr_code.yaml (or when@prod)
nowo_qr_code:
    url_allowlist_required: true
    profiles:
        default:
            url_allowlist:
                - pay.google.com
                - wallet.apple.com
```

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear --env=prod
```

## To 1.4.5

From **1.4.4** — Ensure you are on **`^1.4.5`** for the shipped `UrlAllowlistValidationPass`. When `url_allowlist_required: true`, non-empty `url_allowlist` is required at compile time.

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear
```

## To 1.4.4

From **1.4.3** — When `url_allowlist_required: true` (Flex `when@prod`), ensure `nowo_qr_code.default.url_allowlist` is non-empty before deploying. Compilation fails with a clear error if the allowlist is empty.

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear
```

## To 1.4.3

From **1.4.2** — Review production SSRF config. Flex recipe `when@prod` ships a `url_allowlist` placeholder under `nowo_qr_code`. Replace with your allowed host patterns before generating QR codes from user-supplied URLs.

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear
```

## To 1.4.2

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear
```

## To 1.4.1

No application code changes required. Patch restores the CI **100%** PHP line coverage gate (FormKit / UiKit extension prepend tests).

```bash
composer update nowo-tech/qr-code-bundle
```

## To 1.4.0

From **1.3.x** — FormKit ^2, UiKit ^1.4, Twig Extra (REQ-TWIG-004), and Twig-CS-Fixer.

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear
php bin/console assets:install
```

### UiKit composition (REQ-UI-001-kit)

Admin UI now depends on **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** (`nowo-tech/ui-kit-bundle` `^1.4`).

1. Require the package (pulled transitively once you update this bundle) and run `assets:install`.
2. Stylesheet package: `asset('css/nowo-ui.css', 'nowo_ui_kit')` via `admin/base.html.twig`.
3. Optional: set `nowo_ui_kit.css_framework` / `icon_set` in the host. If unset, QrCode seeds those keys from `web_ui.css_framework` (default `custom`) and defaults `icon_set` to `bootstrap-icons`.
4. Template overrides: extend `@NowoQrCodeBundle/admin/base.html.twig` and use UiKit macros (`ui.flash`, `ui.btn`) instead of hard-coded alert/button classes where applicable.

### FormKitBundle (admin forms)

Ensure `nowo-tech/form-kit-bundle` ^2.0 is installed (pulled transitively) and `Nowo\FormKitBundle\NowoFormKitBundle` is registered. Form types use profile `qr_code` via `#[FormKitConfig]`; the bundle prepends that profile when the host has not defined it.

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).


## [1.3.0] - 2026-08-03 — Admin Web UI look-and-feel + FrankenPHP demo

**Backward compatible** for apps that do not customize admin templates. Defaults keep `web_ui.css_framework: custom` and the bundled standalone layout.

### Optional host layout integration

```yaml
nowo_qr_code:
    web_ui:
        layout_template: 'base.html.twig'   # or a thin bridge mapping body/stylesheets/javascripts
        css_framework: custom               # bootstrap5 | bootstrap | tailwind | tabler | …
```

1. Admin pages extend `admin/base.html.twig`, which calls `{{ parent() }}` so host CSS/JS still load.
2. Prefer `layout_template` / nested `nowo_ui_styles` / `nowo_ui_scripts` over forking `index`/`form` templates.
3. Alias `bootstrap` normalizes to `bootstrap5`.

### SecurityBundle when using DB admin

If `use_database_config: true` and `security.allow_unauthenticated: false`, the host app **must** have `symfony/security-bundle` registered (compile-time `LogicException` otherwise). Demos may set `allow_unauthenticated: true`.

### FrankenPHP demo

```bash
make -C demo/symfony8 up
# http://localhost:8012/demo
```

See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

## [1.2.0] - 2026-07-30 — Database profile overrides

Optional and **backward compatible** when left disabled (`use_database_config: false`, the default).

1. Require Doctrine if you enable DB storage: `composer require doctrine/orm doctrine/doctrine-bundle symfony/form symfony/validator`.
2. Set:

```yaml
nowo_qr_code:
    use_database_config: true
    doctrine:
        table_prefix: ''   # e.g. nowo_ → nowo_qr_code_profile
    security:
        access_roles: [ROLE_ADMIN]
```

3. Create the table (`doctrine:schema:update` or a migration for `qr_code_profile`).
4. Import routes (Flex recipe adds `config/routes/nowo_qr_code.yaml`) and open `/admin/qr-code-profiles`.
5. Resolution rule: if a DB row shares a profile **name** with YAML, the DB row wins completely for that name.

Protect `/admin/qr-code-profiles` with your firewall (default gate: `ROLE_ADMIN`). Set `security.allow_unauthenticated: true` only for local demos.

## [1.1.0] - 2026-07-30

### Twig Component + UX Toolkit

**Breaking for minimal installs:** the package now **requires** `symfony/twig-bundle` and `symfony/ux-twig-component` (`^2.20 || ^3.0`). Run `composer update nowo-tech/qr-code-bundle` so Composer pulls them.

- New component: `<twig:NowoQrCode content="…" />` / `<twig:NowoQrCode url="…" profile="compact" />`.
- Optional host package: `symfony/ux-toolkit` (PHP 8.4+) for Shadcn/other kit components around the QR image.
- Twig overrides: `templates/bundles/NowoQrCodeBundle/components/qr_code.html.twig`.

`QrCodeService` and Twig functions (`qr_code_data_uri`, `qr_code_for_url`) are unchanged.

## From WalletQrBundle-embedded QR helpers

If you previously relied on QR helpers living inside WalletQrBundle and now depend on **nowo-tech/qr-code-bundle**:

1. Require the package: `composer require nowo-tech/qr-code-bundle`.
2. Prefer config under `nowo_qr_code` (see [CONFIGURATION.md](CONFIGURATION.md)).
3. Flat YAML still works and is normalized into `profiles.default`:

```yaml
# Still valid (normalized to profiles.default)
nowo_qr_code:
    size: 300
    margin: 10
    error_correction: high
    url_allowlist: []
```

4. Canonical multi-profile shape:

```yaml
nowo_qr_code:
    default_profile: default
    profiles:
        default:
            size: 300
            margin: 10
            error_correction: high
            url_allowlist: []
```

5. Service / Twig APIs:
   - `QrCodeService::createDataUri($content, ?string $profile = null)`
   - `QrCodeService::createDataUriForUrl($url, ?string $profile = null)`
   - Twig: `qr_code_data_uri`, `qr_code_for_url` (optional second argument = profile name)
   - Component: `<twig:NowoQrCode … />`

WalletQrBundle may still prepend legacy `nowo_wallet_qr.qr_code` onto `nowo_qr_code`; that flat payload remains compatible via the normalization above.

## [1.0.0] - 2026-07-30

Initial release. No prior tagged versions of this package.
