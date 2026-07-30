# Security

## Reporting

Report vulnerabilities privately via [GitHub Security Advisories](https://github.com/nowo-tech/QrCodeBundle/security/advisories) for this repository. Do not open public issues for unfixed vulnerabilities.

## Threat model

| Risk | Mitigation |
|------|------------|
| Phishing / unsafe QR payloads | `QrUrlPolicy` accepts only `http`/`https` and rejects `javascript:`, `data:`, and other schemes. |
| Host allowlist bypass | Host patterns match exact host or subdomains only (not raw substrings). Path patterns require `/`. Regex patterns use `#…` PCRE. |
| Empty allowlist | Any http(s) URL may be encoded — treat QR content as **host-controlled**; do not pass untrusted end-user URLs without an allowlist. |
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
| HTTP / admin UI | None |
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
| Permissions / exposure | No HTTP controllers; consumers control who can request QR generation |
| Limits / DoS | `size`/`margin` bounded; rate-limit if exposed publicly |
| AI security audit (REQ-SEC-004) | Pass recorded in monorepo `BUNDLES_SECURITY_ANALYSIS.md` |

## AI security audit (REQ-SEC-004)

| Field | Value |
|-------|--------|
| Date | 2026-07-30 |
| Method | Cursor / Nowo static AI security review |
| Grade | **Pass (good)** |
| Overall risk | **Low** |
| Open Critical / High | None |
| Notes | Host allowlist uses exact/subdomain matching; http(s)-only policy; no admin UI |
