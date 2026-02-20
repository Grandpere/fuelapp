# SP1-007 - Security tests (unit + integration + functional)

## Context
Security regressions must be caught by CI.

## Scope
- Add automated tests for auth and ownership boundaries.
- Cover both API and UI critical paths.

## Out of scope
- Performance/security penetration testing.

## Acceptance criteria
- CI fails on unauthorized access regressions.
- Representative positive/negative cases are covered.

## Dependencies
- SP1-002 to SP1-006.

## Status
- done

## Delivered
- Added functional security tests for:
- anonymous access redirect on `/ui/*`.
- anonymous access rejection on `/api/*`.
- cross-user access denial on receipt and station API item endpoints.
- Added integration test for repository ownership scoping:
- `ReceiptRepository` returns only data for current authenticated user token.
