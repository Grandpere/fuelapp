# SP4-001 - Admin access model and role policy

## Context
Back-office needs strict role boundaries.

## Scope
- Define admin role and policy model.
- Restrict admin endpoints and UI routes.
- Add checks for user/admin separation.

## Out of scope
- Fine-grained enterprise RBAC.

## Acceptance criteria
- Non-admin cannot access back-office routes.
- Admin routes are test-covered.

## Dependencies
- Sprint 1 auth.

## Status
- done
