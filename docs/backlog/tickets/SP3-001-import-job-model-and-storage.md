# SP3-001 - Import job model and storage strategy

## Context
Define import job persistence for async processing.

## Scope
- Create `import_jobs` model with statuses, timestamps, error payload and file reference.
- Define storage strategy for uploaded files (local/S3-ready abstraction).
- Add retention metadata.

## Out of scope
- OCR/provider implementation.

## Acceptance criteria
- Import job lifecycle is persisted and queryable.
- File references are stable and auditable.

## Dependencies
- Sprint 1 and 2 foundations.

## Status
- done
