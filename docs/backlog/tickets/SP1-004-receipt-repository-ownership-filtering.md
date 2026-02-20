# SP1-004 - Receipt repository ownership filtering

## Context
Repositories currently read/write without ownership guard.

## Scope
- Inject current user context in receipt application flows.
- Filter reads and writes by owner.
- Ensure list/export APIs are owner-scoped.

## Out of scope
- Admin bypass rules.

## Acceptance criteria
- User A never sees user B receipts.
- `get/delete/export/list` are ownership-safe.
- Functional tests cover cross-user access attempts.

## Dependencies
- SP1-001, SP1-002, SP1-003.

## Status
- done
