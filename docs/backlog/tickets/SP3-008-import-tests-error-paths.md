# SP3-008 - Import tests and error-path coverage

## Context
Async OCR/import has many failure modes.

## Scope
- Add unit/integration/functional tests for upload->process flow.
- Cover bad mime, OCR timeout, parser failure, duplicate, review path.
- Ensure CI coverage for critical regressions.

## Out of scope
- Load/perf tests.

## Acceptance criteria
- CI fails on import regression.
- Core failure scenarios are reproducible in tests.

## Dependencies
- SP3-001..SP3-007.

## Status
- todo
