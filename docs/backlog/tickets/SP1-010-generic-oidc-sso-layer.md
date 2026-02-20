# SP1-010 - Generic OIDC SSO integration layer (Auth0/Google/Microsoft)

## Context
Need future-proof SSO support without provider-specific lock-in.

## Scope
- Add a generic OIDC integration layer (provider-agnostic contract/config).
- Implement first provider through the generic layer (Auth0 recommended).
- Support pluggable providers (Google, Microsoft later) via configuration.
- Map external identities to internal users safely.

## Out of scope
- Full enterprise IAM features (SCIM, Just-In-Time provisioning policies, advanced RBAC).
- Supporting every provider in Sprint 1.

## Acceptance criteria
- One OIDC provider works end-to-end through generic abstraction.
- Adding a second provider requires config + adapter, no auth core rewrite.
- Account linking and login errors are explicit and tested.

## Dependencies
- SP1-009.

## Status
- todo
