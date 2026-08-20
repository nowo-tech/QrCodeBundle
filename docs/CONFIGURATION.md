# Configuration

Root key: `nowo_qr_code`.

## Table of contents

- [Profiles (REQ-CFG-001)](#profiles-req-cfg-001)
- [Database overrides (optional)](#database-overrides-optional)
- [Admin Web UI (layout and CSS)](#admin-web-ui-layout-and-css)
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
| `use_database_config` | `false` | When `true`, loads Doctrine services + admin CRUD; requires `doctrine/orm` |
| `doctrine.table_prefix` | `''` | Prefixed onto table `qr_code_profile` |
| `security.access_roles` | `[ROLE_ADMIN]` | Roles allowed to use `/admin/qr-code-profiles` |
| `security.allow_unauthenticated` | `false` | Open admin (demo/dev only) |
| `security.access_checker` | `null` | Optional custom `QrCodeAccessCheckerInterface` service id |

**Setup**

1. `composer require doctrine/orm doctrine/doctrine-bundle symfony/form symfony/validator`
2. Set `use_database_config: true`
3. Create schema for `qr_code_profile` (apply `table_prefix` if configured)
4. Ensure routes import `@NowoQrCodeBundle/Resources/config/routing.yaml`
5. Open `/admin/qr-code-profiles` — use **Import from YAML** or create rows manually
6. Protect the admin path in the host firewall (required when `allow_unauthenticated` is `false`; SecurityBundle is enforced at compile time):

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/qr-code-profiles, roles: ROLE_ADMIN }
```

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

Admin CRUD pages (when `use_database_config` is enabled) extend `@NowoQrCodeBundle/admin/base.html.twig`, which extends `web_ui.layout_template` (Twig global `nowo_qr_code_layout_template`) and stacks `nowo_ui_styles` / `nowo_ui_scripts` with `{{ parent() }}`. Prefer pointing `layout_template` at your project layout (or a thin bridge that maps the `body` block) instead of copying list/form templates. The default `admin/layout.html.twig` is a full HTML demo document (no `parent()`).

| Key | Default | Meaning |
| --- | --- | --- |
| `web_ui.layout_template` | `@NowoQrCodeBundle/admin/layout.html.twig` | Root Twig layout (global `nowo_qr_code_layout_template`) |
| `web_ui.css_framework` | `custom` | Host CSS stack hint (global `nowo_qr_code_css_framework`). Values: `bootstrap5` (alias `bootstrap`), `bootstrap4`, `tabler`, `tailwind`, `foundation`, `custom`, `none` |

```yaml
nowo_qr_code:
    web_ui:
        layout_template: 'base.html.twig'   # host layout or one-file bridge
        css_framework: custom               # or bootstrap5 | tailwind | …
```

**Host integration**

1. Set `web_ui.layout_template` to a Twig template that provides `stylesheets`, `javascripts`, and `body` (or bridge those blocks into your app chrome).
2. Bundle pages go through `admin/base.html.twig`, which calls `{{ parent() }}` so host CSS/JS still load; override nested `nowo_ui_styles` / `nowo_ui_scripts` only when needed.
3. Semantic hooks use `nowo-ui-*` classes (`nowo-ui-container`, `nowo-ui-header`, `nowo-ui-toolbar`, `nowo-ui-table`, …) alongside legacy `qr-*` classes. With `css_framework: custom`, style via those hooks; with Bootstrap/etc., load the framework in the host layout.
4. Alias `bootstrap` is normalized to `bootstrap5` at compile time.

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
| Contains `/` | Substring match against the full URL (path allowlisting) |
| Starts with `#` | PCRE applied to host, then full URL |

Only `http` and `https` schemes are accepted. Schemes such as `javascript:` and `data:` are always rejected.

`url_allowlist_required` (default `false`): when `true` (Flex recipe `when@prod`), compilation fails if the **default** profile allowlist is empty or only the Flex placeholder `example.com` / `www.example.com`. Set real hosts before deploying.

## Container parameters

| Parameter | Meaning |
| --- | --- |
| `nowo_qr_code.default_profile` | Default profile name |
| `nowo_qr_code.profiles` | Full profiles map |
| `nowo_qr_code.size` / `margin` / `error_correction` / `url_allowlist` | Convenience mirrors of the **default** profile |
| `nowo_qr_code.web_ui.layout_template` | Admin layout Twig path |
| `nowo_qr_code.web_ui.css_framework` | Normalized CSS framework (`bootstrap` → `bootstrap5`) |

Twig globals (when TwigBundle is present): `nowo_qr_code_layout_template`, `nowo_qr_code_css_framework`.

## Twig template overrides (REQ-TWIG-001)

Namespace: **`NowoQrCodeBundle`**. Application files under `templates/bundles/NowoQrCodeBundle/` **always win** and freeze that subpath until removed or merged (prefer config / surgical overrides for upgrade-safe customisation).

| Subpath | Purpose |
| --- | --- |
| `admin/base.html.twig` | Page shell: extends `layout_template`, stacks `nowo_ui_styles` / `nowo_ui_scripts` with `{{ parent() }}` |
| `admin/layout.html.twig` | Default standalone full-HTML demo chrome |
| `admin/index.html.twig` / `admin/form.html.twig` | Admin CRUD pages (extend `admin/base.html.twig`) |
| `components/qr_code.html.twig` | Markup for the `NowoQrCode` Twig UX component |

Procedure: copy the vendor file to `templates/bundles/NowoQrCodeBundle/<subpath>`, clear Twig cache if needed. Prefer `web_ui.layout_template` / nested blocks over forking full pages.
