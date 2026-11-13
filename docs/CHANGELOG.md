# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.13.1] - 2026-11-13](#1131---2026-11-13)
  - [Fixed](#fixed)
  - [Changed](#changed)
- [[1.13.0] - 2026-11-11](#1130---2026-11-11)
  - [Added](#added)
  - [Fixed](#fixed-1)
  - [Deprecated](#deprecated)
  - [Tests](#tests)
- [[1.12.0] - 2026-11-11](#1120---2026-11-11)
  - [Fixed](#fixed-2)
  - [Added](#added-1)
- [[1.11.1] - 2026-10-30](#1111---2026-10-30)
  - [Fixed](#fixed-3)
- [[1.11.0] - 2026-10-30](#1110---2026-10-30)
  - [Changed](#changed-1)
  - [Added](#added-2)
  - [Deprecated](#deprecated-1)
  - [Removed](#removed)
  - [Security](#security)
- [[1.10.0] - 2026-10-28](#1100---2026-10-28)
  - [Security](#security-1)
- [[1.9.1] - 2026-10-28](#191---2026-10-28)
  - [Security](#security-2)
- [[1.9.0] - 2026-10-28](#190---2026-10-28)
  - [Security](#security-3)
- [[1.8.1] - 2026-10-24](#181---2026-10-24)
  - [Changed](#changed-2)
- [[1.8.0] - 2026-10-22](#180---2026-10-22)
  - [Added](#added-3)
  - [Changed](#changed-3)
  - [Changed](#changed-4)
  - [Documentation](#documentation)
- [[1.7.2] - 2026-10-18](#172---2026-10-18)
  - [Changed](#changed-5)
- [[1.7.1] - 2026-10-13](#171---2026-10-13)
  - [Fixed](#fixed-4)
- [[1.7.0] - 2026-10-13](#170---2026-10-13)
  - [Changed](#changed-6)
  - [Fixed](#fixed-5)
- [[1.6.0] - 2026-10-13](#160---2026-10-13)
  - [Security](#security-4)
  - [Fixed](#fixed-6)
- [[1.5.1] - 2026-10-13](#151---2026-10-13)
  - [Changed](#changed-7)
  - [Dependencies](#dependencies)
- [[1.5.0] - 2026-10-09](#150---2026-10-09)
  - [Changed](#changed-8)
  - [Fixed](#fixed-7)
- [[1.4.10] - 2026-09-27](#1410---2026-09-27)
  - [Added](#added-4)
  - [Changed](#changed-9)
- [[1.4.9] - 2026-09-25](#149---2026-09-25)
  - [Fixed](#fixed-8)
  - [Changed](#changed-10)
- [[1.4.8] - 2026-09-03](#148---2026-09-03)
  - [Fixed](#fixed-9)
  - [Changed](#changed-11)
- [[1.4.7] - 2026-08-24](#147---2026-08-24)
  - [Changed](#changed-12)
  - [Notes](#notes)
- [[1.4.6] - 2026-08-20](#146---2026-08-20)
  - [Security](#security-5)
- [[1.4.5] - 2026-08-19](#145---2026-08-19)
  - [Security](#security-6)
  - [Fixed](#fixed-10)
- [[1.4.4] - 2026-08-19](#144---2026-08-19)
  - [Security](#security-7)
- [[1.4.3] - 2026-08-19](#143---2026-08-19)
  - [Security](#security-8)
- [[1.4.2] - 2026-08-18](#142---2026-08-18)
  - [Changed](#changed-13)
- [[1.4.1] - 2026-08-07](#141---2026-08-07)
  - [Fixed](#fixed-11)
- [[1.4.0] - 2026-08-04](#140---2026-08-04)
  - [Changed](#changed-14)
  - [Added](#added-5)
  - [Changed](#changed-15)
- [[1.3.0] - 2026-08-03](#130---2026-08-03)
  - [Added](#added-6)
  - [Changed](#changed-16)
  - [Documentation](#documentation-1)
- [[1.2.0] - 2026-07-30](#120---2026-07-30)
  - [Added](#added-7)
- [[1.1.0] - 2026-07-30](#110---2026-07-30)
  - [Added](#added-8)
  - [Changed](#changed-17)
- [[1.0.0] - 2026-07-30](#100---2026-07-30)
  - [Added](#added-9)

## [Unreleased]

### Added

- **Interface (REQ-ARCH-001 rule 1):** `Service\QrCodeServiceInterface` (`createDataUri()`, `createDataUriForUrl()`). `QrCodeService` implements it, the interface is aliased in the container, and the Twig extension and the `NowoQrCode` component depend on the interface, so a host can replace the alias. Strictly additive; the class service id and defaults are unchanged.

## [1.13.1] - 2026-11-13

### Fixed

- DoctrineBundle 3.4: bundle entities resolved to the default manager when a dedicated manager is configured.
  DoctrineBundle 3.4 `auto_discover_entities` (on by default for a manager without `mappings` and `auto_mapping`, with
  Symfony 7.3+ and doctrine/orm 3.6+) gives that manager's metadata driver chain a default attribute driver, which
  claims every `#[ORM\Entity]` class. With `doctrine.entity_manager` set, `getManagerForClass(QrCodeProfileConfig)`
  then answered the host's default manager, and `QrCodeProfileConfigRepository` queried its database ("no such
  table"). `EntityManagerOptionPass` now also registers a driver that maps nothing for the bundle entity namespace on
  every other manager (`ExcludedEntityNamespaceDriver`, only the public `addDriver()` of the
  `doctrine.orm.<manager>_metadata_driver` chain), so the entity resolves to the configured manager on DoctrineBundle
  2.x, 3.3 and 3.4+. Affected in practice: 1.12.0 to 1.13.0 (every release with `doctrine.entity_manager`; all allow
  `doctrine/doctrine-bundle` `^3.0`) with DoctrineBundle 3.4.0 or later.

### Changed

- Demo uses the UiKit base style (REQ-DEMO-014)

[1.13.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.13.1

## [1.13.0] - 2026-11-11

QR profiles from a host provider port without Doctrine; provider-only mode fails closed; size and margin limits on every source. See [UPGRADING](https://github.com/nowo-tech/QrCodeBundle/blob/v1.13.0/docs/UPGRADING.md#to-1130).

Architecture bar W2 (standard Symfony by default, strict DDD opt-in): rule 8 (configuration through provider ports,
no ORM just for configuration).

### Added

- `Config\QrCodeProfileProviderInterface` (`find()`, `names()`): the source of profiles that override the YAML ones.
  `Config\DoctrineQrCodeProfileProvider` reads the bundle's table (`use_database_config`, unchanged behaviour); the
  new `profile_provider` option points at a host service instead (per tenant, settings store), without Doctrine.
  `profile_provider` and `use_database_config` are exclusive. A host provider is the only profile source and fails
  closed: a name it returns `null` for throws `InvalidQrProfileException` (no YAML fallback whose allow-list could be
  wider; audit LR-Q-L1).
- Profiles from a source (host provider or database rows) are checked against the YAML bounds (size 64–1024, margin
  0–64; `QrCodeProfile::MIN_SIZE` / `MAX_SIZE` / `MAX_MARGIN`): out of bounds throws instead of asking GD for a huge
  image (audit LR-Q-L2).
- USAGE section "Using it from a DDD / hexagonal application" (both paths).

### Fixed

- A host without DoctrineBundle but with `doctrine/orm` installed failed to compile ("Could not find the manager name
  parameter"): the `QrCodeProfileConfig` mapping is now registered only when DoctrineBundle is configured or
  `use_database_config` is on. Standard hosts (DoctrineBundle registered) keep the mapping as before.

### Deprecated

- `ProfileResolver`'s `$profileRepository` argument (removed in 2.0): pass `$profileProvider` (a
  `DoctrineQrCodeProfileProvider`).

### Tests

- `tests/Integration/Ddd/HostProfileProviderKernelTest.php`: no DoctrineBundle, profiles per tenant from a host
  provider; a kept-alive kernel serving 1,200 requests for two tenants through `http_kernel` without the services
  resetter (each request gets its tenant's size and URL allow-list, flat memory).

[1.13.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.13.0

## [1.12.0] - 2026-11-11

New doctrine.entity_manager maps QrCode profiles on a dedicated entity manager; recovery never touches host managers. See [UPGRADING](https://github.com/nowo-tech/QrCodeBundle/blob/v1.12.0/docs/UPGRADING.md#to-1120).

### Fixed

- `ClosedEntityManagerSubscriber` reset **every** closed entity manager of the registry on every main request (and
  detached profile rows from all of them), host managers included. It now resets and clears only the manager of
  `QrCodeProfileConfig` (rule 6 of the bundle architecture bar). Standard hosts with one default manager see no change.

### Added

- `nowo_qr_code.doctrine.entity_manager` (default `null` = the default manager): a dedicated entity manager for
  `QrCodeProfileConfig`. The entity is mapped on that manager only (removed from the others, including
  `auto_mapping`), the admin service and controller read and write through it, and the closed-manager recovery resets
  it. The build fails when the manager does not exist. Audit LD-QR-M1.

[1.12.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.12.0

## [1.11.1] - 2026-10-30

Bundle routes are opt-in again: controllers are no longer autoconfigured, so a host's routing.controllers import does not expose them (REQ-SF-004 rule 7). See [UPGRADING](UPGRADING.md#to-1111).

### Fixed

- **Admin routes registered without being imported (REQ-SF-004 rule 7):** with `use_database_config: true` on Symfony >= 7.3, the `#[Route]` on `QrCodeProfileAdminController` made the autoconfigured service carry the `routing.controller` tag, so a host with the default `controllers: { resource: routing.controllers }` got the `nowo_qr_code_admin_*` routes without importing `@NowoQrCodeBundle/Resources/config/routing.yaml` (still behind the admin access checker). The controller service is no longer autoconfigured (explicit `controller.service_arguments` + `container.service_subscriber` tags); the routes exist only where the host imports them. Regression test with a kernel that only loads `routing.controllers`.

[1.11.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.11.1

## [1.11.0] - 2026-10-30

QrCode: UiKit page-contract admin + translations, CsrfProtectionPass; SEC-004 BK: host-only #regex#, url#^…# opt-in, export-ignore, admin routes only with use_database_config See [UPGRADING](UPGRADING.md#to-1110).

Admin pages on the UiKit page contract (REQ-UI-003), admin translations in seven locales, fail-closed CSRF gate. See
[UPGRADING](UPGRADING.md#unreleased).

### Changed

- **UiKit page contract (REQ-UI-003).** The profile admin pages extend the UiKit scaffolds: `admin/index.html.twig` the
  list scaffold (`View\ListPage`), `admin/form.html.twig` the form scaffold (`View\FormPage`, delete zone on edit),
  fed by view models built in the new `Nowo\QrCodeBundle\Ui\QrCodePageFactory`. The layout is
  `nowo_ui_kit.layouts.admin` (`web_ui.layout_template`, now `null` by default); delete runs through the UiKit confirm
  `<dialog>` (no inline `onsubmit="confirm()"`), pagination / flashes / empty state / page header come from the UiKit
  partials. Requires `nowo-tech/ui-kit-bundle` ^2.17.3 and `nowo-tech/form-kit-bundle` ^2.15.
- **Admin UI translated.** Titles, columns, actions, confirm dialog, flashes and form labels / help are
  `NowoQrCodeBundle` keys (`nowo_qr_code.admin.*`) with catalogues for en, es, fr, it, pt, de and nl
  (`make validate-translations`: syntax, required locales, key parity; also in CI).
- The FormKit profile `qr_code` seeded by the bundle no longer carries Bootstrap classes (`form-control`, `mb-2`,
  `form-select`, …): fields get `nowo-ui-input`, the host form theme styles them; it only applies the help /
  placeholder convention keys the catalogue defines (`convention_requires_translation`), so no raw
  `qr_code_profile_config.*` keys reach the form.
- The bundle no longer seeds `nowo_ui_kit.css_framework` / `icon_set`: only a host-set (deprecated)
  `web_ui.css_framework` is copied to `nowo_ui_kit.css_framework`.
- Demo: database profiles on SQLite (seeded by `bin/console app:demo:setup`) and the admin in `dev`, Bootstrap 5 form
  theme, host admin layout `templates/layouts/admin.html.twig`; Playwright e2e for the admin (list, create, edit,
  delete through the confirm dialog, invalid form 422, YAML import) and two new README screenshots.

### Added

- `web_ui.templates.<page>` (`index`, `form`): per-page template overrides; unknown page ids fail the compilation.
- `docs/TEMPLATES.md`: pages, scaffolds, view models, data blocks, overridable subpaths (REQ-TWIG-001 freeze rule).
- `PageContractTest` (UiKit `PageContractTestCase`, strict missing-translation rule) and `LegacyLayoutBridgeTest`.

### Deprecated

- `web_ui.css_framework` (set `nowo_ui_kit.css_framework`) and the Twig globals `nowo_qr_code_layout_template` /
  `nowo_qr_code_css_framework` (the pages no longer read them); removed in 2.0.
- A `web_ui.layout_template` without the UiKit canonical blocks (a 1.x layout rendering `body`) is wrapped in the
  bridge `admin/_legacy_layout.html.twig` and triggers a deprecation; the bridge is removed in 2.0.

### Removed

- `admin/layout.html.twig` (standalone full-HTML layout with inline CSS) and `admin/base.html.twig` (page shell); the
  1.x default `web_ui.layout_template` value `@NowoQrCodeBundle/admin/layout.html.twig` is mapped to `null`.

### Security

- **CSRF fails closed.** With `use_database_config: true` the container refuses to compile without CSRF protection
  (`security.csrf.token_manager`, the form CSRF extension, `framework.form.csrf_protection` not disabled): the new / edit
  form check could otherwise be silently off (REQ-SEC-005).
- **`url_allowlist` regexes match the host only (SEC-004 Appendix BK, L1).** A `#…#` pattern was tried against the
  host and then the full URL, so `#good\.com#` allowed `https://evil.tld/?x=good.com`. `#…#` now applies to the
  parsed host only; full-URL regexes need the explicit prefix `url#` and must be anchored (`url#^https://…#`; an
  unanchored one never matches). Both the YAML configuration and the admin form reject invalid patterns (non-compiling,
  nested quantifiers, `/` in a host regex, unanchored `url#` regex). See [UPGRADING](UPGRADING.md#url-allowlist-regexes).
- **Dist archive trimmed (SEC-004 Appendix BK, L2).** `.gitattributes` `export-ignore`s the demo, tests, CI, specs,
  tooling and dev configuration: `composer require` no longer installs them under `vendor/`.
- **Admin routes only in database mode (SEC-004 Appendix BK, I1).** `@NowoQrCodeBundle/Resources/config/routing.yaml`
  (imported by the Flex recipe) now goes through `Routing\AdminRouteLoader`, which imports the admin routes only when
  `use_database_config` is `true`: with the default `false`, `/admin/qr-code-profiles` is a 404 instead of a 500.
- `QrCodeProfileAdminController` is `final` (SEC-004 Appendix BK, I5).

[1.11.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.11.0

## [1.10.0] - 2026-10-28

Security: allow_unauthenticated is refused in production (prod or non-debug kernels). See [UPGRADING](UPGRADING.md#to-1100).

### Security

- **`allow_unauthenticated` refused in production.** The switch opens the QR code profile admin to anonymous users; the
  container now refuses to compile with it in the `prod` environment or in any non-debug kernel outside `test*`
  environments (as ContactFormBundle).

[1.10.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.10.0

## [1.9.1] - 2026-10-28

Security: url_allowlist_required applies to every profile; admin regex patterns are validated. See [UPGRADING](UPGRADING.md#to-191).

### Security

- **`url_allowlist_required` applies to every profile.** The compile-time check only saw the YAML default profile;
  other YAML profiles and database profiles edited in the admin could encode any URL. `createDataUriForUrl()` now
  refuses a profile without an allowlist when `url_allowlist_required` is true.
- **Admin regex patterns are validated.** `#…#` allowlist patterns that do not compile or nest quantifiers (ReDoS on
  every render) are rejected by the profile form; an invalid stored pattern never matches (no warning / 500).

[1.9.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.9.1

## [1.9.0] - 2026-10-28

Security: URLs whose authority PHP and QR scanners read differently (backslash, userinfo, control chars) are rejected. See [UPGRADING](UPGRADING.md#to-190).

### Security

- **URL allowlist bypass through ambiguous authorities.** `QrUrlPolicy` took the host from PHP `parse_url()`, which
  reads backslashes and userinfo differently from browsers and QR scanners: `https://evil.tld\.good.com/` (host
  `evil.tld\.good.com` for PHP) and `https://evil.tld\@good.com/` (host `good.com` for PHP) passed a `good.com`
  allowlist but open `evil.tld` on a phone, defeating the anti-phishing allowlist for user-supplied URLs. URLs with a
  backslash, control character or whitespace, with userinfo (`user@`, `user:pass@`), or whose host is not a plain DNS
  name / IPv4 / bracketed IPv6 literal (percent-encoded or Unicode hosts) are now rejected, with or without an
  allowlist. See [UPGRADING](UPGRADING.md#unreleased).

[1.9.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.9.0

## [1.8.1] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-181).

### Changed

- Admin view `admin/form.html.twig` renders the profile form through the child loop (`{% for child in form %}` + `not child.rendered` + `form_row(child)`) instead of one `form_row(form.<field>)` per field (REQ-TWIG-003). Same fields and order (from `QrCodeProfileConfigType`); new fields now render without overriding the template.

[1.8.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.8.1

## [1.8.0] - 2026-10-22

Dependency refresh, paginated admin profile list, Symfony action forms, coverage over the whole src, raw-form gate and demo smoke workflow. See [UPGRADING](UPGRADING.md#to-180).

### Added

- `nowo_qr_code.web_ui.list_page_size` (default 50): the admin profile list is paginated (`?page=N`) instead of loading every profile (REQ-PERF-001).

### Changed

- The admin import-YAML and delete buttons are Symfony forms built by the controller (same `_token` ids), so the views contain no raw `<form>` / `<input>`; `release-check` starts with `ensure-up` and runs the new `check-no-raw-html-form` (REQ-TWIG-005, REQ-MAKE-002). The controller receives the form factory through its constructor (autowired); the admin CRUD needs `symfony/security-csrf` (now listed in `suggest`, and in `require-dev` for the tests).
- Coverage now includes `src/Controller` and `src/Repository` (no `<source>` exclusions) (REQ-TEST-003); the last docblock `@covers` became `#[CoversClass]` (REQ-TEST-010); new `Demo smoke` workflow (REQ-TEST-011).
- `docs/SECURITY.md` describes the admin CRUD exposure and flags the AI review as due for a re-audit (REQ-SEC-001); PSR.md gives the real PSR-3 reason (REQ-CS-007); the co-author check accepts git worktrees.

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).

### Documentation

- Documentation: wave-1 REQ compliance — canonical issue and pull request templates, canonical FrankenPHP claim and demo section moved out of the README preamble, documentation list order (PSR under Additional documentation), CONTRIBUTING Code of Conduct link and Cursor Agent queue settings (REQ-IDE-006), DEMO-FRANKENPHP dev/production start and troubleshooting, demo compose project name `qr-code-bundle-demo-symfony-8`, tables of contents on long docs.

[1.8.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.8.0

## [1.7.2] - 2026-10-18

Allows nowo-tech/ui-kit-bundle 2.x (^1.4 || ^2.2) so hosts can move to UiKit 2.2. See [UPGRADING](UPGRADING.md#to-172).

### Changed

- Allows nowo-tech/ui-kit-bundle 2.x (`^1.4 || ^2.2`); the dev lock and the demo lock use 2.2.0. Templates are unchanged (compatibility release). With UiKit 2.x `nowo-ui.css` ships in `@layer nowo-ui`, so unlayered host/framework CSS wins equal-specificity conflicts with kit rules in the admin pages; to keep the 1.x cascade, override the admin `stylesheets` block and link `css/nowo-ui.unlayered.css` (UiKit 2.x only).

[1.7.2]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.7.2

## [1.7.1] - 2026-10-13

### Fixed

- `url_allowlist` path patterns with a scheme (e.g. `https://good.com/Docs/`) no longer lowercase the path prefix, which made them never match mixed-case paths. Only the scheme and host are case-insensitive.

[1.7.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.7.1

## [1.7.0] - 2026-10-13

### Changed

- **Requirements:** Symfony floor raised from `^7.0` to `^7.4` (`^7.4 || ^8.0`), matching what CI actually tests; the redundant Symfony `7.0` CI matrix cell (which resolved to the latest 7.x anyway) was removed.

### Fixed

- **Admin CRUD:** deleting a profile with an invalid CSRF token now shows an `Invalid CSRF token.` error flash (same as the YAML import action) instead of silently redirecting to the list.
- **Docs:** `QrUrlPolicy` constructor docblock no longer claims an empty allowlist restricts URLs to *public* hosts; it allows any http(s) URL with a host (no public/private host or IP check).

[1.7.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.7.0

## [1.6.0] - 2026-10-13

### Security

- **Admin access (behaviour change):** `security.access_roles: []` now **denies** everyone (fail-closed) instead of opening `/admin/qr-code-profiles` to all users. Use `security.allow_unauthenticated: true` (demo/dev only) or a custom `access_checker` to open it.
- **URL allowlist (behaviour change):** patterns containing `/` are matched against the parsed URL (host exact/subdomain, optional scheme/port, path prefix) instead of a substring of the whole URL, closing bypasses such as `https://evil.tld/?r=good.com/path`. Path-only patterns (`/wallet/`) are now path prefixes; paths with dot segments never match.

### Fixed

- **Admin CRUD:** `QrCodeProfileConfig` declares `#[UniqueEntity('name')]`, so a duplicate profile name shows a form error instead of a 500 (unique constraint violation).

[1.6.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.6.0

## [1.5.1] - 2026-10-13

### Changed

- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `nowo-tech/form-kit-bundle` 2.6.1 → 2.7.1 (development lock; #19).
- `nowo-tech/ui-kit-bundle` 1.9.1 → 1.11.0 (development lock; #20).

[1.5.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.5.1

## [1.5.0] - 2026-10-09

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder `orderBy`/`addOrderBy` with `SortDirection::Ascending`/`Descending` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313); require `doctrine/orm` `^3.7` where applicable.
- **Composer:** `conflict: doctrine/orm <3.7` (Doctrine stays optional via `suggest`), so optional Doctrine installs always provide `SortDirection`.
- Dependencies (Dependabot + lock refresh): FormKitBundle 2.6.1, UiKitBundle 1.9.1; dev `doctrine/orm` 3.7.3, `igor-php/igor-php` 0.10, PHPStan 2.3.1 (+ phpunit/symfony extensions 2.1), Rector 2.7.0, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo (Symfony 8): FormKitBundle 2.6.1, UiKitBundle 1.9.1, Twig 3.30.0, HotReloadBundle 1.5.5, TwigInspectorBundle 1.1.7; regenerated `config/reference.php`.

### Fixed

- Tests: repair the `SortDirection` QueryBuilder mock in `QrCodeProfileConfigRepositoryTest`.

[1.5.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.5.0

## [1.4.10] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.4.10]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.10

## [1.4.9] - 2026-09-25

### Fixed

- **FrankenPHP worker mode (`reset_kernel` false / no `services_resetter`), `use_database_config: true`:** `ProfileResolver` reads database profiles with array hydration (`QrCodeProfileConfigRepository::findProfileArrayByName()`) instead of managed entities, so admin edits (including a tightened `url_allowlist`) made in any worker apply on the next render instead of being hidden by a stale identity map.
- **FrankenPHP worker mode (database mode):** `ClosedEntityManagerSubscriber` resets a closed Doctrine manager on every main request and detaches managed `QrCodeProfileConfig` rows so admin CRUD stays fresh; `findAllOrderedByName()` also uses `Query::HINT_REFRESH`.

### Changed

- **Docs:** FrankenPHP worker audit (`docs/FRANKENPHP-WORKER-AUDIT.md`), UPGRADING notes, README / demo / configuration pointers; baseline spec `FR-WRK-001` + code inventory.

[1.4.9]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.9

## [1.4.8] - 2026-09-03

### Fixed

- Apply Rector `??=` null-coalesce assignment in `NowoQrCodeBundle::getContainerExtension()` so `composer rector-dry` stays clean on Rector 2.6.4+.

### Changed

- **Deps (dev):** bump `vincentlanglet/twig-cs-fixer`; refresh Composer lockfiles.

[1.4.8]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.8

## [1.4.7] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Refactor:** apply `ReadOnlyClassRector` for PHP 8.2 readonly classes.
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.4.7]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.7

## [1.4.6] - 2026-08-20

### Security

- **Flex recipe `when@prod`:** stop shipping `example.com` as a silent allowlist. `url_allowlist_required: true` now fails compilation when the default profile allowlist is empty **or** only the placeholder `example.com` / `www.example.com`. Replace with your production hosts before `cache:clear --env=prod`.

[1.4.6]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.6

## [1.4.5] - 2026-08-19

### Security

- **`url_allowlist_required` compiler pass:** ships on `main` with Flex recipe `when@prod` flag and validation tests (REQ-SEC-004). Prefer **`^1.4.5`** over `v1.4.4` if you rely on compile-time allowlist enforcement.

### Fixed

- **Tests:** coverage for `UrlAllowlistValidationPass` and `Configuration`/`NowoQrCodeExtension` wiring.

[1.4.5]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.5

## [1.4.4] - 2026-08-19

### Security

- **`url_allowlist_required`:** when `true`, container compilation fails if the default profile `url_allowlist` is empty (REQ-SEC-004). Flex recipe `when@prod` enables this flag with a placeholder allowlist.

[1.4.4]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.4

## [1.4.3] - 2026-08-19

### Security

- **Flex recipe `when@prod`:** `url_allowlist` placeholder for SSRF-safe QR generation in production.
- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

[1.4.3]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.3

## [1.4.2] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.4.2]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.2

## [1.4.1] - 2026-08-07

### Fixed

- CI coverage gate: raise measured coverage to **100%** with unit tests for FormKit / UiKit `prepend` seeding branches in `NowoQrCodeExtension`.

[1.4.1]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-08-04

### Changed

- **FormKitBundle:** depend on [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0. Admin form types use `FormOptionsTrait` + profile `qr_code` (`#[FormKitConfig]`). Extension prepends that profile when missing; form types are tagged `form.type` so `FormOptionsMerger` is injected.
- **UiKit:** Admin templates use `ui.btn` / `ui.row_actions` macros with `nowo_qr_code_css_framework` for toolbar, row, and form actions (keeps `qr-*` hooks).

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Requires **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** (`nowo-tech/ui-kit-bundle` `^1.4`). Admin `base.html.twig` loads `asset('css/nowo-ui.css', 'nowo_ui_kit')` and imports `@NowoUiKitBundle/macros/ui.html.twig` (flashes via `ui.flash`, admin index create via `ui.btn`). Extension seeds `nowo_ui_kit` defaults from `web_ui.css_framework` (and `bootstrap-icons` when `icon_set` is unset) when the host has not configured UiKit.

[1.4.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.4.0

## [1.3.0] - 2026-08-03

### Added

- Admin Web UI look-and-feel (REQ-UI-001): `web_ui.css_framework` (`bootstrap5` / alias `bootstrap`, `bootstrap4`, `tabler`, `tailwind`, `foundation`, `custom`, `none`) and Twig global `nowo_qr_code_css_framework`.
- `admin/base.html.twig` page shell that extends `web_ui.layout_template` and stacks `nowo_ui_styles` / `nowo_ui_scripts` with `{{ parent() }}`.
- Semantic `nowo-ui-*` hooks on admin markup (alongside legacy `qr-*` classes).
- Symfony **8** FrankenPHP demo under `demo/symfony8` (PHP **8.5** + **gd**, `FRANKENPHP_MODE`, Twig helpers / UX component / `QrCodeService` samples).
- `docs/DEMO-FRANKENPHP.md` + aggregate `demo/Makefile` (`demo-smoke` / `verify`).
- Compile-time guard: when `use_database_config` is true and `security.allow_unauthenticated` is false, SecurityBundle must be present.
- `make release-check` now enforces **100%** PHP line coverage (`coverage-check`).

### Changed

- `web_ui.layout_template` is documented as the host integration point (cannot be empty); Twig global `nowo_qr_code_layout_template` is prepended (leading `@` escaped for DI).
- Admin CRUD templates extend `admin/base.html.twig` instead of the full-HTML layout directly.
- Flex recipe documents `web_ui.layout_template` / `web_ui.css_framework`.
- Dev dependencies: `nowo-tech/phpstan-frankenphp` 1.0.3, `rector/rector` 2.6.0, `friendsofphp/php-cs-fixer` 3.95.18.

### Documentation

- Updated [CONFIGURATION.md](CONFIGURATION.md), [USAGE.md](USAGE.md), [INSTALLATION.md](INSTALLATION.md), [UPGRADING.md](UPGRADING.md), and Spec Kit baseline for Web UI + demo.

## [1.2.0] - 2026-07-30

### Added

- Optional Doctrine profile storage: `use_database_config` + `doctrine.table_prefix`.
- Entity `QrCodeProfileConfig` (table `qr_code_profile`); DB rows with the same name **fully override** YAML profiles; DB-only names are also resolvable.
- Admin CRUD at `/admin/qr-code-profiles` (import from YAML, create/edit/delete) with role gate via `security.access_roles`.
- Flex recipe routes import (`config/routes/nowo_qr_code.yaml`) for the admin CRUD.
- Composer `suggest` entries for `doctrine/orm`, `doctrine/doctrine-bundle`, `symfony/form`, `symfony/validator`, and `symfony/security-bundle`.

## [1.1.0] - 2026-07-30

### Added

- Twig UX component **`NowoQrCode`** via `symfony/ux-twig-component` (`<twig:NowoQrCode content="…" />` / `url` + optional `profile`).
- Twig namespace **`@NowoQrCodeBundle`** and `TwigPathsPass` so apps can override `components/qr_code.html.twig` (REQ-TWIG-001 / REQ-TWIG-002).
- Toolkit-friendly `{# @prop #}` documentation on the component template for Symfony UX Toolkit workflows.
- Composer `suggest` for optional `symfony/ux-toolkit` (PHP 8.4+, experimental) to compose kit UI around the QR image.

### Changed

- Hard dependencies now include `symfony/twig-bundle` and `symfony/ux-twig-component` (`^2.20 || ^3.0`).

## [1.0.0] - 2026-07-30

Initial public release of **nowo-tech/qr-code-bundle**, extracted from [WalletQrBundle](https://github.com/nowo-tech/WalletQRBundle) for standalone PNG QR generation.

### Added

- PNG QR codes as data URIs via `QrCodeService` and `QrCodeDataUriRenderer` (`endroid/qr-code`).
- Named profiles: `default_profile` + `profiles` (REQ-CFG-001 / REQ-CFG-002).
- Flat root keys (`size`, `margin`, `error_correction`, `url_allowlist`) accepted and normalized into `profiles.<default_profile>` for BC with WalletQrBundle-style YAML.
- `ProfileResolver` / `QrCodeProfile`; optional `$profile` on service methods and Twig helpers.
- `QrUrlPolicy`: http(s) only; host exact/subdomain allowlist, path substrings, `#regex`.
- `QrErrorCorrection` enum with strict configuration validation.
- Twig helpers `qr_code_data_uri` and `qr_code_for_url`.
- Flex recipe under `.symfony/recipe/nowo-tech/qr-code-bundle/`.
- FrankenPHP-friendly worker mode claim; full Nowo docs / Spec Kit / CI scaffold.

[1.3.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.3.0
[1.2.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/QrCodeBundle/releases/tag/v1.0.0
