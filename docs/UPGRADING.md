# Upgrading

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
