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

# TODO - SP15-003 Front/admin product friction cleanup

## Plan
- [completed] Add clearer terminal-state next actions on front/admin import detail pages.
- [completed] Restore shared row-click behavior on receipt list rows.
- [completed] Extend duplicate detection so semantically identical receipts are flagged even when the uploaded file bytes differ.
- [completed] Add/update unit, integration, and functional coverage for the new duplicate/shortcut behaviors.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP16-001 Receipt create/edit flow polish

## Plan
- [completed] Reframe Sprint 16/17 backlog and document the front-only scope for receipt form ergonomics.
- [completed] Replace technical receipt form inputs with human-friendly units (`L`, `€/L`) while keeping integer persistence.
- [completed] Align receipt line editing with the same parsing/display rules.
- [completed] Add/update unit and functional coverage for decimal parsing and form rendering.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP16-002 Maintenance flow clarity

## Plan
- [completed] Reframe maintenance event/plan forms around user-facing EUR amounts instead of storage cents.
- [completed] Clarify maintenance dashboard empty states with explicit next actions.
- [completed] Add/update functional coverage for the revised event/plan form payloads and empty-state rendering.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP16-003 Search/filter persistence across key lists

## Plan
- [completed] Keep front receipt/import list context when opening details and coming back.
- [completed] Extend the same return-context behavior to the highest-friction admin lists and delete flows.
- [completed] Add/update functional coverage for safe return paths and list-row detail navigation.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP17-001 Analytics query profiling and hot-path optimization

## Plan
- [completed] Collapse the repeated fuel KPI reads used by the analytics dashboard into a single grouped snapshot read.
- [completed] Rewire the dashboard controller to consume the shared snapshot without changing output semantics.
- [completed] Add/update targeted unit coverage for the grouped analytics reader behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP17-002 Import pipeline observability polish

## Plan
- [completed] Surface a triage-oriented admin summary for import terminal states instead of relying only on raw payload inspection.
- [completed] Expose retry, fallback, duplicate-target, fingerprint, and timing metadata directly in the admin import detail flow.
- [completed] Add/update functional coverage for fallback and failed import observability views.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP19-001 Front maintenance reminder rules CRUD

## Plan
- [completed] Add front-office create/edit/delete controllers for maintenance reminder rules with owner-scoped validation.
- [completed] Surface reminder rules on the maintenance dashboard and wire the new form/delete actions.
- [completed] Add/update functional coverage for rule create/edit/delete and dashboard rendering.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP19-003 Receipt metadata edit flow

## Plan
- [completed] Add a dedicated front-office receipt metadata edit controller/handler.
- [completed] Expose the new edit flow from receipt detail and keep line editing separate.
- [completed] Add/update functional coverage for date, vehicle, station, and odometer edits.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP18-001 Vehicle-centered workflow polish

## Plan
- [completed] Add a front-office vehicle detail page that acts as a daily workflow hub.
- [completed] Link vehicle detail to filtered receipts and maintenance flows instead of forcing users back through generic lists.
- [completed] Add vehicle preselection on maintenance create forms and support vehicle filtering on the receipt list.
- [completed] Add/update functional coverage for the new vehicle hub, maintenance preselection, and receipt vehicle filter.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP18-002 Receipt detail usefulness pass

## Plan
- [completed] Enrich the receipt detail page with linked vehicle context instead of showing only raw receipt fields.
- [completed] Add direct next actions from a receipt toward vehicle, maintenance, analytics, and maintenance-event creation.
- [completed] Allow manual receipt creation to link a vehicle, including preselection from the vehicle page.
- [completed] Add/update functional coverage for the receipt detail context and vehicle-linked creation flow.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP18-003 Maintenance reminder actionability

## Plan
- [completed] Expose clearer trigger context on reminder cards instead of leaving them as passive alerts.
- [completed] Add direct reminder actions toward vehicle view, filtered maintenance timeline, and event logging.
- [completed] Support event-type prefill on maintenance event creation so reminder actions land on a ready-to-use form.
- [completed] Add/update functional coverage for reminder action links and event-type prefill.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

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

