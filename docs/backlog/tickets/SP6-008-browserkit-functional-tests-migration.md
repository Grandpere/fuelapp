# SP6-008 - BrowserKit migration for functional test suite

## Context
Current functional tests are written with `KernelTestCase` + manual `Request::create(...)` because `symfony/browser-kit` is not installed.
This makes UI/API flow tests verbose and harder to maintain.

## Scope
- Add `symfony/browser-kit` as a project dependency (after explicit user approval).
- Introduce shared functional test base helpers using BrowserKit client (`WebTestCase::createClient()`).
- Migrate priority functional suites first:
  - admin back-office UI flows,
  - receipt/import user UI flows,
  - auth/session-driven flows.
- Keep assertions equivalent to avoid behavior drift during migration.

## Out of scope
- Rewriting all tests in one pass.
- Changing business behavior or API contracts.
- Frontend visual/E2E browser automation.

## Acceptance criteria
- `symfony/browser-kit` is installed and functional in test environment.
- At least the targeted priority suites are migrated and green.
- Session/cookie/csrf/form flows rely on BrowserKit client (not manual kernel request plumbing).
- Test readability improves (reduced helper boilerplate).

## Technical notes
- Keep migration incremental and commit by subset to reduce risk.
- Preserve existing data fixtures and DB reset strategy.
- Validate no regression in auth boundaries while migrating.

## Dependencies
- User approval to install dependency: `symfony/browser-kit`.
- Existing functional suites and test DB lifecycle.

## Delivery notes
- `symfony/browser-kit` is now available in dev dependencies.
- Migrated priority UI suites from manual kernel requests to BrowserKit client (`WebTestCase::createClient()`):
  - `tests/Functional/Admin/AdminBackofficeUiTest.php`
  - `tests/Functional/Import/ImportWebUiTest.php`
  - `tests/Functional/Receipt/ReceiptWebUiTest.php`
- Session/authenticated UI flows now rely on BrowserKit cookie jar instead of manual cookie extraction and request plumbing.
- Existing fixture setup, DB reset strategy, and assertions were kept to avoid behavior drift.

## Status
- done
