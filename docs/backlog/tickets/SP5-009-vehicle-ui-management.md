# SP5-009 - Vehicle UI management (user + admin)

## Context
Vehicle domain was exposed in API and admin read views, but no direct web forms existed to manage fleet entries.

## Scope
- Add user web UI for owned vehicle CRUD (`/ui/vehicles`).
- Add admin web UI edit/delete actions for vehicles (`/ui/admin/vehicles/*`).
- Keep ownership and CSRF constraints explicit.
- Add functional coverage for both user and admin vehicle mutation flows.

## Out of scope
- Vehicle import automation.
- Advanced vehicle metadata (brand/model/year).

## Acceptance criteria
- A ROLE_USER can create/edit/delete only owned vehicles from web UI.
- A ROLE_ADMIN can view/edit/delete vehicles from back-office UI, but cannot create them.
- Functional tests validate both flows.

## Dependencies
- SP4-002 (admin vehicle API foundation).
- SP5-006/SP5-008 (user/admin UI continuity).

## Status
- done
