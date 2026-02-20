# Sprint 03 - Execution plan (day-by-day)

## Sprint objective
Ship a robust async receipt import pipeline (image/PDF) with job tracking, parsing, dedup and manual review.

## Assumptions
- Sprint 01 and 02 are merged (security + ownership + async geocoding pattern).
- Messenger queue and worker operations are stable in local/CI environments.
- First OCR provider is acceptable for MVP quality (human review remains in scope).

## Recommended sequence

### Day 1 - Import job model and storage foundation
- Ticket: `SP3-001`
- Work:
  - Add `import_jobs` persistence model (status, file reference, timestamps, error payload, owner id).
  - Add storage abstraction for uploaded files.
  - Add DB migration + fixture updates.
- Exit criteria:
  - Import jobs are persisted and queryable.
  - File reference strategy is stable.

### Day 2 - Upload entrypoint
- Ticket: `SP3-002`
- Work:
  - Add upload endpoint (API) with auth/ownership checks.
  - Validate mime/size/content constraints.
  - Create import job in `queued` status and return tracking id.
- Exit criteria:
  - Valid upload creates a job.
  - Invalid upload returns clear API errors.

### Day 3 - Async orchestration skeleton
- Ticket: `SP3-003`
- Work:
  - Dispatch import job message from upload flow.
  - Implement handler skeleton with lifecycle status transitions.
  - Configure retries and failure transport.
- Exit criteria:
  - Upload returns immediately.
  - Worker processes jobs asynchronously.

### Day 4 - OCR adapter and provider integration
- Ticket: `SP3-004`
- Work:
  - Define OCR adapter contract.
  - Implement first provider adapter.
  - Normalize OCR output into internal raw payload.
- Exit criteria:
  - OCR adapter returns deterministic payload/errors.
  - Provider logic isolated behind interface.

### Day 5 - Parsing and normalization
- Ticket: `SP3-005`
- Work:
  - Parse OCR payload to receipt draft fields.
  - Normalize dates/monetary units/fuel line values.
  - Produce validated command payload candidates.
- Exit criteria:
  - Parsed data respects domain invariants.
  - Invalid parse is explicit (not silent).

### Day 6 - Idempotency and dedup
- Ticket: `SP3-006`
- Work:
  - Add fingerprint strategy (file/content + key extracted fields).
  - Prevent duplicate receipt creation on retry/re-upload.
  - Persist duplicate outcome state.
- Exit criteria:
  - Duplicate import does not create duplicate receipt rows.

### Day 7 - Manual review flow
- Ticket: `SP3-007`
- Work:
  - Add `needs_review` status for ambiguous parsing.
  - Expose draft payload + confidence/errors.
  - Add API action to confirm/correct and finalize.
- Exit criteria:
  - Ambiguous imports are recoverable without DB edits.

### Day 8 - Test coverage hardening
- Ticket: `SP3-008`
- Work:
  - Unit/integration/functional coverage for full pipeline.
  - Include failures: bad mime, OCR timeout, parse failure, duplicate, review finalization.
- Exit criteria:
  - CI catches import regressions on all critical paths.

### Day 9 - Buffer and review
- Work:
  - Address review findings.
  - Tighten retry/idempotency edge cases.
  - Run full quality suite.

### Day 10 - Release prep
- Work:
  - Final regression and operational checklist.
  - Document import API contract and status lifecycle.
  - Capture Sprint 4 handoff items.

## Pull request strategy
- PR1: `SP3-001` + `SP3-002`
- PR2: `SP3-003`
- PR3: `SP3-004` + `SP3-005`
- PR4: `SP3-006` + `SP3-007`
- PR5: `SP3-008` + docs

## Mandatory checks per PR
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Import smoke checks:
  - upload -> job `queued`
  - worker -> status transition (`processed`, `failed`, or `needs_review`)
  - duplicate upload -> no duplicate receipt

## Risk log (Sprint 3)
- Risk: OCR quality variance across station formats.
  - Mitigation: manual review path + confidence flags.
- Risk: duplicate receipts caused by retries/concurrency.
  - Mitigation: deterministic fingerprint + idempotent finalization.
- Risk: large file handling and storage edge cases.
  - Mitigation: strict upload validation + storage abstraction + retention policy.

## Definition of done (Sprint 3)
- All `SP3-*` tickets marked done.
- Import is fully asynchronous and non-blocking.
- Job statuses and failure reasons are visible.
- Dedup/idempotency protections are active.
- Manual review path is functional.
- CI and docs are up to date.
