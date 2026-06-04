# CalDAV Server Compatibility Harness — Design

Date: 2026-06-04
Status: Approved (pending implementation plan)

## Goal

Provide a Pest feature test that boots a real, network-reachable DAV server,
runs the external [`caldav-server-tester`](https://github.com/python-caldav/caldav-server-tester)
against it, parses the JSON feature/support map it emits, and asserts that map
equals a committed **baseline** capturing today's status quo.

The point is to have a repeatable way to *get the results*, not to make every
feature pass. Many features are known to be unsupported today. The test is green
when the observed results match the recorded baseline. As features move
`unsupported → supported`, the baseline is regenerated and its diff documents the
progress.

## Non-goals

- Making the server pass all (or any specific subset of) compatibility checks.
- Replacing the existing in-process `$this->call()` protocol tests.
- Cross-platform CI hardening beyond "the tester binary must be installed".

## Decisions (settled during brainstorming)

1. **Server boot:** use `orchestra/testbench`'s built-in `serve` command, spawned
   via `Illuminate\Process`, against a file-based SQLite DB seeded beforehand.
2. **Gating:** fail loudly. The test runs in the default `pest` run; the
   `caldav-server-tester` binary must be installed wherever the suite runs.
3. **Assertions:** capture the status quo for *all* reported features as a
   committed baseline and assert equality, so the test is green now and any
   change (improvement or regression) surfaces as a baseline diff.

## Location & isolation

- New file: `tests/Integration/CaldavServerTesterTest.php`.
- It is **not** bound to the package `TestCase` (`tests/Pest.php` only binds
  `TestCase` — and therefore `RefreshDatabase` / in-memory SQLite — to
  `Feature/`, `Unit/`, `Smoke/`). The integration test must not share that
  in-memory DB; the spawned server is a separate OS process and needs a shared
  **file** DB.
- It is a plain Pest test that only orchestrates child processes.
- It lives under `tests/`, so the default `./tests` testsuite (see `phpunit.xml`)
  discovers and runs it with `pest`.

## Flow (per test run)

1. Allocate a temp SQLite file and a free TCP port.
2. Build a shared environment for all child processes:
   - `DB_CONNECTION=sqlite`
   - `DB_DATABASE=<tempfile>`
   - `DAV_OWNER_MODEL=Bambamboole\LaravelDav\Tests\Stubs\OwnerUser`
   - a fixed `DAV_REALM`
3. `vendor/bin/testbench migrate:fresh` — creates Laravel's default `users`
   table plus the package's `dav_*` tables in the file DB (the provider
   auto-registers its migrations on boot).
4. `vendor/bin/testbench db:seed --class=<CaldavTesterSeeder>` — seeds one owner,
   one `DavCredential` (known username + known plaintext secret, hashed via
   `Hash::make`), and a `personal` calendar + addressbook so CalDAV/CardDAV
   discovery succeeds.
5. `vendor/bin/testbench serve --host=127.0.0.1 --port=<port>` started in the
   background with `Process::start()`, same env. The provider registers the DAV
   routes on boot, so `/dav/` is exposed.
6. Poll `http://127.0.0.1:<port>/dav/` until it responds `401` (server ready),
   subject to a timeout that fails the test with a clear message.
7. Run:
   `caldav-server-tester --caldav-url http://127.0.0.1:<port>/dav/ \
     --caldav-username <user> --caldav-password <secret> \
     --caldav-calendar personal --format json`
   and capture stdout.
8. Parse the JSON and extract **only** the feature→support map. Strip volatile
   metadata (server URL, version, timestamps). Normalize (sort keys) so the
   comparison is stable across runs.
9. Compare the normalized map to
   `tests/Integration/baseline/caldav-server-tester.json` and assert equality.
   - When `DAV_TESTER_UPDATE_BASELINE=1`, write the baseline file instead of
     asserting. This is how the status quo is first recorded and how progress is
     later captured.
10. In a `finally`/`afterEach` teardown: stop the serve process and delete the
    temp DB/dir — even when the test fails.

## New artifacts

- `tests/Integration/CaldavServerTesterTest.php` — the orchestrator test.
- `tests/Integration/Support/CaldavTesterSeeder.php` — deterministic seeder
  (owner, credential, calendar, addressbook).
- `tests/Integration/baseline/caldav-server-tester.json` — committed status-quo
  feature map, generated from the first real run.
- A `caldav-server-tester` install step + note in `README.md` and CI, since the
  suite now requires the binary.

## Components & responsibilities

- **Process orchestration** (in the test body): migrate → seed → serve → poll →
  run tester → teardown. Each step uses `Illuminate\Process`; failures surface
  the child process's stderr.
- **Result normalizer** (small helper): JSON string → stable, comparable feature
  map. Single responsibility: parsing + metadata stripping + key sorting.
- **Baseline compare/update** (small helper): assert-equal against the committed
  file, or rewrite it when `DAV_TESTER_UPDATE_BASELINE=1` is set.

## Error handling

- Missing `caldav-server-tester` binary, a non-zero exit from any spawned
  command, a server that never becomes ready, or non-JSON output all fail the
  test with the captured stderr/stdout in the message.
- Teardown always runs so a failed run does not leak a server process or temp DB.

## Known risks (to resolve during implementation/TDD)

- Pinning Testbench to the file SQLite across all spawned commands may require a
  `testbench.yaml` / workbench configuration rather than env vars alone. Verify
  on the first real run; add the minimal config if needed.
- The tester's exact JSON shape is unknown until the first run. The normalizer
  and the baseline are written from that real output.
- `caldav-server-tester` must be installed (e.g. `uv tool install`) for the test
  to run locally and in CI.

## Testing

This *is* a test. Its own "verification" is: it produces a parseable result and
goes green against the baseline. The first implementation step generates the
baseline from a real run; subsequent runs assert against it.
