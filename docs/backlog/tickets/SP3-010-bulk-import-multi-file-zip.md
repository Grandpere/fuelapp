# SP3-010 - Bulk import (multi-file and ZIP)

## Context
Users should be able to import many receipts in one action instead of uploading files one by one.

## Scope
- Add a bulk import entrypoint for authenticated users (`ROLE_USER`).
- Support multiple files in one request (PDF/JPG/PNG accepted like current single upload rules).
- Support ZIP upload containing supported receipt files.
- Create one import job per extracted file, preserving owner isolation and duplicate detection rules.
- Return a summary payload (`accepted`, `rejected`, per-file reason).
- Keep processing asynchronous through Messenger.

## Out of scope
- Nested ZIP handling beyond one extraction level.
- Password-protected archives.
- UI batch management dashboard redesign.

## Acceptance criteria
- A user can submit N files in one call and receive N tracked jobs (or explicit per-file rejection reasons).
- A user can submit one ZIP and receive one job per valid file in the archive.
- Existing validation/security constraints remain enforced (owner scope, mime checks, size limits, idempotency).
- Error handling is deterministic: one bad file does not cancel the whole batch.

## Notes
- Prefer API-first implementation; UI upload wizard can be added afterward.
- Reuse current import storage + OCR + parser pipeline to avoid divergent logic.

## Dependencies
- SP3-002, SP3-003, SP3-006, SP3-009.

## Status
- todo
