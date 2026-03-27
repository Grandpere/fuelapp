# SP24-001 - Analytics filter workflow polish

## Why
The analytics dashboard already exposes rich data, but filtering it still feels more manual than the other front-office screens.

## Expected outcome
- `/ui/analytics` exposes quick time-range shortcuts and a readable summary of active filters.
- The current analytics context offers direct next steps into related receipts or maintenance flows.
- Users can understand the current scope faster without re-reading every input field.

## Notes
- Front-office only for now.
- No admin parity needed at this stage.
- Delivered with a projection-recovery guard: when the source receipts match the current filters but `analytics_daily_fuel_kpis` is stale, the dashboard refreshes the projection before rendering.
