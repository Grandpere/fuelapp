# SP2-004 - Trigger geocoding from station creation/update

## Context
Geocoding should be automatic for new or changed addresses.

## Scope
- Dispatch geocoding job on station create.
- Redispatch on identity/address change.
- Avoid unnecessary redispatch when unchanged.

## Out of scope
- Manual admin requeue UI.

## Acceptance criteria
- New station gets `pending` then `success/failed`.
- Address edits trigger new job.

## Dependencies
- SP2-002, SP2-003.

## Status
- done
