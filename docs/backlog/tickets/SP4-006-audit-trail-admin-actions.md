# SP4-006 - Audit trail for admin actions

## Context
Admin mutations must be traceable.

## Scope
- Log admin action events with actor, target, diff summary and timestamp.
- Persist audit trail and expose read endpoint.
- Add correlation id for debugging.

## Out of scope
- External SIEM integration.

## Acceptance criteria
- Critical admin actions are reconstructible.
- Audit entries are immutable in normal flows.

## Dependencies
- SP4-002, SP4-005.

## Status
- done
