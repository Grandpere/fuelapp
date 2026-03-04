# SP12-002 - API abuse protection and input hardening

## Context
API endpoints need a targeted anti-abuse and validation hardening pass.

## Scope
- Review and enforce request-size/rate constraints on sensitive endpoints.
- Add explicit bounds/sanitization on high-risk user inputs used in persistence/audit.
- Verify consistent error behavior (no 500 on malformed hostile inputs).

## Out of scope
- Functional API redesign.

## Acceptance criteria
- Malformed/oversized input cases return controlled 4xx responses.
- Security regression tests cover abuse patterns on key endpoints.

## Dependencies
- Existing API platform and security middleware.

## Status
- todo
