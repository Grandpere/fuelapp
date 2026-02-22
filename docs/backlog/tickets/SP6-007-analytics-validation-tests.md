# SP6-007 - Analytics validation tests

## Context
Metrics correctness is product-critical.

## Scope
- Add test dataset fixtures for deterministic metric checks.
- Validate aggregates, trends and export parity.
- Add regression tests for rounding/precision rules.

## Out of scope
- BI tooling integration tests.

## Acceptance criteria
- CI fails on analytics correctness regressions.
- Metric definitions remain stable over time.

## Dependencies
- SP6-001..SP6-006.

## Delivery notes
- Added deterministic KPI rounding regression coverage for `averagePriceDeciCentsPerLiter` (`HALF_UP` behavior on `.5` case).
- Added analytics/export parity functional coverage: CSV `total_cents` aggregation is validated against `/api/analytics/kpis/cost-per-month` for identical filters.
- Existing KPI fixture-based validation (ownership isolation + filters + monthly aggregates) remains in place as baseline.

## Status
- done
