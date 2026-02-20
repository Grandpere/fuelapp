# SP1-002 - API authentication setup

## Context
API must be protected before new features.

## Scope
- Configure API auth (JWT or token strategy to confirm).
- Secure `/api/*` endpoints by default.
- Return proper `401/403` semantics.

## Out of scope
- UI login screens.

## Acceptance criteria
- Anonymous API requests are denied.
- Authenticated requests work with valid token.
- Invalid/expired token paths tested.

## Dependencies
- SP1-001.

## Status
- todo
