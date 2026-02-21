# Current Focus

## Active sprint
- Sprint 03 - Async import (image/PDF)

## Current goal
- Deliver async import foundations (job model, upload endpoint, orchestration).

## In progress
- `SP3-003` - Async pipeline orchestration.

## Next tickets (ordered)
1. `SP3-003` - Async pipeline orchestration
2. `SP3-004` - OCR adapter abstraction and first provider

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

## Ready for coding checklist
- [ ] Confirm auth strategy for Sprint 01: local users + password hash
- [ ] Confirm API auth mode for Sprint 01: JWT vs token
- [x] Start `SP1-001`
