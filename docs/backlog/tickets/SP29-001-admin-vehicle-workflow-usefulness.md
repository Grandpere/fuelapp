# SP29-001 - Admin vehicle workflow usefulness

## Why
- Admin vehicles currently act mostly as static records, which forces support users to hop manually toward receipts and maintenance context.
- After Sprint 28 improved receipts and maintenance individually, vehicles should become a practical support hub for those linked workflows.

## Scope
- enrich the admin vehicle list with receipt and maintenance context that helps support users scan the fleet faster
- add direct shortcuts from admin vehicles into vehicle-scoped receipts and maintenance queues
- make the admin vehicle detail page useful for support follow-up instead of being only a CRUD surface
- keep edit flows anchored to the right admin return context

## Out of scope
- redesigning the whole vehicle domain model
- adding new admin creation flows for vehicles
- station-specific support flows beyond what is needed to show recent linked receipts

## Validation
- the admin vehicle list makes it easy to spot which vehicles already have receipts and maintenance pressure
- the admin vehicle detail page offers direct follow-up shortcuts toward receipts and maintenance
- editing a vehicle from list or detail keeps the admin operator in the right context
