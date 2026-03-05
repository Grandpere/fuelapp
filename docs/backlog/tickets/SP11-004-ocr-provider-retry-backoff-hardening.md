# SP11-004 - OCR provider retry/backoff hardening

## Context
OCR.Space can return transient capacity errors (`System Resource Exhaustion`) causing import jobs to fail too aggressively.

## Scope
- Refine retry classification rules for provider payload errors.
- Add deterministic retry/backoff strategy for transient OCR failures.
- Ensure failure reasons are explicit and actionable in admin/import UI.

## Out of scope
- Provider migration.

## Acceptance criteria
- Transient provider outages no longer end as immediate permanent failures.
- Retry behavior is deterministic and covered by tests.
- Error payloads remain user/operator readable.

## Dependencies
- SP3-003, SP3-004, SP4-005.

## Status
- todo
