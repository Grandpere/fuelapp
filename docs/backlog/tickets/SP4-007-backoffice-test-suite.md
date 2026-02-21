# SP4-007 - Back-office test suite

## Context
Back-office changes carry business risk.

## Scope
- Add functional tests for admin authz and CRUD flows.
- Add tests for import job operations and audit creation.
- Wire into CI gates.

## Out of scope
- UI screenshot tests.

## Acceptance criteria
- CI blocks regressions in back-office behavior.
- Admin/non-admin boundary is validated.

## Dependencies
- SP4-001..SP4-006.

## Status
- done
