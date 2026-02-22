# Current Focus

## Active sprint
- Sprint 06 - Analytics and exports

## Current goal
- Deliver decision-oriented metrics and robust exports.

## In progress
- `SP6-008` - BrowserKit migration for functional test suite.

## Next tickets (ordered)
1. `SP6-008` - BrowserKit migration for functional test suite

## Notes
- Social login / external IdP is planned after local auth baseline via generic OIDC layer (Auth0 first, then Google/Microsoft).
- Sprint 01 target is local auth baseline + resource protection + ownership isolation.
- Local auth baseline delivered: login page, authenticator, logout, user creation command.
- API auth strategy for Sprint 1: JWT bearer on `/api` with dedicated login endpoint.
- API JWT baseline delivered: `/api/login` + Bearer authenticator on `/api/*`.
- UI protection baseline delivered: `/ui/*` secured, `/ui/login` public, CSRF-protected logout.
- Receipt repository scoping delivered: user only sees/mutates owned receipts.
- Station repository reads are scoped to stations linked to current user's receipts.
- Ownership transition support delivered: command to claim historical unowned receipts.
- Authorization layer delivered: voters for receipt/station view/delete policies.
- Security tests baseline delivered (integration + functional) for auth/ownership boundaries.
- Security runbook and local/dev/prod security checklist delivered.
- Generic OIDC SSO integration layer delivered (Auth0-ready, provider-agnostic).
- OIDC provider catalog/conventions delivered (claims mapping + onboarding checklist).
- `SP3-001` delivered: import job domain model, persistence, local storage adapter, and baseline tests.
- `SP3-002` delivered: authenticated upload endpoint with validation and queued import job creation.
- `SP3-003` delivered: async import message dispatch, handler lifecycle transitions, and retry-ready routing.
- `SP3-004` delivered: OCR abstraction + first provider adapter and normalized extraction payload for parser handoff.
- `SP3-005` delivered: OCR parsing/normalization draft model with validated command payload candidate and parse issues.
- `SP3-006` delivered: checksum-based duplicate detection with explicit `duplicate` import status and idempotent short-circuit before OCR.
- `SP3-007` delivered: API review/finalization flow for `needs_review` imports with receipt creation and processed audit payload.
- `SP3-008` delivered: import regression coverage for OCR/provider/parser failure paths and finalize API error-paths.
- `SP3-009` delivered: upload endpoint migrated to native API Platform operation with multipart OpenAPI docs (no custom decorator).
- `SP4-001` delivered: admin access model with role hierarchy and explicit `/api/admin` + `/ui/admin` policy gates.
- `SP4-002` delivered: admin station CRUD APIs and vehicle management APIs (read/update/delete) with basic list filters/search in `/api/admin/*`.
- `SP4-003` delivered: back-office UI shell/navigation with station and vehicle pages.
- `SP4-004` delivered: imports dashboard with filters and metrics.
- `SP4-005` delivered: admin retry/finalize flows for import recovery.
- `SP4-006` delivered: immutable audit trail for admin mutations (API + UI listing).
- `SP4-007` delivered: back-office functional suite extended for authenticated UI role boundary and seeded rendering coverage.
- `SP5-001` delivered: maintenance bounded context skeleton with domain model, doctrine persistence, migration, and baseline tests.
- `SP5-002` delivered: maintenance events CRUD API with ownership-safe vehicle binding and functional validation/isolation coverage.
- `SP5-003` delivered: reminder rules model (date/odometer/whichever-first) with deterministic due-state calculator and rule persistence/tests.
- `SP5-004` delivered: async reminder evaluation workflow with persistent deduplicated reminders and in-app notification abstraction.
- `SP5-005` delivered: planned maintenance cost CRUD + variance read-model endpoint (period/vehicle filters) with unit/integration/functional coverage.
- `SP5-006` delivered: maintenance web UI timeline/planner + event/plan create-edit forms with responsive pages and functional coverage.
- `SP5-008` delivered: admin read-only maintenance exposure (`/api/admin/maintenance/*`, `/ui/admin/maintenance/*`) with filters and role-boundary functional coverage.
- `SP5-009` delivered: vehicle web CRUD for users and back-office vehicle mutation forms for admins with functional coverage.
- `SP6-001` delivered: analytics daily fuel KPI read-model, refresh pipeline (sync/async), and projection freshness metadata.
- `SP6-002` delivered: KPI API endpoints (cost/month, consumption/month, average fuel price) with period/vehicle filters and explicit units.
- `SP6-003` delivered: responsive analytics dashboard UI with period/vehicle filters, KPI cards, and monthly trend views.
- `SP6-004` delivered: station/fuel/date multi-filter parity across KPI APIs and dashboard UI.
- `SP6-005` delivered: hardened export flow with streaming CSV/XLSX and export metadata.
- `SP6-006` delivered: KPI query optimization (dynamic SQL filters) with documented performance budget + indexing strategy.
- `SP6-007` delivered: deterministic analytics validation tests for KPI correctness, rounding precision, and export/API parity.

## Ready for coding checklist
- [ ] Confirm auth strategy for Sprint 01: local users + password hash
- [ ] Confirm API auth mode for Sprint 01: JWT vs token
- [x] Start `SP1-001`
