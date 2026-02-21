# Current Focus

## Active sprint
- Sprint 04 - Back-office

## Current goal
- Deliver back-office security model and admin tooling.

## In progress
- `SP4-005` - Retry/fix/reprocess actions for failed imports.

## Next tickets (ordered)
1. `SP4-005` - Retry/fix/reprocess actions for failed imports
2. `SP4-006` - Audit trail for admin actions

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
- `SP4-002` delivered: admin CRUD APIs for stations and vehicles with basic list filters/search in `/api/admin/*`.

## Ready for coding checklist
- [ ] Confirm auth strategy for Sprint 01: local users + password hash
- [ ] Confirm API auth mode for Sprint 01: JWT vs token
- [x] Start `SP1-001`