# TODO - SP15-001 Import review multi-line finalization

## Plan
- [completed] Align Sprint 15 backlog/todo tracking around import review product gaps.
- [completed] Expose every parsed/import line in user and admin review pages instead of only the first line.
- [completed] Accept multi-line review form payloads in front/admin finalize controllers while keeping validation explicit.
- [completed] Add/update front/admin tests for multi-line review/finalization.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP15-002 Bulk import feedback and recovery polish

## Plan
- [completed] Clarify Sprint 15 backlog tracking after SP15-001 delivery.
- [completed] Replace generic bulk-upload flash floods with a structured post-upload summary on `/ui/imports`.
- [completed] Keep accepted/rejected filename context readable for ZIP-originated entries.
- [completed] Add/update web functional coverage for the new summary behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP15-003 Front/admin product friction cleanup

## Plan
- [completed] Clarify Sprint 15 tracking after SP15-002 delivery.
- [in_progress] Add clearer next-step actions on import detail pages for processed/duplicate jobs.
- [pending] Keep front/admin import detail shortcuts aligned.
- [pending] Add/update functional coverage for the new terminal-state shortcuts.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP13-003 Theme toggle and light mode

## Plan
- [in_progress] Introduce shared light/dark theme tokens in the base layout and keep current look as dark mode.
- [pending] Add a persistent theme toggle in the UI shell with system-preference fallback.
- [pending] Adjust the most visible screens/components so light mode stays readable without page-specific regressions.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Ask user to run `make phpunit-functional` and validate the visual result.

# TODO - SP14-001 Security headers and CSP baseline

## Plan
- [completed] Inventory current frontend/browser dependencies and define a CSP-compatible baseline that does not break the current app.
- [completed] Add a shared response-level security-header subscriber for UI/API responses.
- [completed] Add regression coverage for expected browser security headers on representative public/protected routes.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP14-002 Frontend asset sovereignty and external dependency reduction

## Plan
- [completed] Inventory current remote frontend assets and pick the highest-value removals that do not require new dependencies.
- [completed] Remove Google Fonts and keep only justified frontend runtime dependencies for themed datepickers/maps.
- [completed] Tighten CSP expectations after dependency reduction and keep analytics map behavior intact.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

# TODO - SP14-003 Theme/chart runtime hardening and test coverage

## Plan
- [completed] Harden theme and chart runtime behavior against storage, navigation, and lifecycle edge cases.
- [completed] Add targeted regression coverage for rendered theme/chart runtime hooks.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] User-run functional suite validated (`make phpunit-functional`).

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
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP11-013 follow-up upload thresholds by file type

## Plan
- [completed] Keep OCR-side image auto-compression flow and raise upload intake for images to 8 MB.
- [completed] Keep PDF upload limit at 1 MB to match OCR provider constraint and fail fast with explicit message.
- [completed] Align API bulk/UI messages with split limits (8 MB images, 1 MB PDF).
- [completed] Update focused functional coverage on oversized ZIP entry behavior and message.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP13-002 Navigation and layout cohesion

## Plan
- [completed] Add shared front/admin utility classes for common page, filter, table and action patterns.
- [completed] Harmonize the highest-traffic front/admin templates to use those shared patterns instead of inline styles.
- [completed] Update Sprint 13/current-focus docs and capture any UX lessons in `docs/ai/memory.md` if needed.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and validate the visual flow.

# TODO - SP20-001 Import review productivity shortcuts

## Plan
- [completed] Reframe Sprint 20 backlog/docs and keep this ticket front-office only.
- [completed] Add review queue context with previous/next navigation on import review pages.
- [completed] Add a direct "finalize and open next" action plus lightweight keyboard shortcuts.
- [completed] Add/update functional coverage for queue context and continue-review behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP20-002 Receipt and maintenance cross-links completion

## Plan
- [completed] Preserve maintenance filter context when creating or editing events and plans from vehicle-scoped views.
- [completed] Add direct receipt/analytics/vehicle links from timeline, planner, and triggered reminder blocks.
- [completed] Add/update functional coverage for the new cross-links and maintenance return context.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP20-003 Vehicle dashboard usefulness pass

