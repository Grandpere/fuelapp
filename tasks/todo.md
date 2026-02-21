# TODO - SP6-005 Export service hardening (CSV/XLSX)

## Plan
- [completed] Refactor receipt export to stream CSV rows in chunks for large datasets.
- [completed] Add optional XLSX export format and include export metadata (generation date + applied filters).
- [completed] Expose CSV/XLSX options in web UI export actions while preserving existing filter semantics.
- [completed] Add functional coverage for CSV metadata/filter parity and XLSX response format.
- [completed] Run quality gates, update backlog docs, and commit.
