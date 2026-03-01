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

# TODO - SP7 batch A (SP7-001 + SP7-004)

## Plan
- [completed] SP7-001: define/apply Turbo conventions on critical front/admin flows (imports + vehicle/maintenance forms).
- [completed] SP7-004: add `/ui/contact` page and admin shortcut in front nav.
- [completed] Update functional tests for touched front/admin flows.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` for batch validation and share errors.

# TODO - SP7-002 receipt odometer support

## Plan
- [completed] Add `odometerKilometers` on receipt domain + persistence model.
- [completed] Expose odometer in API inputs/outputs and web create/edit flows.
- [completed] Extend import review/finalize flow with odometer value.
- [completed] Add/update unit/integration/functional coverage for odometer scenarios.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share any failures.

# TODO - SP7-003 receipt odometer -> maintenance reminder bridge

## Plan
- [completed] Add resolver that computes current vehicle odometer from maintenance events + receipt odometer history.
- [completed] Wire reminder evaluation handler to use the aggregated resolver.
- [completed] Extend repository contract with owner+vehicle max receipt odometer lookup for system jobs.
- [completed] Add unit/integration coverage for receipt-driven mileage due reminders.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share any failures.

# TODO - SP8-001 visited stations map

## Plan
- [completed] Add analytics read-model method for visited geocoded stations with existing filters parity.
- [completed] Expose visited stations data through a dedicated API Platform collection endpoint.
- [completed] Render visited stations map on analytics dashboard (Leaflet + OSM) with fallback list.
- [completed] Add/adjust functional tests for API and analytics dashboard map section.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share any failures.

# TODO - SP8-002 fuel price evolution graph

## Plan
- [completed] Add monthly fuel price evolution read-model query with existing analytics filters.
- [completed] Expose monthly fuel price trend via API endpoint.
- [completed] Render a new fuel price trend panel in analytics dashboard.
- [completed] Extend functional tests for API and dashboard rendering.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share any failures.

# TODO - SP8-003 compared cost graph (fuel vs maintenance vs total)

## Plan
- [completed] Add read-model/API series combining monthly fuel and maintenance costs.
- [completed] Add analytics dashboard compared-cost panel.
- [completed] Extend functional coverage for new API endpoint and UI block.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share any failures.

# TODO - SP9-001 BO users CRUD operational controls

## Plan
- [completed] Add user activation flag and enforce disabled-user blocking on auth entry points.
- [completed] Add admin API users resource (list/detail/update role+status with filters).
- [completed] Add admin UI users page with filters and toggle actions.
- [completed] Add audit entries for user admin actions.
- [completed] Add/update functional coverage for admin API/UI user management.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share any failures.
