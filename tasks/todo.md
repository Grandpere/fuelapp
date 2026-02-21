# TODO - SP4-006 Audit trail for admin actions

## Plan
- [completed] Introduce immutable admin audit log persistence model with correlation id metadata.
- [completed] Add audit recording service + context provider (actor identity and request correlation).
- [completed] Record critical admin mutations (stations, vehicles, import retry/finalize in API and UI flows).
- [completed] Expose read-only admin audit log endpoint with filters.
- [completed] Add functional coverage for audit recording and correlation id propagation.
- [completed] Run quality checks and finalize docs.
