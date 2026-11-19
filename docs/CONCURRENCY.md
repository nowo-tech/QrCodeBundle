# Concurrency (REQ-RUNTIME-003)

How NowoQrCodeBundle stays correct when two admins, two workers or a retried request write the same QR profile rows at
the same time. Since 1.15.

The bundle writes only when `use_database_config` is true: the profile admin (`/admin/qr-code-profiles`) and its YAML
import. Rendering a QR code only reads (`DoctrineQrCodeProfileProvider`, array hydration, no lock). Hosts that use YAML
profiles or their own `profile_provider` have no write path.

## Table of contents

- [Write paths](#write-paths)
- [Conflict answers and codes](#conflict-answers-and-codes)
- [Host options](#host-options)
- [Worker mode and entity managers](#worker-mode-and-entity-managers)
- [Tests and CI](#tests-and-ci)
- [Residual risks](#residual-risks)

## Write paths

Every write runs in one `QrCodeProfileWriteUnit` (rule 1): one transaction on the manager of `QrCodeProfileConfig`,
flushed before the commit, through nowo-tech/transaction-doctrine's runner.

| Path | Transaction | Concurrency control (rule 4) | Answer when it loses a race |
|------|-------------|------------------------------|-----------------------------|
| Admin create (`POST /new`) | one write unit | unique key `uniq_qr_code_profile_name` + catch | the form again, 409, `X-Error-Code: QR_CODE_PROFILE_DUPLICATE_NAME` |
| Admin edit (`POST /{id}/edit`) | one write unit | optimistic `version` column: the form carries the version it was opened with (`lock(OPTIMISTIC)` before the write) and the UPDATE checks it | the form again, 409, `X-Error-Code: QR_CODE_PROFILE_STALE`; a renamed profile can also meet the unique key (`DUPLICATE_NAME`) |
| Admin delete (`POST /{id}/delete`) | one write unit | none needed: deleting is idempotent for the admin; a delete wins over a concurrent edit | — |
| YAML import (`POST /import-yaml`) | one write unit for all profiles (rows read inside it, all written or none) | unique key + optimistic `version` | 409, `X-Error-Code: QR_CODE_PROFILE_DUPLICATE_NAME` or `QR_CODE_PROFILE_STALE` (host error page); run it again |
| Any of the above meeting a row lock, a deadlock or a serialization failure | — | — | 409 + `CONFLICT_001` / `CONFLICT_002` (below) |

`grep -rn "flush()" src/` has one hit: the flush inside `QrCodeProfileWriteUnit::run()` on the host's manager (the
bundle's own manager is flushed by `DoctrineTransactionRunner::forEntityManager()`).

Business keys compare exactly (rule 5): the profile name is re-checked byte for byte after every lookup
(`QrCodeProfileConfigRepository::findOneByName()`, `findProfileArrayByName()`), so a case-insensitive MySQL / MariaDB
collation never makes `Compact` resolve the `compact` row.

## Conflict answers and codes

`QrCodeProfileConflictListener` (kernel.exception, priority -64) answers for the bundle's admin controller only; host
routes keep the host's error handling.

| Failure | Status | Headers | Body (host error page) |
|---------|--------|---------|------------------------|
| Deadlock, lock wait timeout, serialization failure (MySQL 1213 / 1205, MariaDB 1020, PostgreSQL 40001 / 40P01 / 55P03), with nothing committed in the request | 409 (configurable to 503) | `X-Conflict-Code: CONFLICT_001`, `Retry-After` | "Another change to the QR profiles is being saved at the same time. Retry in a moment." |
| The same after a commit earlier in the same request, or a lost savepoint of unknown outcome | 409 (or 503) | `X-Conflict-Code: CONFLICT_002`, no `Retry-After` | "Part of the change may already be saved. Reload the page before trying again." |
| Unique name already used (YAML import) | 409 | `X-Error-Code: QR_CODE_PROFILE_DUPLICATE_NAME` | fixed message |
| Profile changed since it was read (YAML import) | 409 | `X-Error-Code: QR_CODE_PROFILE_STALE` | fixed message |
| Lost connection, any other database error | not mapped | — | a real error (500) |

- `CONFLICT_001`: nothing was committed; retrying is safe.
- `CONFLICT_002`: part of the request may be committed; read again, do not repeat blindly. "Part committed" covers the
  whole request: transaction-doctrine's request watermark (`ConflictCode::forRequest()`, when its bundle runs with
  `commit_detection`), and the bundle's own commit mark on the ledger its commit-tracking middleware feeds.
- `X-Conflict-Code` only ever carries `CONFLICT_001` or `CONFLICT_002`. Domain refusals use `X-Error-Code` and never a
  conflict code.

The storage error stays the previous exception, for the logs; no SQL or value reaches the client.

## Host options

```yaml
nowo_qr_code:
    conflict_response:
        status: ~          # 409 (default) or 503; null follows nowo_transaction_doctrine.conflict_response, else 409
        retry_after: ~     # seconds of a CONFLICT_001 answer; null follows transaction-doctrine, else 1
        commit_tracking: bundle   # bundle (default) | all | off
```

`commit_tracking` decides which commits count for `CONFLICT_002` when transaction-doctrine's `commit_detection` is off:

- `bundle` (default): transaction-doctrine's `CommitTrackingMiddleware` wraps only the connection of the bundle's entity
  manager (`doctrine.entity_manager`, else the default one). The host's other connections are never wrapped (audit
  UE-CF-1).
- `all`: every Doctrine connection.
- `off`: no middleware; only the failure itself and transaction-doctrine's request mark decide.

When nowo-tech/transaction-doctrine's bundle is registered with `commit_detection`, its `CommitLedger` is reused and the
bundle registers no middleware: no connection is wrapped twice.

## Worker mode and entity managers

FrankenPHP worker mode with kernel reset off, standard and DDD hosts:

- **Host's manager** (the default, `doctrine.entity_manager: null`): an open manager is never cleared, closed or reset
  (rule 2). The unit refuses to start with a `LogicException` when the manager has pending changes on entities other
  than `QrCodeProfileConfig` (the host's work would be carried into a transaction that may roll back). After a failure a
  closed manager is replaced through the registry, and an open one forgets only the profile rows.
- **Bundle's own manager** (`doctrine.entity_manager` set to a manager other than the host's default):
  `DoctrineTransactionRunner::forEntityManager()`; after a storage failure that manager is closed and reset.
- Nested in a host transaction, the unit runs on a savepoint and repairs the nesting level after a failure (rule 3); a
  lost savepoint is never taken as success.
- `ClosedEntityManagerSubscriber` (since 1.12) still replaces a closed bundle manager and detaches stale profile rows at
  the start of each main request.
- No retry inside the unit, no I/O while a lock is held, no listener swallows a transient failure (rules 8 and 9). The
  admin pages are POST + redirect; a retried create after `CONFLICT_001` writes nothing twice, and the unique name keeps
  a blind repeat from creating a duplicate (rule 7).

## Tests and CI

- `tests/Integration/Database/QrCodeProfileConcurrencyDatabaseTest.php` (group `database`): two connections on MySQL
  8.4, MariaDB 11.4 and PostgreSQL 16, each session with a 2 s `innodb_lock_wait_timeout` / `lock_timeout`:
  - two admins creating one name: `CONFLICT_001` while the first insert is open, then the domain 409, one row;
  - an edit of a stale profile: refused by the version, the newer row stays whole;
  - an edit that meets a locked row: `CONFLICT_001`, nothing written, the retry writes once;
  - a YAML import that meets a locked row: nothing written (all or nothing), the retry writes all;
  - a lock conflict after a commit earlier in the request: `CONFLICT_002`, counted by the real middleware.
- Unit tests cover the mapping of every conflict code, the domain codes, the host routes, the write unit on the host's
  and the bundle's manager, and the commit-tracking scoping.
- CI job "Real databases (MySQL 8.4, MariaDB 11.4, PostgreSQL 16)" on DBAL 3 and 4, a required check on `main`
  (`make test-with-db` locally).

## Residual risks

- **Delete wins over a concurrent edit.** A delete does not check the version: an admin who deletes a profile another
  admin just edited deletes the edited row. Accepted: deleting is the admin's explicit intent, and nothing is
  half-saved.
- **The YAML import overwrites concurrent admin edits made before it read the rows.** That is what the import is for;
  an edit committed while the import runs makes the import fail whole (`QR_CODE_PROFILE_STALE`) instead of being
  overwritten.
- **Case-insensitive collations.** On MySQL / MariaDB the unique key treats `Compact` and `compact` as one name, so the
  second create answers `QR_CODE_PROFILE_DUPLICATE_NAME`. Lookups are exact (rule 5), so a profile is never resolved by
  another case. Hosts that want case-distinct names use a binary collation for the `name` column.
- **Low traffic.** The admin is an admin-only, low-traffic path; no retry is built in. A host that wants automatic
  retries aliases transaction-contract's runner to a retrying adapter.
