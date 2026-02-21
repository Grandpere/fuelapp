# SP2-005 - Job observability and retry policy

## Context
Async workflows need operational visibility.

## Scope
- Structured logs for geocoding lifecycle.
- Retry policy documentation and defaults.
- Failure reason persistence.

## Out of scope
- External monitoring platform integration.

## Acceptance criteria
- Failed cases are diagnosable without local debugging.
- Retry behavior is deterministic and documented.

## Dependencies
- SP2-002.

## Implemented policy
- Retry strategy on `async` transport:
  - `max_retries: 3`
  - `delay: 1000ms`
  - `multiplier: 2`
  - `max_delay: 8000ms`
- Failed messages are routed to `failed` transport (`doctrine://default?queue_name=failed`).
- Operational commands:
  - `make messenger-consume-async`
  - `make messenger-failed-show`
  - `make messenger-failed-retry-all`
- Structured geocoding logs emitted for lifecycle events:
  - job started
  - skipped (not found / already success)
  - failed (no result / exception)
  - success
- Failure reason is persisted on station (`geocoding_last_error`) for diagnostics.

## Status
- done
