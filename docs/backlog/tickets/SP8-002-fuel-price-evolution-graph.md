# SP8-002 - Fuel price evolution graph

## Context
Users need a time-series view of fuel price progression by period and fuel type.

## Scope
- Add API/read-model support for price evolution series.
- Add dashboard chart panel for average fuel price over time.

## Acceptance criteria
- Series respects analytics filters (`from/to/vehicle/station/fuel`).
- Monthly average price uses the same unit as KPI (`deci-cents per liter`).
- Dashboard displays monthly fuel price trend entries.

## Delivery notes
- Added API/read-model monthly series endpoint `/api/analytics/kpis/fuel-price-per-month`.
- Added dashboard trend panel `Fuel price trend by month`.
- Added functional assertions on API payload and dashboard rendering.

## Status
- done
