# SP29-002 - Admin station workflow usefulness

## Why
- Admin stations are still mostly static address records, while support users often need to understand the linked receipt traffic and bounce quickly into that context.
- After improving admin receipts and vehicles, stations should become another practical support surface instead of an isolated CRUD endpoint.

## Scope
- enrich the admin station list with linked receipt and vehicle context
- add direct shortcuts from admin stations into station-scoped receipts
- make the admin station detail page useful for support follow-up with recent receipts and linked vehicle context
- keep station edit flows anchored to the right admin return context

## Out of scope
- redesigning geocoding flows
- adding analytics-specific admin screens
- broader receipt filtering beyond what is needed to support station-driven triage

## Validation
- the admin station list makes receipt activity and linked fleet context visible at a glance
- the admin station detail page provides direct follow-up toward the linked receipt stream
- editing a station from list or detail keeps the admin operator in the expected context
