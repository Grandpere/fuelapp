# SP7-003 - Bridge receipt odometer to maintenance due-state reminders

## Context
Maintenance due-state currently relies on maintenance events only; receipt mileage should contribute.

## Scope
- Include latest known odometer from receipts when evaluating reminder due-state.
- Keep deterministic due computation with ownership-safe reads.
- Trigger in-app reminder/notice generation when kilometer thresholds are crossed.

## Out of scope
- Email notification channel.

## Acceptance criteria
- Kilometer-based rules become due when latest known mileage exceeds threshold.
- Reminder creation stays deduplicated and ownership-safe.

## Delivery notes
- Added `VehicleCurrentOdometerResolver` and default implementation aggregating:
  - max odometer from maintenance events,
  - max odometer from receipts for the same owner+vehicle.
- Reminder evaluation handler now relies on this resolver before due-state computation.
- Added receipt repository contract method `maxOdometerKilometersForOwnerAndVehicle`.
- Added unit and integration coverage for receipt-driven kilometer due reminders.

## Status
- done
