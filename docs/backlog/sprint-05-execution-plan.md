# Sprint 05 - Execution plan (day-by-day)

## Sprint objective
Introduce a robust maintenance domain with reminders and planned vs actual cost tracking.

## Assumptions
- Core receipt and admin flows are stable.
- Vehicle entity exists (or is delivered at Sprint 4 start).
- Reminder scheduling can use existing async infra.

## Recommended sequence

### Day 1 - Maintenance context foundation
- Ticket: `SP5-001`
- Work:
  - Create maintenance domain/application/infrastructure/UI skeleton.
  - Define entities/value objects/repositories.
  - Add migration base.
- Exit criteria:
  - Context compiles and is wired cleanly.

### Day 2 - Maintenance event API
- Ticket: `SP5-002`
- Work:
  - Implement maintenance event model and CRUD API.
  - Add validation for date/odometer/cost fields.
- Exit criteria:
  - Events manageable through API.

### Day 3 - Reminder rule engine
- Ticket: `SP5-003`
- Work:
  - Add rules for date, odometer, and whichever-comes-first.
  - Compute due state per vehicle/service type.
- Exit criteria:
  - Rule evaluation deterministic and testable.

### Day 4 - Scheduler and notifications
- Ticket: `SP5-004`
- Work:
  - Add periodic reminder generation job.
  - Add notification abstraction (in-app baseline).
  - Prevent duplicate reminders.
- Exit criteria:
  - Due reminders generated automatically.

### Day 5 - Planned vs actual costs
- Ticket: `SP5-005`
- Work:
  - Add planned maintenance cost model.
  - Link actual events and compute variance.
- Exit criteria:
  - Planned/actual comparison available by vehicle/period.

### Day 6 - Maintenance UI
- Ticket: `SP5-006`
- Work:
  - Build timeline and planner views.
  - Add create/edit flows for events/rules.
- Exit criteria:
  - Core maintenance workflows usable on mobile/desktop.

### Day 7 - Tests and regression matrix
- Ticket: `SP5-007`
- Work:
  - Add rule-focused and flow-focused tests.
  - Cover edge cases for intervals and odometer boundaries.
- Exit criteria:
  - CI catches maintenance regressions.

### Day 8 - Admin back-office coverage
- Ticket: `SP5-008`
- Work:
  - Expose maintenance observability/operations in admin API/UI.
  - Add role-boundary tests and audit hooks for admin mutations.
- Exit criteria:
  - Admin can operate maintenance scope without policy regressions.

### Day 9 - UX polish and data quality pass
- Work:
  - Improve reminder clarity and planner readability.
  - Validate unit/currency consistency.
- Exit criteria:
  - Maintenance UX is coherent and low-friction.

### Day 10 - Buffer and review
- Work:
  - Resolve findings.
  - Tighten business rule edge cases.
  - Final quality run.

### Day 11 - Release prep
- Work:
  - Final regression.
  - Update maintenance docs and examples.
  - Record Sprint 6 integration points.

## Pull request strategy
- PR1: `SP5-001` + `SP5-002`
- PR2: `SP5-003` + `SP5-004`
- PR3: `SP5-005`
- PR4: `SP5-006`
- PR5: `SP5-007` + `SP5-008`

## Mandatory checks per PR
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Reminder scenario smoke tests

## Risk log (Sprint 5)
- Risk: reminder logic ambiguity.
  - Mitigation: explicit rule precedence and fixtures.
- Risk: odometer/date unit mismatch.
  - Mitigation: strict validation + consistent value objects.
- Risk: maintenance context leaking into receipt internals.
  - Mitigation: clear boundaries and contracts.

## Definition of done (Sprint 5)
- All `SP5-*` tickets done.
- Maintenance events and reminders are functional.
- Planned vs actual costs are computed and visible.
- Tests and docs updated; CI green.
