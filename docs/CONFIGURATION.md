# Configuration

Root key: `nowo_qr_code`.

## Table of contents

- [Profiles (REQ-CFG-001)](#profiles-req-cfg-001)
- [Database overrides (optional)](#database-overrides-optional)
  - [Selecting a profile](#selecting-a-profile)
- [Admin Web UI (layout and CSS)](#admin-web-ui-layout-and-css)
  - [Flat legacy config (BC)](#flat-legacy-config-bc)
- [`error_correction`](#error_correction)
- [`url_allowlist`](#url_allowlist)
- [Container parameters](#container-parameters)
- [Twig template overrides (REQ-TWIG-001)](#twig-template-overrides-req-twig-001)

## Profiles (REQ-CFG-001)

Canonical shape: `default_profile` + `profiles`. Each profile is a complete settings block (`size`, `margin`, `error_correction`, `url_allowlist`).

```yaml
# config/packages/nowo_qr_code.yaml
nowo_qr_code:
    default_profile: default
    profiles:
        default:
            size: 300                 # pixels (64–1024)
            margin: 10                # quiet zone (0–64)
            error_correction: high    # low | medium | quartile | high
            url_allowlist: []         # optional; empty = any http(s) URL
        compact:
            size: 128
            margin: 2
            error_correction: medium
            url_allowlist:
                - example.com
```

- `default_profile` **must** be a key under `profiles` (compile-time validation).
- Runtime APIs accept an optional profile name; omitted/`null` uses `default_profile`.

## Database overrides (optional)

Disabled by default. When enabled, Doctrine rows in `qr_code_profile` override YAML **by profile name** (full replace for that name). New DB-only names are also valid at runtime.

```yaml
nowo_qr_code:
    use_database_config: true
    doctrine:
        table_prefix: ''          # optional; e.g. app_ → app_qr_code_profile
    security:
        access_roles: [ROLE_ADMIN]
        allow_unauthenticated: false
    default_profile: default
    profiles:
        default:
            size: 300
            margin: 10
            error_correction: high
            url_allowlist: []
```

| Key | Default | Meaning |
| --- | --- | --- |
| `use_database_config` | `false` | When `true`, loads Doctrine services + admin CRUD; requires `doctrine/orm` ^3.7 |
| `doctrine.table_prefix` | `''` | Prefixed onto table `qr_code_profile` |
| `security.access_roles` | `[ROLE_ADMIN]` | Roles allowed to use `/admin/qr-code-profiles`. Empty list = **deny everyone** (fail-closed); use `allow_unauthenticated` to open it |
| `security.allow_unauthenticated` | `false` | Open admin (demo/dev only) |
| `security.access_checker` | `null` | Optional custom `QrCodeAccessCheckerInterface` service id |

**Setup**

1. `composer require doctrine/orm doctrine/doctrine-bundle symfony/form symfony/validator`
2. Set `use_database_config: true`
3. Create schema for `qr_code_profile` (apply `table_prefix` if configured)
4. Ensure routes import `@NowoQrCodeBundle/Resources/config/routing.yaml` (the Flex recipe does). It loads the admin
   routes only while `use_database_config` is `true`; otherwise it imports nothing and `/admin/qr-code-profiles` is a 404.
5. Open `/admin/qr-code-profiles` — use **Import from YAML** or create rows manually
6. Protect the admin path in the host firewall (required when `allow_unauthenticated` is `false`; SecurityBundle is enforced at compile time):

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/qr-code-profiles, roles: ROLE_ADMIN }
```

**FrankenPHP worker mode:** with `use_database_config: true`, the bundle does not rely on Doctrine `kernel.reset`. Render uses array hydration; `ClosedEntityManagerSubscriber` recovers a closed EntityManager and detaches managed profile rows on each main request. See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

**Merge rules**

| Situation | Result |
| --- | --- |
| Flag off | YAML only (table unused) |
| Flag on, no DB row for name | YAML profile |
| Flag on, DB row with same name | **DB wins** (full replace) |
| Flag on, DB-only name | DB profile |

### Selecting a profile

| Surface | How |
| --- | --- |
| PHP | `QrCodeService::createDataUri($content, ?string $profile = null)` |
| PHP | `QrCodeService::createDataUriForUrl($url, ?string $profile = null)` |
| Twig | `qr_code_data_uri(content, profile|null)` |
| Twig | `qr_code_for_url(url, profile|null)` |

```php
$qrCodeService->createDataUri('EVENT-42', 'compact');
$qrCodeService->createDataUriForUrl('https://example.com/pass', 'compact');
```

```twig
<img src="{{ qr_code_data_uri('Hello', 'compact') }}" alt="QR">
<img src="{{ qr_code_for_url(downloadUrl, 'compact') }}" alt="QR">
```

## Admin Web UI (layout and CSS)

Admin CRUD pages (when `use_database_config` is enabled) follow the UiKit page contract (REQ-UI-003): they extend the
UiKit list / form scaffolds, fed by view models, inside **`nowo_ui_kit.layouts.admin`** (your host layout or a one-file
bridge) and themed once through **`nowo_ui_kit.theme`** / **`nowo_ui_kit.css_framework`**. Pages, data blocks and view
models: [TEMPLATES.md](TEMPLATES.md).

| Key | Default | Meaning |
| --- | --- | --- |
| `web_ui.layout_template` | `null` | Layout the admin pages extend. `null` = `nowo_ui_kit.layouts.admin`. A 1.x layout (no UiKit canonical blocks, renders `body`) is wrapped in a deprecated bridge; the removed 1.x default `@NowoQrCodeBundle/admin/layout.html.twig` maps to `null` |
| `web_ui.templates.<page>` | `{}` | Per-page template override, pages `index` and `form` (unknown ids fail the container compilation). Extend `@!NowoQrCodeBundle/admin/<page>.html.twig` and override a data block |
| `web_ui.css_framework` | `null` | **Deprecated since 1.11**: set `nowo_ui_kit.css_framework`. When set (and the host did not set `nowo_ui_kit.css_framework`) it is copied there (`bootstrap` → `bootstrap5`) |
| `web_ui.list_page_size` | `50` | Profiles per page on the admin list (`?page=N`, 1–500) (REQ-PERF-001) |

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'   # host layout rendering the UiKit canonical blocks (or a bridge)

nowo_qr_code:
    web_ui:
        layout_template: null              # null = nowo_ui_kit.layouts.admin
        list_page_size: 50
        # templates: { index: 'admin/qr/index.html.twig' }
```

**Host integration**

1. Set `nowo_ui_kit.layouts.admin` once (a layout that renders the UiKit canonical blocks — `nowo_ui_page_header`,
   `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, … — e.g. one that `use`s `@NowoUiKit/layout/_bridge.html.twig`).
2. Theme `nowo_ui_kit.theme` (classes, attributes, partials, tokens) once; per-bundle exceptions go under
   `nowo_ui_kit.theme.bundles.NowoQrCodeBundle`.
3. The admin labels come from the `NowoQrCodeBundle` translation domain (en, es, fr, it, pt, de, nl); override keys in
   `translations/NowoQrCodeBundle.<locale>.yaml` (REQ-I18N-001).
4. The admin fails closed without CSRF protection: with `use_database_config: true` the container refuses to compile
   unless `framework.csrf_protection` and `framework.form.csrf_protection` are enabled (`symfony/security-csrf`).

### Flat legacy config (BC)

Options at the root without a `profiles` map are normalized into `profiles.<default_profile>`:

```yaml
nowo_qr_code:
    size: 300
    margin: 10
    error_correction: high
    url_allowlist: []
```

Equivalent to `default_profile: default` with a single `profiles.default` entry.

## `error_correction`

Must be one of: `low`, `medium`, `quartile`, `high` (default `high`). Invalid values fail configuration processing at compile time. The same values are modeled by `Nowo\QrCodeBundle\Enum\QrErrorCorrection`.

## `url_allowlist`

Used by `QrCodeService::createDataUriForUrl` and Twig `qr_code_for_url` **for the selected profile**.

| Pattern form | Behaviour |
| --- | --- |
| Empty list | Any `http`/`https` URL with a host is allowed |
| Host name (no `/`) | Exact host **or** subdomain (e.g. `example.com` matches `cdn.example.com`, **not** `evil-example.com`) |
| Contains `/` | `[scheme://]host[:port]/path-prefix` on the **parsed** URL: host exact or subdomain, scheme/port when given, URL path starts with the pattern path. Empty host (e.g. `/wallet/`) = that path prefix on any host. Paths with dot segments (`..`, `.`) never match. |
| Starts with `#` (`#…#`) | PCRE applied to the parsed **host only** (e.g. `#^(cdn\|img)\.example\.com$#`). A `/` in the pattern is rejected: hosts never contain `/` |
| Starts with `url#` (`url#^…#`) | Explicit opt-in: PCRE applied to the **full URL**. Must be anchored at the start (`url#^https://cdn\.example\.com/#`); an unanchored one is rejected at configuration time and never matches at render time |

Regex patterns (`#…#`, `url#…#`) are validated when the configuration is processed and when the admin form is
submitted: they must compile and may not nest quantifiers (catastrophic backtracking). Prefer anchored regexes
(`^…$`): an unanchored host regex such as `#good\.com#` also matches `good.com.evil.tld`.

> **Changed in 1.11.0 (security):** `#…#` regexes used to be tried against the host **and then the full URL**, so
> `#good\.com#` allowed `https://evil.tld/?x=good.com`. They now match the host only; use `url#^…#` for full-URL
> regexes. See [UPGRADING](UPGRADING.md#unreleased).

Only `http` and `https` schemes are accepted. Schemes such as `javascript:` and `data:` are always rejected.

`url_allowlist_required` (default `false`): when `true` (Flex recipe `when@prod`), compilation fails if the **default** profile allowlist is empty or only the Flex placeholder `example.com` / `www.example.com`. Set real hosts before deploying.

## Container parameters

| Parameter | Meaning |
| --- | --- |
| `nowo_qr_code.default_profile` | Default profile name |
| `nowo_qr_code.profiles` | Full profiles map |
| `nowo_qr_code.size` / `margin` / `error_correction` / `url_allowlist` | Convenience mirrors of the **default** profile |
| `nowo_qr_code.web_ui.layout_template` | Admin layout override (`null` = `nowo_ui_kit.layouts.admin`) |
| `nowo_qr_code.web_ui.templates` | Per-page template overrides (`index`, `form`) |
| `nowo_qr_code.web_ui.css_framework` | Deprecated: normalized `web_ui.css_framework` (`bootstrap` → `bootstrap5`), `null` when unset |

Twig globals `nowo_qr_code_layout_template` and `nowo_qr_code_css_framework` (`custom` when unset) are **deprecated
since 1.11**: the pages no longer read them (REQ-UI-003); they are removed in 2.0.

## Twig template overrides (REQ-TWIG-001)

Namespace: **`NowoQrCodeBundle`**. Freeze rule, upgrade-safe alternatives, recovery procedure and the full subpath
table: [TEMPLATES.md](TEMPLATES.md#overriding-templates-req-twig-001).
