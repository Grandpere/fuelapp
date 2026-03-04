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

# TODO - SP9 batch B (SP9-002 + SP9-003)

## Plan
- [completed] SP9-002 identities domain/app layer: add admin identity repository + manager contracts.
- [completed] SP9-002 identities API/UI: list/relink/unlink + filters + audit logging.
- [completed] SP9-003 security activities: add dedicated read model/API/UI timeline.
- [completed] SP9-003 activity feed: log login success/failure and include account-role/status events in timeline.
- [completed] Add/update admin functional coverage for identities and security activities.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP9-004 Admin password reset + email verification controls

## Plan
- [completed] Add user email verification persistence flag (`emailVerifiedAt`) and expose it in admin user API/UI.
- [completed] Add admin API controls for user email verification status + resend verification request + password reset.
- [completed] Add admin UI controls for toggle verification, resend verification, and temporary password reset.
- [completed] Add/update functional tests for new API/UI controls.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP10-001 Local observability stack (SigNoZ baseline)

## Plan
- [completed] Add SigNoZ service to Docker compose using `observability` profile.
- [completed] Add make targets for observability start/stop/logs.
- [completed] Add local runbook with URLs, ports, and first checks.
- [completed] Add explicit backlog ticket for possible migration to modular stack later (`SP10-004`).
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share any failures.

# TODO - SP10-002 Structured logs + correlation

## Plan
- [completed] Add correlation context and HTTP request/response subscriber (`X-Correlation-Id`).
- [completed] Add Messenger correlation stamp + middleware propagation.
- [completed] Add integration/functional coverage for correlation propagation.
- [completed] Enable structured JSON logs processor through Monolog config.
- [completed] Dependency approval obtained and `symfony/monolog-bundle` installed.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`) on current code state.
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP10-003 Observability runbook

## Plan
- [completed] Expand local SigNoZ runbook with standard diagnostics flow and troubleshooting steps.
- [completed] Add canonical query patterns (`correlation_id`, `user`, `route`, `job`) for incident triage.
- [completed] Document local vs production operational differences and rollout notes.
- [completed] Align Sprint 10/backlog ticket statuses with delivered work (`SP10-001` to `SP10-003`).
- [completed] Documentation-only ticket: no code quality/test commands executed.

# TODO - SP10-004 Modular stack evaluation decision

## Plan
- [completed] Produce explicit comparison matrix between SigNoZ baseline and modular OTel/Loki/Tempo/Prometheus/Grafana stack.
- [completed] Document migration decision, trigger criteria, and phased rollout strategy if migration is approved later.
- [completed] Link decision artifact in backlog ticket and mark Sprint 10 execution plan as completed.
- [completed] Documentation-only ticket: no code quality/test commands executed.

# TODO - SP10 follow-up OTEL wiring to SigNoZ

## Plan
- [completed] Add OpenTelemetry dependencies (`api`, `sdk`, `exporter-otlp`) with user approval.
- [completed] Wire OTEL environment in Docker app service for SigNoZ OTLP endpoint.
- [completed] Add HTTP request tracing subscriber with correlation attributes.
- [completed] Extend Messenger middleware to emit dispatch/consume spans with correlation attributes.
- [completed] Update observability runbook with concrete first telemetry checks.
- [completed] Fix observability runtime by adding ClickHouse backend and explicit SigNoZ DSN credentials.
- [completed] Run syntax-only verification on touched PHP files (no tests executed).

# TODO - SP3-010 Bulk import (multi-file and ZIP)

## Plan
- [completed] Add shared bulk upload service for direct files + ZIP extraction with per-file validation and result summary.
- [completed] Add API bulk endpoint `POST /api/imports/bulk` with OpenAPI docs and deterministic accepted/rejected payload.
- [completed] Extend `/ui/imports` upload form/controller to support multi-file submit and show summary flash feedback.
- [completed] Add/update functional coverage for API bulk and web multi-upload flows.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP3-011 OCR address line reconstruction

## Plan
- [completed] Add parser heuristic to merge split street line segments before postal/city line when confidence is acceptable.
- [completed] Add unit coverage for split-address OCR sample (`Route de` + `Troyes`).
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP11 batch A (SP11-002 + SP11-003)

## Plan
- [completed] SP11-002: harden OCR parser for noisy Volume/Prix ticket patterns with conservative heuristics.
- [completed] SP11-002: add focused unit tests for new noisy OCR extraction cases.
- [completed] SP11-003: standardize compact table action button styles across critical front/admin templates.
- [completed] SP11-003: remove repetitive inline style fragments where shared utility classes can be used.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share failures.

# TODO - SP11-004 OCR provider retry/backoff hardening

## Plan
- [completed] Reclassify OCR.Space capacity errors as retryable provider failures.
- [completed] Add import-handler retry scheduling with explicit backoff delays for retryable OCR errors.
- [completed] Add retry-attempt ceiling with deterministic exhausted-to-failed fallback.
- [completed] Update unit/integration tests for queued-on-retryable and exhausted behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share failures.
