# SP28-003 - Admin maintenance overview usefulness pass

## Why
- Admin maintenance screens are readable, but they still force support to work from raw IDs and low-context rows.
- Operators need to understand "which vehicle, why is this due, and where should I click next?" without opening every maintenance row one by one.

## Scope
- surface vehicle names alongside IDs on admin maintenance reminder and event lists
- add quick support shortcuts from reminders and events toward the linked vehicle
- make the admin reminder detail page more usable with linked vehicle context and a fast jump to related vehicle events
- keep the pass lightweight and focused on overview usefulness rather than deep maintenance redesign

## Out of scope
- new admin maintenance CRUD flows
- front-office maintenance changes
- advanced SLA/reporting dashboards

## Validation
- admin maintenance reminders and events are easier to scan without decoding raw UUIDs first
- support can jump from reminders/events to the linked vehicle in one click
- reminder detail provides clearer context and a practical next action beyond raw fields
