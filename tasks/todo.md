# TODO - SP2-004 Trigger geocoding on station create/update

## Plan
- [completed] Add station address update flow with explicit geocoding re-trigger logic.
- [completed] Dispatch geocoding on create and on address change when station is not already pending.
- [completed] Avoid unnecessary redispatch when address is unchanged or geocoding is already pending.
- [completed] Expose station update through API PATCH operation.
- [completed] Add/update tests for update behavior and status transitions.
- [completed] Run quality gates and update backlog/docs.
