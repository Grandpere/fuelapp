# SP3-009 - Optional API Platform native upload operation refactor

## Context
Upload endpoint `/api/imports` is currently a Symfony controller route and documented through an OpenAPI decorator.

## Scope
- Refactor upload endpoint to a native API Platform operation.
- Keep multipart behavior and validation unchanged.
- Keep response contract (`id`, `status`, `createdAt`) unchanged.

## Out of scope
- Async pipeline behavior changes.
- OCR/provider integration.

## Acceptance criteria
- `/api/imports` appears in docs without custom decorator wiring.
- Existing upload functional tests remain green.
- No behavior regression on auth/validation/status code contracts.

## Dependencies
- SP3-002.

## Status
- done
