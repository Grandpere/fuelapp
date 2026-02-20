# SP1-005 - Station repository ownership filtering

## Context
Stations are shared implicitly today.

## Scope
- Scope station queries to current owner.
- Scope station create/delete to owner.
- Preserve dedup logic per owner.

## Out of scope
- Global station catalog.

## Acceptance criteria
- Cross-user station visibility is impossible.
- Station identity uniqueness is owner-scoped.
- API station endpoints respect ownership.

## Dependencies
- SP1-001, SP1-002, SP1-003.

## Status
- todo
