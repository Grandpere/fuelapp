# SP2-003 - Nominatim provider adapter with rate-limit compliance

## Context
Need a free geocoding provider for initial rollout.

## Scope
- Implement Nominatim adapter behind interface.
- Respect usage policy (user-agent, throttling, caching).
- Normalize provider responses.

## Out of scope
- Paid provider fallback.

## Acceptance criteria
- Valid addresses resolve to coordinates.
- Rate-limit and transient errors are handled gracefully.

## Dependencies
- SP2-001.

## Status
- done
