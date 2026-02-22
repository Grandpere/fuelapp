# TODO - Receipts Front/Admin Edition

## Plan
- [completed] Add front UI receipt lines edit flow (`/ui/receipts/{id}/edit`) with CSRF and owner-scoped access.
- [completed] Add application/repository support for receipt updates and admin system-scope reads/deletes.
- [completed] Add admin receipts section: list, detail, edit, delete, row-click behavior, and audit logging.
- [completed] Add/update functional tests for front/admin receipt edit-delete coverage.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Functional test validation done with user-run `make phpunit-functional`; fixes applied.
