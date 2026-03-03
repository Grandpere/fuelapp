# SP8-003 - Compared cost graph (fuel vs maintenance vs total)

## Context
Users need a monthly comparison between fuel and maintenance costs.

## Scope
- Add API/read-model support for compared monthly series.
- Add dashboard chart panel for fuel vs maintenance vs total trend.

## Acceptance criteria
- Monthly series includes `fuelCostCents`, `maintenanceCostCents`, `totalCostCents`.
- Filters are aligned with analytics dashboard (`from/to/vehicle` and fuel/station impact fuel side).
- Dashboard exposes a clear monthly comparison block.

## Delivery notes
- Added endpoint `/api/analytics/kpis/compared-cost-per-month`.
- Added dashboard panel `Compared cost trend (fuel vs maintenance vs total)`.
- Added functional assertions on API payload and UI rendering.

## Status
- done
