# SP11-001 - Microsoft OIDC login rollout

## Context
Microsoft OIDC support is not essential now compared to import reliability and UX consistency work.

## Scope
- Keep provider setup documented and ready for later enablement.
- No runtime behavior change in this phase.

## Out of scope
- UI button enablement for Microsoft login.
- End-to-end provider onboarding in Azure.

## Acceptance criteria
- Backlog clearly marks Microsoft OIDC as deferred.
- No auth regression introduced.

## Technical notes
- Reuse existing generic OIDC provider layer when resumed.

## Dependencies
- Existing OIDC baseline (Auth0/Google architecture).

## Status
- blocked
