# SP1-006 - Authorization voters/policies

## Context
Route-level auth is not enough for object-level access.

## Scope
- Add authorization checks for receipt and station objects.
- Centralize policy rules.

## Out of scope
- Role matrix beyond `user/admin` baseline.

## Acceptance criteria
- Object access decisions are enforced consistently.
- Unauthorized object access returns `403` or `404` per policy.

## Dependencies
- SP1-004, SP1-005.

## Status
- done
