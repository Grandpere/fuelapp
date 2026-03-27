# SP26-003 - Maintenance reminder lifecycle clarity

## Why
- The maintenance dashboard already exposes rules, reminders, and plans, but the lifecycle between them is still harder to scan than it should be.
- Users should be able to tell whether a rule is merely configured, actively tracking, close to due, or already due without mentally reconstructing the whole state.

## Scope
- add explicit lifecycle stages for reminder rules
- show lifecycle summary counts on the maintenance dashboard
- add per-rule stage badges and short lifecycle copy
- keep triggered reminders and next-step actions intact

## Out of scope
- new reminder generation logic
- admin/back-office parity
- separate reminder-specific pages

## Validation
- the dashboard distinguishes configured, watching, due soon, and due now states
- mileage-only rules without odometer data read as configured instead of ambiguous
- already due rules still push the user toward triggered reminders and event logging
