# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/qr-code-bundle` (`symfony-bundle`) |
| Audited revision | `v1.4.9` (post W-01 / W-02 remediation) |
| Audit date | 2026-09-25 |
| Method | Manual review of every PHP file under `src/` (services, controller, Twig extension and component, form type, repository, Doctrine listener, DI extension, compiler passes, `Resources/config/*.yaml`) |
| **Verdict** | ✅ **Compatible under scenario B** — YAML-only is fully stateless; with `use_database_config: true`, render uses array hydration, closed EntityManagers are recovered on every main request, and managed `QrCodeProfileConfig` rows are detached so admin CRUD sees fresh data without a kernel reset |
| Remediation | W-01: `ProfileResolver` + `QrCodeProfileConfigRepository::findProfileArrayByName()`; W-02: `EventSubscriber/ClosedEntityManagerSubscriber` (reset closed managers + detach profile entities; `findAllOrderedByName()` uses `Query::HINT_REFRESH`). Tests under `tests/Unit/Config`, `tests/Unit/Repository`, `tests/Unit/EventSubscriber` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests (`reset_kernel` / equivalent off), so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | All bundle services are `final readonly` or only hold `readonly` dependencies; renderer and URL policy are created per call |
| Static properties / `static` locals | ✅ | None; only static factories/closures |
| `ResetInterface` / `kernel.reset` coverage | ✅ | Nothing to reset on bundle services; database mode no longer depends on Doctrine's `kernel.reset` (W-01, W-02) |
| Request / user / locale captured in services | ✅ | Access checker asks `AuthorizationChecker` per call; no request stored |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config compiled into container parameters |
| Doctrine / EntityManager | ✅ | Render path: array hydration. Closed managers: reset on every main request. Admin path: detach `QrCodeProfileConfig` + `HINT_REFRESH` on list queries. Host entities other than this bundle's profile entity are not cleared |
| Output, headers, `exit`, shutdown functions | ✅ | None |
| Resources (files, sockets, cURL) held open | ✅ | None; QR PNG built in memory |
| Memory growth across requests | ✅ | No caches or accumulating arrays in the bundle |
| Blocking I/O and timeouts | ✅ | No network or process calls; QR rendering is CPU-only (endroid/qr-code + GD) |
| Third-party static state | ✅ | `endroid/qr-code` `Builder`/`PngWriter` created per call; FormKit `FormOptionsTrait::withBuilder()` restores its bound builder in `finally` |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` declares a `worker` block (`file /app/public/index.php`, `watch`).

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\QrCodeBundle\Config\ProfileResolver` | yes | none (`final readonly`); optional repository dependency | ✅ | ✅ (W-01) |
| `Nowo\QrCodeBundle\Service\QrCodeService` | yes | none (`final readonly`); builds `QrCodeDataUriRenderer` and `QrUrlPolicy` per call | ✅ | ✅ |
| `Nowo\QrCodeBundle\Twig\QrCodeExtension` | yes | none (`readonly` service dependency) | ✅ | ✅ |
| `Nowo\QrCodeBundle\Twig\Component\QrCode` (`NowoQrCode`) | **no** (UX `TwigComponentPass` sets `setShared(false)`) | public props set in `mount()`, but a new instance per render | ✅ | ✅ |
| `Nowo\QrCodeBundle\Service\QrCodeProfileAdminService` (DB mode) | yes | none (`final readonly`) | ✅ | ✅ (W-02) |
| `Nowo\QrCodeBundle\Controller\QrCodeProfileAdminController` (DB mode) | yes (controller service) | none (`readonly` dependencies) | ✅ | ✅ (W-02) |
| `Nowo\QrCodeBundle\Repository\QrCodeProfileConfigRepository` (DB mode) | yes | none of its own; no custom cache | ✅ | ✅ (W-01 / W-02) |
| `Nowo\QrCodeBundle\EventSubscriber\ClosedEntityManagerSubscriber` (DB mode) | yes | none (`final readonly`) | ✅ | ✅ |
| `nowo_qr_code.access_checker.*` (`ConfigurableQrCodeAccessChecker` / `AllowAllQrCodeAccessChecker`) | yes | none (`final readonly`) | ✅ | ✅ |
| `Nowo\QrCodeBundle\DependencyInjection\TablePrefixListener` | yes | none (`final readonly` prefix; only `loadClassMetadata`) | ✅ | ✅ |
| `QrCodeProfileConfigType` (form type) | yes | FormKit trait state is config-derived; bound builder restored in `finally` | ✅ | ✅ |

`QrCodeProfile`, `QrCodeDataUriRenderer` and `QrUrlPolicy` are `final readonly` value objects created per call. Compiler passes (`TwigPathsPass`, `UrlAllowlistValidationPass`) only run at container build time.

## Findings

### W-01 — DB-backed profiles can go stale in a worker that is never reset (Medium)

- **Where:** `ProfileResolver` previously called `findOneByName()` (identity map).
- **Worker impact:** under **B**, edits from another worker (including a tightened `url_allowlist`) were invisible until restart.
- **Status:** Resolved — `findProfileArrayByName()` (scalar `SELECT` + `getArrayResult()`). `findOneByName()` kept for BC. Test: `ProfileResolverTest::testConsecutiveRequestsWithoutResetSeeTightenedAllowlist`.

### W-02 — Closed EntityManager / stale admin entities without reset (Medium)

- **Where:** admin flush failures close the shared EM; list/edit used managed entities.
- **Worker impact:** under **B**, later writes failed with "EntityManager is closed"; admin pages could show stale rows.
- **Status:** Resolved — `ClosedEntityManagerSubscriber` on every main request: `resetManager()` when closed, then detach only `QrCodeProfileConfig` from the identity map. `findAllOrderedByName()` also sets `Query::HINT_REFRESH`. Host application entities are not cleared.

No other findings. The YAML-only configuration (default, `use_database_config: false`) never touches Doctrine and is fully stateless under both scenarios.

## Usage recommendations in worker mode

- With the default YAML profiles, no reset hook or special configuration is needed.
- With `use_database_config: true`, the bundle is correct under A and B without relying on Doctrine's `kernel.reset`.
- Custom `security.access_checker` services must stay stateless (or implement `ResetInterface`); do not cache the current user or the decision in a property.
- Do not decorate `QrCodeService` or `ProfileResolver` with an in-memory profile cache unless it is reset per request or invalidated on admin changes.

## Re-audit triggers

Re-run this audit when a change adds: properties or a cache to `ProfileResolver`, `QrCodeService` or the Twig extension; a shared (non-component) service that stores rendered QR codes; new Doctrine listeners; logo/image loading from URLs or files; or any use of `$_SERVER` / `$_ENV` at runtime.
