# SP1-005 - Station repository ownership filtering

## Context
Stations are shared implicitly today.

## Scope
- Scope station read operations to current user's visible perimeter (stations linked to user's receipts).
- Scope station delete to current user perimeter and prevent cross-user data impact.
- Keep station identity dedup global (not per-user ownership).

## Out of scope
- Global station catalog.

## Acceptance criteria
- Cross-user station visibility is impossible through repository reads.
- Station identity uniqueness remains global.
- API station endpoints respect user perimeter.

## Dependencies
- SP1-001, SP1-002, SP1-003.

## Status
- done
