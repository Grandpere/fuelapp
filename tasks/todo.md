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