## Plan
- [completed] Add a few higher-signal cockpit summaries to the front vehicle detail page without turning it into a full analytics screen.
- [completed] Surface direct actions around the latest receipt, next maintenance plan, and current attention point.
- [completed] Add/update functional coverage for the vehicle cockpit additions.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP21-001 Receipt list productivity pass

## Plan
- [completed] Reframe Sprint 21 backlog/docs and keep this ticket front-office only.
- [completed] Make active receipt filters easier to read and give quick date shortcuts for common ranges.
- [completed] Add vehicle-context shortcuts and prefilled manual receipt creation from the current filter state.
- [completed] Add/update functional coverage for the new receipt-list shortcuts and summary behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP21-002 Station workflow usefulness pass

## Plan
- [completed] Add a front station detail page that turns a station into a useful navigation hub.
- [completed] Connect receipt and receipt-list station contexts to the new station shortcuts.
- [completed] Allow manual receipt creation to prefill station details from station context.
- [completed] Add/update front functional coverage for station navigation and prefilled create flow.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP21-003 Import post-processing clarity

## Plan
- [completed] Build a clearer front-office status summary for processed, duplicate, failed, and needs_review imports.
- [completed] Surface next actions and key details directly from the import detail page without forcing payload reading.
- [completed] Add/update functional coverage for the clearer post-processing copy and links.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP22-003 Import queue confidence signals

## Plan
- [completed] Build clearer row-level confidence signals for processed, duplicate, failed, and needs_review imports on the queue list.
- [completed] Surface direct primary actions from the queue when the next step is obvious.
- [completed] Add/update functional coverage for the new import list signals and shortcuts.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP22-001 Vehicle timeline editing shortcuts

## Plan
- [completed] Add direct edit shortcuts on recent maintenance events and upcoming plans from the vehicle hub.
- [completed] Keep all edit/create maintenance shortcuts anchored back to the vehicle page.
- [completed] Add/update functional coverage for the new vehicle-hub editing actions.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP22-002 Receipt detail quick corrections pass

## Plan
- [completed] Expose compact quick-correction shortcuts on `/ui/receipts/{id}` for the highest-frequency fixes.
- [completed] Add direct edit actions for already-visible nearby entities when a safe front flow already exists.
- [completed] Add/update functional coverage for the new receipt detail shortcuts and missing-context guidance.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP23-003 Maintenance dashboard scanability pass

## Plan
- [completed] Add at-a-glance scan signals for urgent reminders, near-term plans, and recently handled events on `/ui/maintenance`.
- [completed] Mark timeline and planner rows with lightweight visual labels that reduce read time.
- [completed] Add/update functional coverage for the new maintenance scanability signals.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP23-002 Station list productivity pass

## Plan
- [completed] Add a front-office `/ui/stations` working list with basic usage signals per station.
- [completed] Expose direct station shortcuts into receipts, analytics, and receipt creation from the list.
- [completed] Add/update functional coverage for the new station list workflow.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP23-001 Vehicle form and list workflow polish

## Plan
- [completed] Keep vehicle create/edit flows anchored to a safe `return_to` instead of always dropping back to the generic list.
- [completed] Add the most useful front-office shortcuts directly from `/ui/vehicles` rows.
- [completed] Add/update functional coverage for the new vehicle list/form workflow behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP24-001 Analytics filter workflow polish

## Plan
- [completed] Add quick date-range shortcuts and an active-filter summary to `/ui/analytics`.
- [completed] Add direct next-step links that respect the current analytics context.
- [completed] Add a projection self-heal pass when analytics filters hit stale KPI rows after late receipt edits.
- [completed] Add/update functional coverage for the new analytics filter workflow behavior.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.

# TODO - SP24-002 Import list follow-up shortcuts

## Plan
- [completed] Add status quick filters and list-level follow-up shortcuts on `/ui/imports`.
- [completed] Expose secondary row actions when the main next step is not the only useful follow-up.
- [completed] Add/update functional coverage for status filtering and new import follow-up shortcuts.
- [completed] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [completed] Ask user to run `make phpunit-functional` and share failures if any.
