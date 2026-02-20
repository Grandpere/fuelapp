# SP3-006 - Idempotency and duplicate detection

## Context
Re-upload/retry should not create duplicate receipts.

## Scope
- Define deterministic fingerprint strategy.
- Add dedup checks before final create.
- Mark duplicates with explicit import status.

## Out of scope
- Manual dedup merge tooling.

## Acceptance criteria
- Duplicate files do not create new receipt rows.
- Import response/status explains duplicate outcome.

## Dependencies
- SP3-005.

## Status
- todo
