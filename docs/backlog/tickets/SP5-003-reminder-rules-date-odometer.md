# SP5-003 - Reminder rules (date/odometer)

## Context
Users need preventive maintenance triggers.

## Scope
- Model reminder rules with date and odometer thresholds.
- Support "whichever comes first" logic.
- Compute next due state per vehicle.

## Out of scope
- Notification channel delivery.

## Acceptance criteria
- Reminder due calculation is deterministic and tested.
- Rules support common maintenance intervals.

## Dependencies
- SP5-002.

## Status
- done
