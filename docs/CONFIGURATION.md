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
