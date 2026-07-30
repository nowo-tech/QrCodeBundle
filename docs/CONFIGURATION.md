# Configuration

Root key: `nowo_qr_code`.

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

## Container parameters

| Parameter | Meaning |
| --- | --- |
| `nowo_qr_code.default_profile` | Default profile name |
| `nowo_qr_code.profiles` | Full profiles map |
| `nowo_qr_code.size` / `margin` / `error_correction` / `url_allowlist` | Convenience mirrors of the **default** profile |

## Twig template overrides (REQ-TWIG-001)

Namespace: **`NowoQrCodeBundle`**. Application files under `templates/bundles/NowoQrCodeBundle/` **always win** and freeze that subpath until removed or merged (prefer config / surgical overrides for upgrade-safe customisation).

| Subpath | Purpose |
| --- | --- |
| `components/qr_code.html.twig` | Markup for the `NowoQrCode` Twig UX component |

Procedure: copy the vendor file to `templates/bundles/NowoQrCodeBundle/<subpath>`, clear Twig cache if needed.
