# SP6-006 - Performance budget and query optimization

## Context
Analytics can degrade quickly with scale.

## Scope
- Define query/performance budgets.
- Optimize heavy queries/indexes/materialization paths.
- Add profiling checks for critical endpoints.

## Out of scope
- Full load test campaign.

## Acceptance criteria
- Critical endpoints stay within budget thresholds.
- Index/query choices are documented.

## Dependencies
- SP6-001..SP6-005.

## Delivery notes
- KPI read SQL changed from optional `OR` predicates to strict dynamic filters (`WHERE owner_id = ... AND ...`) so PostgreSQL can use selective indexes.
- Added index `idx_analytics_daily_owner_fuel_day (owner_id, fuel_type, day)` for fuel-type filtered KPI reads.
- Kept read-model strategy (pre-aggregated daily table) unchanged to avoid high-risk redesign in this sprint.

## Performance budget (P95 targets)
- `GET /api/analytics/kpis/cost-per-month`: <= 120 ms
- `GET /api/analytics/kpis/consumption-per-month`: <= 120 ms
- `GET /api/analytics/kpis/average-price`: <= 100 ms
- `GET /ui/analytics` (server-side render): <= 180 ms

## Profiling checks
- Use PostgreSQL explain plan to verify index usage:
  - `EXPLAIN (ANALYZE, BUFFERS) SELECT ... FROM analytics_daily_fuel_kpis WHERE owner_id = ... AND fuel_type = ... AND day >= ... AND day <= ...`
- Validate no sequential scan on critical API patterns when filters are present (`vehicle`, `station`, `fuel`, `from/to`).
- Track budget in local QA with realistic owner dataset after projection refresh.

## Status
- done
