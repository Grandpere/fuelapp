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
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP11-004 OCR provider retry/backoff hardening

## Plan
- [completed] Reclassify OCR.Space capacity errors as retryable provider failures.
- [completed] Add import-handler retry scheduling with explicit backoff delays for retryable OCR errors.
- [completed] Add retry-attempt ceiling with deterministic exhausted-to-failed fallback.
- [completed] Update unit/integration tests for queued-on-retryable and exhausted behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share failures.

# TODO - SP11-005 OCR provider circuit breaker for outage bursts

## Plan
- [completed] Add OCR.Space circuit breaker state with cache-backed open/failure counters.
- [completed] Fast-fail provider calls while breaker is open with retryable exception for Messenger.

# TODO - SP11-013 OCR image auto-resize over provider 1MB limit

## Plan
- [completed] Add OCR provider pre-upload image optimization for oversized JPEG/PNG/WEBP files.
- [completed] Keep original stored file untouched; send only optimized temporary copy to OCR.
- [completed] Add unit coverage for oversized image optimization path.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).
- [completed] Reset failure counter after successful OCR extraction.
- [completed] Add/update unit tests for circuit-open behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP11-006 OCR fallback strategy (manual review fallback)

## Plan
- [completed] Add fallback behavior after retry exhaustion: switch import job to `needs_review` instead of `failed`.
- [completed] Persist explicit fallback payload with parse issues so user can manually finalize in UI.
- [completed] Update unit/integration tests for exhausted-retry fallback semantics.

# TODO - SP12-001 Auth and session hardening

## Plan
- [completed] Make session fixation/logout invalidation guarantees explicit in security/framework config.
- [completed] Add targeted functional coverage for session rotation, logout invalidation, and cookie attributes on UI auth flow.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).
- [completed] Add UI login throttling policy on main firewall to reduce brute-force attempts.
- [completed] Add API login limiter (`/api/login`) with controlled `429` response and `Retry-After` header.
- [completed] Add functional regression for API login rate-limit behavior.

# TODO - SP12-002 API abuse protection and input hardening

## Plan
- [completed] Add explicit payload size guard on `/api/login` with controlled `413` response.
- [completed] Add rate limiting for `/api/imports` and `/api/imports/bulk` with controlled `429` + `Retry-After`.
- [completed] Add functional security regressions for oversized login payload and upload/bulk rate limiting.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP13-003 Theme toggle and light mode

## Plan
- [in_progress] Introduce shared light/dark theme tokens in the base layout and keep current look as dark mode.
- [pending] Add a persistent theme toggle in the UI shell with system-preference fallback.
- [pending] Adjust the most visible screens/components so light mode stays readable without page-specific regressions.
- [pending] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and validate the visual result.

# TODO - SP12-003 Import/upload security hardening

## Plan
- [completed] Enforce mime/extension consistency checks for direct and bulk import files.
- [completed] Harden ZIP entry handling against dangerous paths (`../`, absolute path, control chars).
- [completed] Add defensive ZIP processing limits (max entries and streamed per-entry size cap).
- [completed] Add functional regressions for mime-extension mismatch and hostile ZIP entries.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP12-004 Security observability and incident runbook hardening

## Plan
- [completed] Define security-focused alert baseline and concrete thresholds (auth abuse, upload abuse, admin-sensitive bursts).
- [completed] Add security query starters and repeatable triage procedure for SigNoZ.
- [completed] Add local verification checklist for end-to-end alert/runbook validation.
- [completed] Link security runbook from observability alerting baseline docs.
- [completed] Documentation-only ticket: no code quality/test commands executed.

# TODO - SP11-014 OCR parser hardening on real uploaded samples

## Plan
- [completed] Analyze current OCR payloads/parsed drafts from real uploaded samples in DB (`import_jobs`).
- [completed] Improve station/postal/city extraction for compact/noisy OCR formats (including `L-5751` postal pattern).
- [completed] Tighten street fallback to avoid technical tokens (`a000...`) and select more plausible address candidates.
- [completed] Improve fuel line parsing for noisy patterns (`Excellium 98`, parenthesized quantity, split unit price forms).
- [completed] Add unit regressions reproducing real sample patterns.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP11-013 follow-up upload thresholds by file type

## Plan
- [completed] Keep OCR-side image auto-compression flow and raise upload intake for images to 8 MB.
- [completed] Keep PDF upload limit at 1 MB to match OCR provider constraint and fail fast with explicit message.
- [completed] Align API bulk/UI messages with split limits (8 MB images, 1 MB PDF).
- [completed] Update focused functional coverage on oversized ZIP entry behavior and message.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP13-002 Navigation and layout cohesion

## Plan
- [completed] Add shared front/admin utility classes for common page, filter, table and action patterns.
- [completed] Harmonize the highest-traffic front/admin templates to use those shared patterns instead of inline styles.
- [completed] Update Sprint 13/current-focus docs and capture any UX lessons in `docs/ai/memory.md` if needed.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and validate the visual flow.
