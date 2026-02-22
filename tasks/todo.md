# TODO - Receipts Front/Admin Edition

## Plan
- [completed] Add front UI receipt lines edit flow (`/ui/receipts/{id}/edit`) with CSRF and owner-scoped access.
- [completed] Add application/repository support for receipt updates and admin system-scope reads/deletes.
- [completed] Add admin receipts section: list, detail, edit, delete, row-click behavior, and audit logging.
- [completed] Add/update functional tests for front/admin receipt edit-delete coverage.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Functional test validation done with user-run `make phpunit-functional`; fixes applied.

# TODO - SP6-006 Analytics performance optimization

## Plan
- [completed] Refactor KPI SQL filters to dynamic predicates (remove optional `OR` filter branches).
- [completed] Add targeted analytics index for fuel-filtered reads.
- [completed] Document performance budget and profiling checks in backlog ticket/docs.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP6-007 Analytics validation tests

## Plan
- [completed] Add deterministic KPI precision regression test for half-up rounding.
- [completed] Add functional parity test between analytics KPI totals and CSV export totals for identical filters.
- [completed] Update sprint/backlog docs and mark ticket `SP6-007` as done.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP6-008 BrowserKit functional tests migration

## Plan
- [completed] Migrate admin back-office UI functional suite to BrowserKit client.
- [completed] Migrate import user UI functional suite to BrowserKit client.
- [completed] Migrate receipt user UI functional suite to BrowserKit client.
- [completed] Keep fixture setup and assertions stable while removing manual session/cookie request plumbing.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.
