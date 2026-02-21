# SP5-008 - Maintenance admin back-office exposure

## Context
Maintenance features are user-facing first, but operations/support may need admin visibility and actions.

## Scope
- Expose maintenance events and reminder states in `/api/admin/*` and `/ui/admin/*`.
- Add admin filters (owner, vehicle, status/type, due windows).
- Define allowed admin actions (read-only baseline, optional corrective actions if approved).
- Ensure admin audit trail is recorded for maintenance mutations.

## Out of scope
- Changing core reminder engine logic.
- Reworking user-facing maintenance UX.

## Acceptance criteria
- Admin can query maintenance data without bypassing explicit policies.
- If admin mutations are enabled, all actions are audited.
- Functional coverage includes admin/non-admin boundaries on new endpoints/pages.

## Dependencies
- SP5-002, SP5-003, SP5-004 (minimum data model + reminder state available).

## Status
- todo
