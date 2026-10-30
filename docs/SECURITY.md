# Security

## Table of contents

- [Reporting](#reporting)
- [Threat model](#threat-model)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging and observability](#logging-and-observability)
- [Composer audit](#composer-audit)
- [Exposure surface](#exposure-surface)
- [Residual risk](#residual-risk)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [AI security audit (REQ-SEC-004)](#ai-security-audit-req-sec-004)

## Reporting

Report vulnerabilities privately via [GitHub Security Advisories](https://github.com/nowo-tech/QrCodeBundle/security/advisories) for this repository. Do not open public issues for unfixed vulnerabilities.

## Threat model

| Risk | Mitigation |
|------|------------|
| Phishing / unsafe QR payloads | `QrUrlPolicy` accepts only `http`/`https` and rejects `javascript:`, `data:`, and other schemes. |
| Host allowlist bypass | Host patterns match exact host or subdomains only (not raw substrings). Path patterns (`host/path`) parse the URL and match host + path prefix (never a raw substring of the URL, so `https://evil.tld/?r=good.com/path` does not match `good.com/path`). Regex patterns `#…#` are PCRE on the parsed host only; full-URL regexes need the explicit, anchored `url#^…#` form (since 1.11.0). Before matching, URLs whose authority browsers and `parse_url()` read differently are rejected: backslashes, control characters, whitespace, userinfo, percent-encoded / Unicode hosts (since 1.9.0). |
| Empty allowlist | Any http(s) URL may be encoded — treat QR content as **host-controlled**; do not pass untrusted end-user URLs without an allowlist. Flex `when@prod` sets `url_allowlist_required: true` so an empty or `example.com` placeholder fail compilation (since 1.4.6). |
| Twig / XSS | Helpers emit data-URI strings; escape surrounding HTML as usual (`attr`/`html` contexts). |
| Resource abuse | Bound `size`/`margin` in config; rate-limit generation if exposed to anonymous users. |
| Supply chain | Keep `endroid/qr-code` and Symfony components updated; run `composer audit` before releases. |

## Secrets and cryptography

This bundle does **not** store credentials, API keys, or cryptographic secrets. It does not implement signing of QR payloads. Do not put secrets into QR content.

## Logging and observability

Do not log full untrusted URLs that may contain tokens or PII. Prefer logging host-only or redacted forms when diagnosing policy rejections. See also org-wide observability hygiene (REQ-OBS-001).

## Composer audit

Before tagging a release, run `composer audit` (and address Critical/High findings) as part of the Security 12.4.1 release checklist (REQ-SEC-002).

## Exposure surface

| Surface | Notes |
|---------|--------|
| PHP API | `QrCodeService`, `QrCodeDataUriRenderer`, `QrUrlPolicy` |
| Twig | `qr_code_data_uri`, `qr_code_for_url` |
| HTTP / admin UI | Admin CRUD at `/admin/qr-code-profiles` (index, new, edit, delete, import YAML) when `use_database_config` profiles are enabled: role-gated by `security.access_roles` / `access_checker` (empty list denies everyone), CSRF-protected Symfony forms, paginated list (`web_ui.list_page_size`). No public QR endpoint. |
| Config | `nowo_qr_code.*` (size, margin, error_correction, url_allowlist) |

## Residual risk

Encoding attacker-controlled text via `createDataUri` (no URL policy) can still produce phishing-looking QR payloads. Callers that accept user input **must** apply their own validation or use `createDataUriForUrl` with a non-empty allowlist.

## Release security checklist (12.4.1)

Confirm before each release (REQ-SEC-002):

| Item | Check |
|------|--------|
| `docs/SECURITY.md` + `.github/SECURITY.md` | Present and up to date |
| `.env` ignored | Root `.gitignore` covers `.env` / `.env.local` |
| No secrets in repo | No credentials, tokens, or private keys committed |
| Flex recipe safe | `.symfony/recipe/...` copies only default config (no secrets) |
| Input / output | Config enum validation; URL policy; Twig callers escape HTML |
| Dependencies | `composer audit` clean or waived with documented CVE policy |
| No-secret logs | Do not log full URLs that may contain tokens/PII |
| Cryptography | N/A (no crypto in this bundle) |
| Permissions / exposure | Admin CRUD at `/admin/qr-code-profiles` is role-gated (`security.access_roles`; an empty list denies everyone, fail-closed); keep `allow_unauthenticated` false in production. QR generation APIs remain in-process (no public QR endpoint). |
| Limits / DoS | `size`/`margin` bounded; rate-limit if exposed publicly |
| AI security audit (REQ-SEC-004) | Pass recorded in monorepo `BUNDLES_SECURITY_ANALYSIS.md` |

## AI security audit (REQ-SEC-004)

| Field | Value |
|-------|--------|
| Date | 2026-10-30 |
| Method | Claude Code static security review |
| Scope | `main` at `28e8ae4` (1.11.0) |
| Grade | **Pass (good)** |
| Overall risk | **Low** |
| Open Critical / High / Medium | None |
| Notes | Monorepo `BUNDLES_SECURITY_ANALYSIS.md` Appendix BK. Low findings fixed in 1.11.0: L1 (`#…#` allowlist regexes also matched the full URL, so `#good\.com#` allowed `https://evil.tld/?x=good.com`; now host-only, full-URL regexes need the anchored `url#^…#` opt-in) and L2 (dist archive shipped demo/tests/tooling; now `export-ignore`d). Also: admin routes only imported with `use_database_config` (404 instead of 500), admin controller `final`. |
