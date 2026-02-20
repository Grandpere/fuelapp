# Sprint 06 - Execution plan (day-by-day)

## Sprint objective
Deliver reliable analytics and hardened exports for product-level reporting.

## Assumptions
- Receipt/import/maintenance data model is stable enough for KPI definitions.
- Existing list/export foundation already works.
- Performance optimization is in scope, not deferred.

## Recommended sequence

### Day 1 - Analytics read model design
- Ticket: `SP6-001`
- Work:
  - Define aggregate read model and refresh strategy.
  - Add schema/indexes/materialization approach.
  - Define freshness metadata.
- Exit criteria:
  - KPI read path architecture validated.

### Day 2 - KPI API endpoints
- Ticket: `SP6-002`
- Work:
  - Implement KPI endpoints with period/vehicle filters.
  - Enforce unit/precision consistency.
- Exit criteria:
  - API returns deterministic KPI payloads.

### Day 3 - Dashboard UI
- Ticket: `SP6-003`
- Work:
  - Build dashboard cards and trend views.
  - Wire filter controls to KPI API.
- Exit criteria:
  - Dashboard displays key metrics and trends.

### Day 4 - Multi-filter analytics behavior
- Ticket: `SP6-004`
- Work:
  - Add station/fuel/vehicle/time filters.
  - Ensure parity between UI view and backend filters.
- Exit criteria:
  - Filtered views are consistent across components.

### Day 5 - Export hardening
- Ticket: `SP6-005`
- Work:
  - Stream large exports safely.
  - Add optional XLSX format support.
  - Include metadata (filters/date generated).
- Exit criteria:
  - Large export works without memory spikes.

### Day 6 - Performance optimization
- Ticket: `SP6-006`
- Work:
  - Define budget targets and profile bottlenecks.
  - Optimize heavy queries/indexes.
  - Document performance tradeoffs.
- Exit criteria:
  - Critical endpoints within defined budget.

### Day 7 - Analytics validation suite
- Ticket: `SP6-007`
- Work:
  - Add fixture-based metric correctness tests.
  - Validate rounding/precision and export parity.
- Exit criteria:
  - CI catches KPI correctness regressions.

### Day 8 - UX and product polish
- Work:
  - Improve dashboard readability and state transitions.
  - Polish export UX feedback and empty states.
- Exit criteria:
  - Reporting UX feels production-ready.

### Day 9 - Buffer and review
- Work:
  - Resolve findings.
  - Tighten edge cases and consistency.
  - Final quality run.

### Day 10 - Release prep
- Work:
  - Final regression.
  - Update analytics docs and KPI definitions.
  - Close sprint with follow-up list.

## Pull request strategy
- PR1: `SP6-001`
- PR2: `SP6-002` + `SP6-003`
- PR3: `SP6-004` + `SP6-005`
- PR4: `SP6-006`
- PR5: `SP6-007`

## Mandatory checks per PR
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Manual KPI/export smoke checks on realistic dataset

## Risk log (Sprint 6)
- Risk: KPI mismatch between API, UI and exports.
  - Mitigation: shared query services + parity tests.
- Risk: performance degradation with dataset growth.
  - Mitigation: budgets + indexed read models + streaming exports.
- Risk: precision/rounding inconsistencies.
  - Mitigation: centralized money/units formatting and test fixtures.

## Definition of done (Sprint 6)
- All `SP6-*` tickets done.
- KPI API + dashboard + exports are consistent.
- Performance budget met on critical paths.
- Validation tests and docs are complete.
