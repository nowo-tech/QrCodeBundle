# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/qr-code-bundle`  
**Last audited**: 2026-10-30

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` are out of Packagist scope unless promoted in the spec.

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoQrCodeBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree + profiles | FR-CFG-001, FR-CFG-002 |
| `DependencyInjection/NowoQrCodeExtension.php` | DI extension | FR-CFG-002, FR-DI-001, FR-DB-001, FR-SEC-004 |
| `DependencyInjection/TablePrefixListener.php` | Doctrine table prefix | FR-DB-001 |
| `DependencyInjection/UrlAllowlistValidationPass.php` | Compile-time allowlist gate | FR-SEC-004 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace + overrides | FR-TWIG-001, FR-TWIG-002 |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | Fail-closed CSRF gate for the admin | FR-ADM-003 |

## Configuration & profiles

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Config/QrCodeProfile.php` | Resolved profile DTO | FR-CFG-001 |
| `Config/ProfileResolver.php` | Profile name resolution | FR-CFG-001, FR-CFG-003, FR-WRK-001 |

## Rendering & public API

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Enum/QrErrorCorrection.php` | Error-correction enum | FR-QR-002 |
| `Enum/CssFramework.php` | Deprecated `web_ui.css_framework` values | FR-ADM-001 |
| `QrCode/QrCodeDataUriRenderer.php` | PNG data URI renderer | FR-QR-001, FR-QR-002 |
| `Service/QrCodeService.php` | Public API | FR-SVC-001 |

## Security & URL policy

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Security/QrUrlPolicy.php` | URL scheme + allowlist | FR-SEC-001, FR-SEC-002 |
| `Security/QrCodeAccessCheckerInterface.php` | Admin access contract | FR-SEC-004 |
| `Security/ConfigurableQrCodeAccessChecker.php` | Role-based admin gate | FR-SEC-004 |
| `Security/AllowAllQrCodeAccessChecker.php` | Demo-only permissive gate | FR-SEC-004 |
| `Exception/InvalidQrUrlException.php` | Policy failures | FR-SEC-003 |
| `Exception/InvalidQrProfileException.php` | Unknown profile name | FR-CFG-003 |

## Entity & persistence (optional DB profiles)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Entity/QrCodeProfileConfig.php` | Doctrine profile entity | FR-DB-001, FR-DB-002 |
| `Repository/QrCodeProfileConfigRepository.php` | Profile lookup | FR-DB-002, FR-WRK-001 |
| `EventSubscriber/ClosedEntityManagerSubscriber.php` | Worker EM hygiene (DB mode) | FR-WRK-001 |

## Admin CRUD

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/QrCodeProfileAdminController.php` | Admin routes + CRUD | FR-ADM-001, FR-SEC-004 |
| `Service/QrCodeProfileAdminService.php` | Seed/import/sync profiles | FR-ADM-001, FR-DB-002 |
| `Form/QrCodeProfileConfigType.php` | Admin form type | FR-ADM-001 |
| `Ui/QrCodePages.php` | Layout / per-page template resolution, 1.x layout bridge | FR-ADM-002 |
| `Ui/QrCodePageFactory.php` | UiKit view models of the admin pages | FR-ADM-002 |

## Twig & UX component

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Twig/QrCodeExtension.php` | Twig helpers | FR-TWIG-001 |
| `Twig/Component/QrCode.php` | UX Twig component `NowoQrCode` | FR-UX-001 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Core service wiring | FR-DI-001, FR-SEC-004 |
| `Resources/config/services_database.yaml` | Doctrine services | FR-DB-001, FR-DB-002, FR-WRK-001 |
| `Resources/config/services_twig_component.yaml` | Twig component services | FR-UX-001 |
| `Resources/config/routing.yaml` | Admin routes | FR-ADM-001 |

## Twig views (`src/Resources/views/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/components/qr_code.html.twig` | Component template | FR-UX-001, FR-TWIG-001 |
| `Resources/views/admin/_legacy_layout.html.twig` | Deprecated 1.x layout bridge | FR-ADM-002, FR-TWIG-002 |
| `Resources/views/admin/index.html.twig` | Admin profile list (UiKit list scaffold) | FR-ADM-001, FR-ADM-002 |
| `Resources/views/admin/form.html.twig` | Admin create/edit form (UiKit form scaffold) | FR-ADM-001, FR-ADM-002 |

## Translations (`src/Resources/translations/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoQrCodeBundle.{en,es,fr,it,pt,de,nl}.yaml` | Admin UI catalogue | FR-ADM-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle & DI | 7 | 7 |
| Configuration & profiles | 2 | 2 |
| Rendering & public API | 4 | 4 |
| Security & URL policy | 6 | 6 |
| Entity & persistence | 3 | 3 |
| Admin CRUD | 5 | 5 |
| Twig & UX component | 2 | 2 |
| Symfony config | 4 | 4 |
| Twig views | 4 | 4 |
| Translations | 7 | 7 |
| **Total production sources** | **44** | **44** |
