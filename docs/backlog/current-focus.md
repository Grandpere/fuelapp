# Current Focus

## Active sprint
- Sprint 01 - Security and ownership

## Current goal
- Put strong authentication/authorization foundations before async geocoding and import pipeline.

## In progress
- `SP3-001` - Import job model and storage.

## Next tickets (ordered)
1. `SP3-001` - Import job model and storage
2. `SP3-002` - Upload API endpoint validation

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

## Ready for coding checklist
- [ ] Confirm auth strategy for Sprint 01: local users + password hash
- [ ] Confirm API auth mode for Sprint 01: JWT vs token
- [x] Start `SP1-001`
