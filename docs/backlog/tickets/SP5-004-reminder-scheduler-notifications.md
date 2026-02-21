# SP5-004 - Reminder scheduler and notifications

## Context
Due reminders should surface automatically.

## Scope
- Schedule reminder evaluation job.
- Persist reminder instances/events.
- Add notification abstraction (in-app first).

## Out of scope
- Email/SMS provider rollout.

## Acceptance criteria
- Due reminders are generated without manual actions.
- Duplicate reminder spam is prevented.

## Dependencies
- SP5-003.

## Status
- done
