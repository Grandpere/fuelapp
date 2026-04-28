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
- [pending] Add safe matching between user visited stations and public station records.
