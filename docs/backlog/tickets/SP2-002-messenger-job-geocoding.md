# SP2-002 - Messenger job and handler for geocoding

## Context
Geocoding must not block create flows.

## Scope
- Create message + handler for station geocoding.
- Configure transport/retry/dead-letter.
- Ensure idempotent processing by station id and state.

## Out of scope
- OCR/import integration.

## Acceptance criteria
- Job dispatch works from create/update station flow.
- Failed jobs are retried and observable.

## Dependencies
- SP2-001.

## Status
- todo
