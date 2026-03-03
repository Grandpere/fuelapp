# SP9-001 - BO users CRUD operational controls

## Context
Back-office lacks user lifecycle controls for support and operations.

## Scope
- Add users listing with filters in BO UI and API.
- Add account activation/deactivation controls.
- Add admin role promotion/demotion controls.
- Ensure actions are protected by `ROLE_ADMIN` and audited.

## Acceptance criteria
- Admin can filter users by email, role, status.
- Admin can activate/deactivate and promote/demote users from BO.
- Changes are visible in API and UI, and are audit-logged.

## Status
- done
