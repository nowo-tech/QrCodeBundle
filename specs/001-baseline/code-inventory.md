# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/qr-code-bundle`  
**Last audited**: 2026-07-30

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` are out of Packagist scope unless promoted in the spec.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoQrCodeBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree + profiles | FR-CFG-001, FR-CFG-002 |
| `DependencyInjection/NowoQrCodeExtension.php` | DI extension | FR-CFG-002, FR-DI-001 |
| `Config/QrCodeProfile.php` | Resolved profile DTO | FR-CFG-001 |
| `Config/ProfileResolver.php` | Profile name resolution | FR-CFG-001 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace + overrides | FR-TWIG-001, FR-TWIG-002 |
| `Enum/QrErrorCorrection.php` | Error-correction enum | FR-QR-002 |
| `Exception/InvalidQrUrlException.php` | Policy failures | FR-SEC-003 |
| `Exception/InvalidQrProfileException.php` | Unknown profile name | FR-CFG-001 |
| `QrCode/QrCodeDataUriRenderer.php` | PNG data URI renderer | FR-QR-001, FR-QR-002 |
| `Security/QrUrlPolicy.php` | URL scheme + allowlist | FR-SEC-001, FR-SEC-002 |
| `Service/QrCodeService.php` | Public API | FR-SVC-001 |
| `Twig/QrCodeExtension.php` | Twig helpers | FR-TWIG-001 |
| `Twig/Component/QrCode.php` | UX Twig component `NowoQrCode` | FR-UX-001 |

## Config & resources (`src/Resources/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |
| `Resources/config/services_twig_component.yaml` | Twig component services | FR-UX-001 |
| `Resources/views/components/qr_code.html.twig` | Component template | FR-UX-001, FR-TWIG-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 14 | 14 |
| Resources | 3 | 3 |
| **Total production sources** | **17** | **17** |
