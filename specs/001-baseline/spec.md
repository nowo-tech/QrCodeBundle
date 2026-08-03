# Feature Specification: QrCodeBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-30  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/qr-code-bundle`  
**Configuration root**: `nowo_qr_code`

Symfony bundle that renders PNG QR codes as data URIs, with optional http(s) URL policy and host/path allowlisting for safer QR encoding.

---

## User Scenarios & Testing

### User Story 1 — Encode arbitrary content (Priority: P1)

As an application developer, I call `QrCodeService::createDataUri` (or Twig `qr_code_data_uri`) so I can embed a PNG QR for tickets, deep links, or plain text.

**Independent Test**: Call `createDataUri('EVENT-42')` → string starts with `data:image/png;base64,`.

**Acceptance Scenarios**:

1. **Given** default config, **When** `createDataUri` is called with non-empty content, **Then** a PNG data URI is returned.
2. **Given** Twig is available, **When** `qr_code_data_uri` is used, **Then** the same data URI is produced.

### User Story 2 — Encode only safe http(s) URLs (Priority: P1)

As an integrator embedding download/save URLs, I use `createDataUriForUrl` / `qr_code_for_url` so `javascript:` and `data:` schemes are rejected.

**Acceptance Scenarios**:

1. **Given** `https://example.com/path`, **When** `createDataUriForUrl` runs, **Then** a PNG data URI is returned.
2. **Given** `javascript:alert(1)`, **When** `assertAllowed` / `createDataUriForUrl` runs, **Then** `InvalidQrUrlException` is thrown.
3. **Given** a non-empty `url_allowlist`, **When** the host is not matched, **Then** the URL is rejected.

### User Story 3 — Configure size and error correction (Priority: P2)

As an integrator, I set named profiles under `default_profile` + `profiles` so size, ECC, and allowlists can vary per use case.

**Acceptance Scenarios**:

1. **Given** `profiles.default.error_correction: medium`, **When** the extension loads, **Then** the default profile uses medium ECC.
2. **Given** `error_correction: invalid` on a profile, **When** configuration is processed, **Then** Symfony raises `InvalidConfigurationException`.
3. **Given** `size` outside 64–1024, **When** configuration is processed, **Then** validation fails.
4. **Given** `default_profile: missing`, **When** configuration is processed, **Then** validation fails.
5. **Given** flat root keys without `profiles`, **When** configuration is processed, **Then** they are normalized into `profiles.default`.
6. **Given** `createDataUri($content, 'compact')`, **When** `compact` exists, **Then** that profile’s size/ECC are used.

### User Story 4 — Admin CRUD for database profiles (Priority: P2)

As an operator, I manage QR profiles at `/admin/qr-code-profiles` so YAML defaults can be overridden in Doctrine.

**Acceptance Scenarios**:

1. **Given** an authenticated user with a configured access role, **When** they open the admin index, **Then** existing `QrCodeProfileConfig` rows are listed.
2. **Given** `use_database_config: true`, **When** a profile exists in the database, **Then** runtime resolution prefers DB values over YAML.

### Edge Cases

- Host allowlist: `example.com` must not match `evil-example.com`; subdomains like `cdn.example.com` are allowed.
- Path allowlist patterns must contain `/`.
- Empty allowlist entries are skipped.
- URLs without scheme or host are rejected by the policy.
- Unknown runtime profile name throws `InvalidQrProfileException`.

---

## Requirements

### Bundle & DI

- **FR-BUNDLE-001**: `NowoQrCodeBundle` MUST expose `NowoQrCodeExtension` with alias `nowo_qr_code`.
- **FR-DI-001**: `services.yaml` MUST wire `ProfileResolver`, service, and Twig extension (autoconfigure).
- **FR-CFG-001**: `Configuration` MUST define `default_profile` + `profiles` (size, margin, error_correction enum, url_allowlist) and validate the default key.
- **FR-CFG-002**: Flat legacy root keys MUST normalize into `profiles.<default_profile>`; Extension MUST publish profile parameters.
- **FR-CFG-003**: `ProfileResolver` MUST resolve by name (null → default) and throw `InvalidQrProfileException` for unknown names.

### Rendering

- **FR-QR-001**: `QrCodeDataUriRenderer` MUST render PNG data URIs via `endroid/qr-code` Builder + PngWriter.
- **FR-QR-002**: `QrErrorCorrection` enum MUST map to endroid `ErrorCorrectionLevel` cases.
- **FR-SVC-001**: `QrCodeService` MUST expose `createDataUri` / `createDataUriForUrl` with optional `$profile`.

### Security

- **FR-SEC-001**: `QrUrlPolicy` MUST allow only `http`/`https` with a non-empty host.
- **FR-SEC-002**: Allowlist host patterns MUST use exact/subdomain matching (not raw host substring).
- **FR-SEC-003**: `InvalidQrUrlException` MUST be thrown when a URL is rejected via `assertAllowed`.

### Security

- **FR-SEC-001**: `QrUrlPolicy` MUST allow only `http`/`https` with a non-empty host.
- **FR-SEC-002**: Allowlist host patterns MUST use exact/subdomain matching (not raw host substring).
- **FR-SEC-003**: `InvalidQrUrlException` MUST be thrown when a URL is rejected via `assertAllowed`.
- **FR-SEC-004**: Admin routes MUST be gated by `QrCodeAccessCheckerInterface` (`access_roles`, custom checker, or demo-only allow-all).

### Entity & database (optional)

- **FR-DB-001**: When `use_database_config: true`, Extension MUST load Doctrine services and apply table prefix listener.
- **FR-DB-002**: `QrCodeProfileConfig` entity + repository MUST store per-profile size, margin, ECC, and allowlist overrides; `ProfileResolver` MUST prefer DB rows when enabled.

### Admin CRUD

- **FR-ADM-001**: `/admin/qr-code-profiles` MUST provide list/create/edit/delete with Twig admin views and `QrCodeProfileAdminService` seed/import helpers.

### Twig

- **FR-TWIG-001**: `QrCodeExtension` MUST register `qr_code_data_uri` and `qr_code_for_url` with optional profile argument.
- **FR-TWIG-002**: Twig namespace `NowoQrCodeBundle` MUST support app template overrides via `TwigPathsPass`.

### UX component

- **FR-UX-001**: Twig UX component `NowoQrCode` MUST render the shared component template with profile-aware data URIs.

### Explicit non-goals

- No Messenger or outbound HTTP from this package.
- No demo tree is required for the Packagist contract.

---

## Success criteria

- PHPUnit covers Unit + Integration for the service graph and policy edge cases.
- PHPStan level 8 with empty `ignoreErrors` and FrankenPHP rulesets.
- Spec inventory maps **100%** of `src/` production files.
