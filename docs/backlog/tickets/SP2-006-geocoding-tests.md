# SP2-006 - Geocoding tests and failure scenarios

## Context
Geocoding has many external failure modes.

## Scope
- Unit tests for adapter mapping and error handling.
- Integration tests for job handler behavior.
- Cases: no result, timeout, rate-limit, malformed response.

## Out of scope
- Load testing.

## Acceptance criteria
- Green tests cover happy path and core failures.
- CI catches regressions in async status transitions.

## Dependencies
- SP2-003, SP2-004, SP2-005.

## Status
- done
