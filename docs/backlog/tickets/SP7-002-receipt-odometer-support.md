# SP7-002 - Receipt odometer end-to-end support

## Context
Receipt flows lacked odometer capture, preventing downstream mileage-based maintenance logic.

## Scope
- Add nullable `odometerKilometers` on receipt domain/persistence/API/UI.
- Support value in manual create/edit and import review/finalize paths.
- Expose value in list/detail/export payloads.

## Out of scope
- Reminder engine logic update based on receipt odometer (`SP7-003`).

## Acceptance criteria
- Odometer is accepted as non-negative integer on all receipt input paths.
- Odometer appears in receipt list/detail/export outputs.
- Import review/finalize supports manual odometer correction.

## Delivery notes
- Added DB migration for `receipts.odometer_kilometers` with non-negative check constraint.
- Updated receipt command/domain/repository and API resource contracts.
- Updated front templates (create, list, detail) and import review forms (user/admin).
- Added/updated unit, integration and functional coverage for new field.

## Status
- done
