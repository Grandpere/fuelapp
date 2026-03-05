# SP12-003 - Import/upload security hardening

## Context
Import/upload is business-critical and exposed to untrusted files.

## Scope
- Tighten upload validation (mime/extension mismatch handling, archive safety checks).
- Prevent dangerous archive/file patterns (zip-slip/path traversal checks revalidation).
- Add defensive limits for file processing and OCR handoff.

## Out of scope
- OCR provider migration.

## Acceptance criteria
- Hostile file scenarios are rejected safely with explicit errors.
- Existing valid import formats still pass.

## Dependencies
- SP3 upload/import pipeline.

## Status
- in_progress
