# SP5-002 - Maintenance event model and CRUD API

## Context
Need to record maintenance operations reliably.

## Scope
- Implement maintenance event entity and API resource.
- Include fields: vehicle, type, date, odometer, cost, notes.
- Validate domain constraints.

## Out of scope
- Reminder engine.

## Acceptance criteria
- Events can be created/read/updated/deleted via API.
- Validation errors are explicit.

## Dependencies
- SP5-001.

## Status
- done
