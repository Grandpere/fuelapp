# Sprint 04 - Execution plan (day-by-day)

## Sprint objective
Deliver an operational back-office for entities and import jobs with secure admin boundaries and auditability.

## Assumptions
- Sprints 01-03 are merged.
- Import pipeline and statuses already exist.
- Admin role model is available from security foundation.

## Recommended sequence

### Day 1 - Admin access model
- Ticket: `SP4-001`
- Work:
  - Define admin role policy and route boundaries.
  - Protect back-office API/UI namespaces.
  - Add baseline authz tests.
- Exit criteria:
  - Non-admin access is denied everywhere in back-office scope.

### Day 2 - Back-office API entities
- Ticket: `SP4-002`
- Work:
  - Add admin CRUD API for stations and vehicles.
  - Add basic filtering/sorting.
  - Keep ownership/security constraints explicit.
- Exit criteria:
  - Admin endpoints functional and testable.

### Day 3 - Back-office UI shell
- Ticket: `SP4-003`
- Work:
  - Build admin layout with navigation.
  - Create basic list/detail pages for managed entities.
  - Ensure responsive behavior.
- Exit criteria:
  - Admin can navigate from one entrypoint to all core sections.

### Day 4 - Import jobs dashboard
- Ticket: `SP4-004`
- Work:
  - Add jobs list with status/date/user/source filters.
  - Add job detail view with payload/error information.
- Exit criteria:
  - Operators can identify pending/failed jobs quickly.

### Day 5 - Retry/fix/reprocess actions
- Ticket: `SP4-005`
- Work:
  - Add retry action for failed jobs.
  - Add patch/fix flow for `needs_review` jobs.
  - Requeue and finalize corrected jobs.
- Exit criteria:
  - Failed/review jobs are recoverable from UI/API.

### Day 6 - Audit trail
- Ticket: `SP4-006`
- Work:
  - Persist audit events for admin mutations.
  - Include actor, action, target, timestamp, minimal diff summary.
  - Add read endpoint for audit events.
- Exit criteria:
  - Admin actions are traceable end-to-end.

### Day 7 - Back-office tests
- Ticket: `SP4-007`
- Work:
  - Add functional tests for admin authz and CRUD.
  - Add tests for import ops actions and audit generation.
- Exit criteria:
  - CI protects core operational behaviors.

### Day 8 - UX and workflow polish
- Work:
  - Improve filtering ergonomics and empty/error states.
  - Reduce operator friction on common actions.
- Exit criteria:
  - Daily operational scenarios are smooth.

### Day 9 - Buffer and review
- Work:
  - Resolve findings.
  - Tighten edge cases and permissions.
  - Final quality run.

### Day 10 - Release prep
- Work:
  - Final regression.
  - Update docs for admin operations.
  - Record known follow-ups.

## Pull request strategy
- PR1: `SP4-001`
- PR2: `SP4-002`
- PR3: `SP4-003` + `SP4-004`
- PR4: `SP4-005`
- PR5: `SP4-006` + `SP4-007`

## Mandatory checks per PR
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Manual smoke checks for admin/non-admin boundary

## Risk log (Sprint 4)
- Risk: accidental privilege escalation.
  - Mitigation: policy-first implementation + negative tests.
- Risk: operator actions bypass validation.
  - Mitigation: reuse domain commands/validators.
- Risk: poor traceability of admin edits.
  - Mitigation: mandatory audit events on mutating actions.

## Definition of done (Sprint 4)
- `SP4-001` to `SP4-006` done, `SP4-007` pending closure.
- Back-office is restricted to admin users.
- Import operations are manageable from UI/API.
- Audit trail is persisted and queryable.
- CI and docs are updated.
