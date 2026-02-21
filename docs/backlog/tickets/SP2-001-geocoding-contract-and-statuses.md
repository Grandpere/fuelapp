# SP2-001 - Geocoding contract and statuses

## Context
Need stable async model before provider integration.

## Scope
- Define geocoding service interface.
- Add station geocoding statuses (`pending`, `success`, `failed`).
- Add persistence fields needed for retries and diagnostics.

## Out of scope
- Provider-specific HTTP implementation.

## Acceptance criteria
- Status model is persisted and queryable.
- API/UI can display geocoding status.

## Dependencies
- Sprint 1 completion.

## Status
- done
