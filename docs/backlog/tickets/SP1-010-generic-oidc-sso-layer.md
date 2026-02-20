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
- done

## Delivered
- Generic OIDC provider registry (config-driven, provider-agnostic contract).
- UI OIDC login flow endpoints:
- start: `/ui/login/oidc/{provider}`
- callback: `/ui/login/oidc/{provider}/callback`
- Generic OIDC client using discovery + token + userinfo endpoints.
- Safe account linking:
- link by `(provider, subject)` first,
- fallback by normalized email,
- create local user if not found, then persist identity link.
- First provider ready through generic layer: Auth0 (config keys present, disabled by default).
