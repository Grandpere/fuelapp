# SP8-001 - Visited stations map

## Context
Users need spatial visibility on where fuel spend happens with same filters as analytics KPIs.

## Scope
- Add read-model query returning geocoded visited stations with cost/volume/receipt aggregates.
- Expose data via API endpoint `/api/analytics/stations/visited`.
- Render a map section in analytics dashboard with an accessible fallback list.

## Out of scope
- Historical station movement clustering.
- External geospatial providers requiring API keys.

## Acceptance criteria
- Map data respects `from/to/vehicle/station/fuel` filters exactly like KPI endpoints.
- Only stations with coordinates are displayed.
- API and dashboard expose consistent aggregated station values.

## Delivery notes
- Added `readVisitedStations(...)` in analytics read-model contract and Doctrine implementation.
- Added API collection endpoint `/api/analytics/stations/visited`.
- Added analytics dashboard section with Leaflet/OpenStreetMap rendering and textual fallback list.
- Added functional coverage for API and dashboard rendering.

## Status
- done
