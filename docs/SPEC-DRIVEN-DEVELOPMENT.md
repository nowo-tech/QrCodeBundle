# Spec-driven development

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of production code in `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **QrCodeBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit** and **PHPStan** enforce contracts in CI where applicable.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles so changes to scripts stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); tests and static analysis are the mechanical proof alongside this document.

---

## User stories

| ID | Story |
| --- | --- |
| US-01 | **As a** developer, **I want** PNG QR codes as data URIs **so that** I can embed them in Twig/HTML without a separate image host. |
| US-02 | **As an** integrator encoding wallet/download links, **I want** http(s)-only URL policy **so that** `javascript:` / `data:` payloads cannot be QR-encoded via the URL helpers. |
| US-03 | **As an** integrator, **I want** named profiles (`default_profile` + `profiles`) **so that** size, ECC, and allowlists can differ per use case. |
| US-04 | **As an** integrator, **I want** host/path allowlisting per profile **so that** only approved domains or URL paths can be encoded with `createDataUriForUrl`. |
| US-05 | **As a** maintainer, **I want** PHPUnit + PHPStan + CS in CI **so that** configuration validation and rendering stay stable. |

**Out of scope for these stories:** admin UI, Twig template overrides, Doctrine, Messenger, outbound HTTP clients.

---

## Bundle functional scope

**Goal:** Symfony bundle providing PNG QR data-URI generation with optional URL safety policy.

**In scope**

- Documented integration (see root `README.md` and `docs/`).
- Configuration and runtime behavior described in [`CONFIGURATION.md`](CONFIGURATION.md) and [`USAGE.md`](USAGE.md).
- Consumer-facing change notes in [`CHANGELOG.md`](CHANGELOG.md) and [`UPGRADING.md`](UPGRADING.md) when applicable.

**Explicit non-goals**

- Behavior not documented here or in linked integrator docs.
- A `demo/` tree is optional and not part of the Packagist contract.

---

## Validating the functional spec

- Run **`composer qa`** and/or **`make qa`** / **`make release-check`** as documented in [`CONTRIBUTING.md`](CONTRIBUTING.md) (Docker-based flows may apply).
- Run **PHPUnit** and **PHPStan** in CI and locally for code changes.
- New or changed behavior should add or adjust **tests** under `tests/`.

---

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| REQ-GIT-001 | `Makefile` `setup-hooks` / `check-no-cursor-coauthor` | No Cursor co-author trailers |
| REQ-MAKE-008 | `Makefile` `update-deps` include | Optional monorepo update-deps |
| REQ-MAKE-010 | `Makefile` Compose detection | Docker Compose V2 preference |
| REQ-REL-003 | `Makefile` `check-open-prs` | Block release when open PRs remain |
| REQ-TEST-011 | `Makefile` `demo-smoke` | Demo HTTP 200 when demos exist |

When you change scripted behavior, **update the existing `REQ-*` comment** if the ID still matches the rule, or **add a new `REQ-*`** and document it here and in the PR description.

---

## Suggested workflow for contributors

1. **Clarify behavior** in an issue or draft PR.
2. **Implement** with tests and static analysis.
3. **Anchor scripts** when Makefile UX changes: add or adjust `REQ-*` comments and this table.
4. **Ship integrator docs** when behavior or configuration changes.
5. **Keep Spec Kit artifacts in sync** when production code under `src/` changes:
   - Update [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md).
   - Follow the maintainer checklist in [`SPEC-KIT.md`](SPEC-KIT.md).

---

## GitHub Spec Kit (summary)

See [`SPEC-KIT.md`](SPEC-KIT.md) for install/init/Cursor skill usage. Baseline feature: [`specs/001-baseline/`](../specs/001-baseline/).
