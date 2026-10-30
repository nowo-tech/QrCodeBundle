# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.11.0](#to-1110)
  - [Template contract](#template-contract)
  - [Layout](#layout)
  - [Configuration](#configuration)
  - [Template overrides](#template-overrides)
  - [URL allowlist regexes](#url-allowlist-regexes)
  - [Admin routes](#admin-routes)
- [To 1.10.0](#to-1100)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
- [To 1.7.2](#to-172)
- [To 1.7.1](#to-171)
- [To 1.7.0](#to-170)
- [To 1.6.0](#to-160)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
- [To 1.4.10](#to-1410)
- [From 1.4.8 to 1.4.9](#from-148-to-149)
- [From 1.4.7 to 1.4.8](#from-147-to-148)
- [From 1.4.6 to 1.4.7](#from-146-to-147)
- [To 1.4.6](#to-146)
- [To 1.4.5](#to-145)
- [To 1.4.4](#to-144)
- [To 1.4.3](#to-143)
- [To 1.4.2](#to-142)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
  - [UiKit composition (REQ-UI-001-kit)](#uikit-composition-req-ui-001-kit)
  - [FormKitBundle (admin forms)](#formkitbundle-admin-forms)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [[1.3.0] - 2026-08-03 — Admin Web UI look-and-feel + FrankenPHP demo](#130---2026-08-03--admin-web-ui-look-and-feel--frankenphp-demo)
  - [Optional host layout integration](#optional-host-layout-integration)
  - [SecurityBundle when using DB admin](#securitybundle-when-using-db-admin)
  - [FrankenPHP demo](#frankenphp-demo)
- [[1.2.0] - 2026-07-30 — Database profile overrides](#120---2026-07-30--database-profile-overrides)
- [[1.1.0] - 2026-07-30](#110---2026-07-30)
  - [Twig Component + UX Toolkit](#twig-component--ux-toolkit)
- [From WalletQrBundle-embedded QR helpers](#from-walletqrbundle-embedded-qr-helpers)
- [[1.0.0] - 2026-07-30](#100---2026-07-30)

## Unreleased

No action needed when you import `@NowoQrCodeBundle/Resources/config/routing.yaml` (as documented). If `/admin/qr-code-profiles` only appeared through Symfony's default `controllers: { resource: routing.controllers }` (Symfony >= 7.3), add that import: the bundle controller is no longer registered there (REQ-SF-004 rule 7).

## To 1.11.0

From **1.10.0**:

```bash
composer update nowo-tech/qr-code-bundle
```

```bash
composer update nowo-tech/qr-code-bundle nowo-tech/ui-kit-bundle nowo-tech/form-kit-bundle
```

Requires `nowo-tech/ui-kit-bundle` **^2.17.3** and `nowo-tech/form-kit-bundle` **^2.15**. Nothing changes for apps
that only render QR codes (`use_database_config: false`). For the admin (`use_database_config: true`):

### Template contract

| Before (1.10) | Now |
| --- | --- |
| `admin/index.html.twig` / `admin/form.html.twig` extend `admin/base.html.twig` → `web_ui.layout_template`, fill `body` | They extend `@NowoUiKit/page/list.html.twig` / `page/form.html.twig` (variable `page`: `View\ListPage` / `View\FormPage`) and fill the data blocks `nowo_ui_list_filters`, `nowo_ui_list_row`, `nowo_ui_form_body` |
| `admin/base.html.twig`, `admin/layout.html.twig` | Removed |
| Variables `profiles`, `pagination` (array), `import_form`, `delete_forms`, `yaml_profiles`, `use_database_config`; `form`, `profile`, `is_new` | `page` (view model: header actions, columns, row actions with their CSRF FormViews, `View\Pagination`, empty state / form, delete action) + `profiles`, `yaml_profiles`, `use_database_config`, `profile`, `is_new` |
| Delete: `onsubmit="return confirm(…)"` | UiKit confirm `<dialog>` (`View\Confirm`) |
| Hard-coded English | `NowoQrCodeBundle` translation keys |

Pages, view-model fields and data blocks: [TEMPLATES.md](TEMPLATES.md).

### Layout

1. Point **`nowo_ui_kit.layouts.admin`** at your admin layout (it must render the UiKit canonical blocks, e.g. by
   `use`-ing `@NowoUiKit/layout/_bridge.html.twig`; see the demo `templates/layouts/admin.html.twig`) and leave
   `nowo_qr_code.web_ui.layout_template` unset (`null`). The old default `@NowoQrCodeBundle/admin/layout.html.twig` is
   mapped to `null`.
2. A `web_ui.layout_template` written for 1.x (renders `body`, `title`, `stylesheets`, `javascripts`, e.g. your
   `base.html.twig`) keeps working through the deprecated bridge `admin/_legacy_layout.html.twig` (deprecation notice;
   removed in 2.0).
3. Load your CSS framework and form theme in the host (the removed layout shipped inline CSS); set
   `twig.form_themes` (e.g. `bootstrap_5_layout.html.twig`) — the `qr_code` FormKit profile no longer adds Bootstrap
   classes.

### Configuration

- `web_ui.css_framework` is deprecated: set `nowo_ui_kit.css_framework` (a host-set value is still copied there). The
  bundle no longer seeds `nowo_ui_kit.css_framework` / `icon_set` when you did not set them.
- New `web_ui.templates.<page>` (`index`, `form`) for a thin `{% extends '@!NowoQrCodeBundle/admin/<page>.html.twig' %}`
  override of one data block.
- The admin now **requires CSRF protection**: enable `framework.csrf_protection` and `framework.form.csrf_protection`
  (`composer require symfony/security-csrf`), or the container does not compile while `use_database_config` is true.

### Template overrides

Copies of the old `templates/bundles/NowoQrCodeBundle/admin/*.html.twig` are frozen 1.x markup: delete them, then
re-apply only what you still need through `nowo_ui_kit.theme`, translations (`translations/NowoQrCodeBundle.<locale>.yaml`)
or `web_ui.templates.<page>` (see [TEMPLATES.md](TEMPLATES.md#overriding-templates-req-twig-001)).

### URL allowlist regexes

Security fix (SEC-004 Appendix BK, L1). A `#…#` entry in `url_allowlist` (YAML profile or admin form) was matched
against the host **and then the full URL**, so an unanchored `#good\.com#` allowed `https://evil.tld/?x=good.com`.

| Before (1.10) | Now |
| --- | --- |
| `#…#` matched the host, then the full URL | `#…#` matches the parsed host only |
| Full-URL regex: `#^https://cdn\.example\.com/#` | `url#^https://cdn\.example\.com/#` (explicit `url` prefix, anchored with `^` or `\A`) |
| YAML regexes were not validated | YAML and admin form reject non-compiling regexes, nested quantifiers, `/` in a `#…#` host regex and unanchored `url#` regexes |

What to do:

1. Host regexes (`#^cdn\.example\.com$#`) keep working; anchor them (`^…$`) if they are not.
2. A YAML regex written for the full URL (it contains `/`) now fails container compilation with a hint: prefix it with
   `url` (`url#^https://…#`) or replace it with a path pattern (`cdn.example.com/passes/`).
3. Database profiles are not re-validated on upgrade: a stored `#…/…#` regex silently stops matching (URLs are
   rejected, fail-closed). Edit the profile in `/admin/qr-code-profiles` and switch it to `url#^…#` or a path pattern.

### Admin routes

`@NowoQrCodeBundle/Resources/config/routing.yaml` (imported by the Flex recipe) now imports the admin routes only while
`use_database_config` is `true`; with `false` the paths are a 404 instead of a 500. Nothing to do. If you imported the
controller directly (`resource: '@NowoQrCodeBundle/Controller/'`, `type: attribute`), switch to `routing.yaml`.
`QrCodeProfileAdminController` is now `final`: decorate or replace the route instead of extending it.

## To 1.10.0

From **1.9.1**:

```bash
composer update nowo-tech/qr-code-bundle
```

## To 1.9.1

From **1.9.0**:

```bash
composer update nowo-tech/qr-code-bundle
```

## To 1.9.0

From **1.8.1**:

```bash
composer update nowo-tech/qr-code-bundle
```

**URL validation is stricter.** `QrUrlPolicy` (QR codes for URLs, WalletQr) now rejects URLs containing `\`, control
characters or spaces, URLs with userinfo (`user@host`, `user:pass@host`) and hosts that are not plain DNS names, IPv4
or bracketed IPv6 literals. Encode internationalised domain names in punycode (`https://xn--bcher-kva.de/`) and
percent-encode spaces in paths/queries before generating the QR code.

## To 1.8.1

From **1.8.0**:

```bash
composer update nowo-tech/qr-code-bundle
```

## To 1.8.0

From **1.7.2**:

```bash
composer update nowo-tech/qr-code-bundle
```

- The admin profile list is paginated (`nowo_qr_code.web_ui.list_page_size`, default 50). If you override `admin/index.html.twig`, use the new `import_form`, `delete_forms` and `pagination` variables instead of building raw `<form>` tags with `csrf_token()`.

## To 1.7.2

From **1.7.1**:

```bash
composer update nowo-tech/qr-code-bundle
```

## To 1.7.1

From **1.7.0** — allowlist path patterns keep their case when a scheme is given.

```bash
composer update nowo-tech/qr-code-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.7.0

From **1.6.0** — CSRF delete feedback, accurate allowlist docs, Symfony >=7.4.

```bash
composer update nowo-tech/qr-code-bundle
```

- **Requirements:** Symfony **7.4** or **8.x** is now required (`^7.4 || ^8.0`, was `^7.0 || ^8.0`). Apps on Symfony 7.0–7.3 must upgrade to 7.4 LTS first (`composer update "symfony/*" --with-all-dependencies`).

## To 1.6.0

From **1.5.1** — security: fail closed on empty access_roles, parsed URL path patterns, unique profile names.

```bash
composer update nowo-tech/qr-code-bundle
```

- **Behaviour change:** `security.access_roles: []` used to open the admin to everyone and now denies access. Set roles, or `allow_unauthenticated: true` (dev only).
- Allowlist patterns containing `/` no longer match anywhere in the URL: `good.com/path` needs host good.com and a path starting with `/path`; `/wallet/` must be a path prefix.

## To 1.5.1

From **1.5.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/qr-code-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.5.0

From **1.4.10** — Doctrine `SortDirection`; `doctrine/orm` `^3.7` when Doctrine storage is used.

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear
```

- If you use `use_database_config: true`, ensure `doctrine/orm` is `^3.7` (the package now conflicts with `doctrine/orm <3.7`). Apps without Doctrine are unaffected.
- No configuration changes.

## To 1.4.10

From **1.4.9** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/qr-code-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.4.8 to 1.4.9

No required application upgrade steps for YAML-only setups.

If you use **`use_database_config: true`** under FrankenPHP **worker** mode with the kernel **not** reset between requests (`reset_kernel` / equivalent off):

- Render path no longer depends on Doctrine's identity map (`findProfileArrayByName()`).
- Database mode registers `ClosedEntityManagerSubscriber` (closed EM recovery + detach of `QrCodeProfileConfig` on each main request).
- Prefer `QrCodeProfileConfigRepository::findProfileArrayByName()` over `findOneByName()` if you call the repository from your own code for rendering.

See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

```bash
composer update nowo-tech/qr-code-bundle
```

## From 1.4.7 to 1.4.8

No application upgrade steps.

```bash
composer update nowo-tech/qr-code-bundle
```

## From 1.4.6 to 1.4.7

Review the [CHANGELOG](CHANGELOG.md) entry. PHP **8.2+** may now be required.

```bash
composer update nowo-tech/qr-code-bundle
```

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
