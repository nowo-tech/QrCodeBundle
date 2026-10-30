# Templates

Pages of QrCodeBundle on the UiKit page contract (REQ-UI-003, UiKit 2.17). The admin CRUD
(`/admin/qr-code-profiles`, enabled with `use_database_config: true`) has two pages.
`Nowo\QrCodeBundle\Ui\QrCodePageFactory` builds the UiKit view models (`Nowo\UiKitBundle\View\*`) for the
controller; `Nowo\QrCodeBundle\Ui\QrCodePages` resolves the layout (`web_ui.layout_template`, null =
`nowo_ui_kit.layouts.admin`) and the per-page overrides (`web_ui.templates.<page>`). Both pages extend a UiKit scaffold
and fill data blocks only. Contract test: `tests/Integration/Contract/PageContractTest.php` (UiKit
`PageContractTestCase`, strict missing-translation rule). Twig namespace: `@NowoQrCodeBundle` (views in
`src/Resources/views/`).

## Table of contents

- [Pages](#pages)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Bundle partials](#bundle-partials)
- [Translations](#translations)
- [Layout bridge (1.x layouts)](#layout-bridge-1x-layouts)
- [Allowed exceptions](#allowed-exceptions)

## Pages

Every view model passes `bundle: 'NowoQrCodeBundle'` (theme exception `nowo_ui_kit.theme.bundles.NowoQrCodeBundle`)
and `layout: QrCodePages::layout()`. Labels are `NowoQrCodeBundle` translation keys (`QrCodePages::label()`). The
delete and import actions are POST + CSRF-only `FormView`s (field `_token`); deletes sit behind the UiKit confirm
`<dialog>` (`View\Confirm`).

| Page (`web_ui.templates` id) | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Profiles (`index`) | `admin/index.html.twig` | `page/list` | `ListPage` (header `nowo_qr_code.admin.index.title` + intro, actions New profile (`create`) and Import from YAML (`import`, POST + CSRF FormView, `data-testid="qr-code-import-yaml"`); columns name (required), size, margin (priority 2), error correction (priority 2), URL allowlist (priority 3); rows = `list<QrCodeProfileConfig>`; row actions Edit / Delete (Confirm `nowo_qr_code.admin.confirm.*`); `Pagination` (`web_ui.list_page_size`); `EmptyState` `nowo_qr_code.admin.index.empty` with New profile; `data-testid="qr-code-profiles"`) | `nowo_ui_list_filters` (`use_database_config: false` notice on the `flash_warning` slot, YAML baseline names on the `muted` / `code` slots, then `{{ parent() }}`), `nowo_ui_list_row` (name on `code` + `badge_info` "Overrides YAML" / `badge_neutral` "Database only"; error correction label; allowlist patterns on `code`, empty = `muted` "Any http(s) URL") |
| Profile new / edit (`form`) | `admin/form.html.twig` | `page/form` | `FormPage` (header `nowo_qr_code.admin.form.new_title` / `edit_title`, intro = `new_intro` / the profile name, back link to the list (`data-testid="qr-code-back"`); form = `QrCodeProfileConfigType` (FormKit profile `qr_code`); submit `nowo_qr_code.admin.form.save`; cancel = list; on edit the delete zone with the Delete action; `data-testid="qr-code-profile-form"`) | `nowo_ui_form_body` (`use_database_config: false` notice on the `flash_warning` slot, then `{{ parent() }}` = the REQ-TWIG-003 child loop) |

Other variables passed by the controller (for host templates set through `web_ui.templates.<page>`): `profiles`
(index), `yaml_profiles` (`list<string>`, index), `profile`, `is_new` (form), `use_database_config` (both), plus the
bridge variables `nowo_qr_code_legacy_layout` / `nowo_qr_code_legacy_blocks`.

## Unique pages

- (none) — both admin pages extend a UiKit scaffold. `components/qr_code.html.twig` is the markup of the `NowoQrCode`
  Twig component (an `<img>`), not a page.

## Per-page overrides

- `nowo_qr_code.web_ui.templates.<page>` (`index`, `form`): a host template that extends
  `@!NowoQrCodeBundle/admin/<page>.html.twig` and overrides a data block (`nowo_ui_list_row`, `nowo_ui_list_filters`,
  `nowo_ui_form_body`, …). Unknown page ids fail the container compilation. Prefer theming the UiKit slots
  (`nowo_ui_kit.theme`) and scaffolds once for every bundle.
- Record such overrides in the application's override inventory.

## Overriding templates (REQ-TWIG-001)

Any bundle template can be replaced by a file at `templates/bundles/NowoQrCodeBundle/<subpath>` (same relative path as
under `src/Resources/views/`). `TwigPathsPass` registers the application directory first (`prependPath`) and the bundle
views after it (`addPath`), so **an application file at the override path always wins**.

**Freeze rule.** An application file at the override path always wins and will not pick up vendor changes for that
path until you remove or merge it: after `composer update` the application keeps rendering its own copy.
Upgrade-safe customisation, in this order:

| Depth | How | On a QrCodeBundle upgrade |
| --- | --- | --- |
| Preferred — no Twig fork | `nowo_ui_kit.layouts.admin` (or `web_ui.layout_template`), `nowo_ui_kit.theme` (class / attribute maps, tokens, `theme.bundles.NowoQrCodeBundle`), translations (`translations/NowoQrCodeBundle.<locale>.yaml`), the FormKit profile `qr_code`, CSS on `nowo-ui-*` / `nowo-qr-code-*` / `--nowo-ui-*` | Vendor templates and fixes apply automatically |
| Surgical | `web_ui.templates.<page>` with a thin `{% extends '@!NowoQrCodeBundle/admin/<page>.html.twig' %}` overriding one data block (with `{{ parent() }}` where possible) | Only that block is frozen |
| Full-file override | Copy a template into `templates/bundles/NowoQrCodeBundle/<subpath>` | That `<subpath>` is frozen until removed or merged |

**Procedure.**

1. Find the `<subpath>` in the table below (path relative to `src/Resources/views/`).
2. Create `templates/bundles/NowoQrCodeBundle/<subpath>` in the application (same file name).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.
4. Treat the file as a fork: it does not change when the bundle ships a new version of that `<subpath>`.

Example: to change the `<img>` markup of the Twig component, create
`templates/bundles/NowoQrCodeBundle/components/qr_code.html.twig`.

**Recovering upstream after a freeze.** Delete (or rename aside) the application file, clear the Twig cache
(`php bin/console cache:clear`), then re-apply only surgical customisations if still needed. Diff your copy against
the new vendor file under `vendor/nowo-tech/qr-code-bundle/src/Resources/views/<subpath>` before re-copying anything.

**Overridable subpaths.**

| `<subpath>` | Purpose |
| --- | --- |
| `admin/index.html.twig` | Profiles list (list scaffold) |
| `admin/form.html.twig` | Profile create / edit (form scaffold) |
| `admin/_legacy_layout.html.twig` | Deprecated 1.x layout bridge |
| `components/qr_code.html.twig` | `NowoQrCode` Twig component markup (`<img>`) |

Renaming or removing a `<subpath>` or a data block listed here is a breaking change (see [UPGRADING](UPGRADING.md)).

## Bundle partials

The bundle ships no layout, no local pagination, flash, confirm, modal, page header or empty-state partial and no
inline script: the scaffolds and the UiKit partials (`action`, `confirm`, `empty`, `flashes`, `pagination`,
`page_header`, `scripts`) render them, and the host layout loads the CSS framework.

## Translations

Domain `NowoQrCodeBundle` (REQ-I18N-003), catalogues `src/Resources/translations/NowoQrCodeBundle.<locale>.yaml` for
en, es, fr, it, pt, de and nl (same keys; `make validate-translations`). Keys live under `nowo_qr_code.admin.*`
(page titles, columns, actions, confirm dialog, flashes, form labels / help, error-correction labels). Override any key
in the application (`translations/NowoQrCodeBundle.<locale>.yaml`); see [USAGE](USAGE.md#translations).

## Layout bridge (1.x layouts)

`web_ui.layout_template` may still point at a 1.x layout (no `nowo_ui_page_header` block, e.g. the project
`base.html.twig` rendering `body`, or a copy of the removed `admin/layout.html.twig`): `QrCodePages` then wraps it in
`admin/_legacy_layout.html.twig` (maps the canonical blocks into `body`, `title`, `stylesheets`, `javascripts`) and
triggers a deprecation. When the 1.x layout renders `nowo_ui_flashes` itself (a copy of the removed bundle layout), the
flashes are not printed twice and the page header is printed there. The value
`@NowoQrCodeBundle/admin/layout.html.twig` (the 1.x default) is mapped to null. The bridge is removed in 2.0.

## Allowed exceptions

- (none) — the contract test declares no `allowedClasses()` and no `ignoredMissingTranslations()`.
