# TODO - SP38-001 Public fuel station import and cache

## Plan
- [completed] Review project memory and start from an up-to-date dedicated branch.
- [completed] Inspect the data.gouv instant v2 CSV shape and choose a local-cache model.
- [completed] Implement parser, importer, persistence, sync command and tests.
- [completed] Run quality gates and review the diff for PR readiness.
- [pending] Hand over migration/sync commands for user-side validation.

# TODO - SP38-002 Admin public station sync monitor

## Plan
- [completed] Add read-only admin diagnostics for sync health, freshness and failures.

# TODO - SP38-003 Public fuel station map

## Plan
- [completed] Add a public map/list from cached public stations with practical fuel filters.

# TODO - SP38-004 Visited/public station matching

## Plan
- [completed] Add safe matching between user visited stations and public station records.

# TODO - SP39-001 Analytics visited/public station map fusion

## Plan
- [completed] Inspect analytics map and matching data already available in the codebase.
- [completed] Add a read model that enriches visited station points with nearby public station context.
- [completed] Update the analytics map/list UI to show the merged station context clearly.
- [completed] Add or update unit/integration/functional coverage and run quality gates.
