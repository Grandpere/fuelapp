# SP6-005 - Export service hardening (CSV/XLSX)

## Context
Exports must remain robust as data volume grows.

## Scope
- Refactor export service for large datasets (streaming).
- Add optional XLSX output format.
- Add export metadata (generation date, applied filters).

## Out of scope
- PDF output.

## Acceptance criteria
- Large exports complete without memory issues.
- Export outputs match active filters and precision rules.

## Dependencies
- SP6-004.

## Status
- todo
