# SP1-011 - OIDC provider catalog and conventions

## Context
Provider sprawl can quickly break maintainability.

## Scope
- Define supported provider catalog format.
- Define conventions for claims mapping (email, subject, name, picture).
- Define minimal required claims and fallback behavior.
- Document onboarding checklist for new providers.

## Out of scope
- Implementing each provider adapter.

## Acceptance criteria
- Team can add a new OIDC provider with a documented checklist.
- Claims mapping behavior is consistent across providers.
- Provider-specific quirks are captured in docs.

## Dependencies
- SP1-010.

## Status
- done

## Delivered
- Provider catalog documented (`auth0`, `google`, `microsoft`) with stable key conventions.
- Standard claims mapping documented (`sub`, `email`, `name`, `picture`).
- Required claims and fallback behavior documented (existing identity vs first link/create).
- Provider onboarding checklist documented (env/config/callback/test steps).
- Provider-specific notes captured for Auth0, Google, Microsoft.
