# Current Focus

## Active sprint
- Sprint 01 - Security and ownership

## Current goal
- Put strong authentication/authorization foundations before async geocoding and import pipeline.

## In progress
- `SP1-001` - User model and ownership columns (baseline committed, final constraints pending).
- `SP1-003` - UI authentication and protected routes (in progress).

## Next tickets (ordered)
1. `SP1-003` - UI authentication and protected routes (hardening)
2. `SP1-001` - Finalize user/ownership baseline constraints
3. `SP1-010` - Generic OIDC SSO integration layer

## Notes
- Social login / external IdP is planned after local auth baseline via generic OIDC layer (Auth0 first, then Google/Microsoft).
- Sprint 01 target is local auth baseline + resource protection + ownership isolation.
- Local auth baseline delivered: login page, authenticator, logout, user creation command.
- API auth strategy for Sprint 1: JWT bearer on `/api` with dedicated login endpoint.
- API JWT baseline delivered: `/api/login` + Bearer authenticator on `/api/*`.

## Ready for coding checklist
- [ ] Confirm auth strategy for Sprint 01: local users + password hash
- [ ] Confirm API auth mode for Sprint 01: JWT vs token
- [x] Start `SP1-001`
