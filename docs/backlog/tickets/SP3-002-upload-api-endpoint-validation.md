# SP3-002 - Upload API endpoint and validation

## Context
Users need a safe entrypoint for receipt files.

## Scope
- Add upload endpoint for image/PDF receipt input.
- Validate mime type, max size, and ownership.
- Persist import job in `queued` status.

## Out of scope
- OCR parsing logic.

## Acceptance criteria
- Invalid files are rejected with clear API errors.
- Valid upload creates import job and returns tracking id.

## Dependencies
- SP3-001.

## Status
- todo
