# Sprint 01 - Execution plan (day-by-day)

## Sprint objective
Lock down API/UI access and enforce strict ownership isolation on receipts and stations.

## Assumptions
- 2-week sprint.
- 1 main developer track (you) with CI already in place.
- Target: shippable increment at end of sprint (not partial security).

## Recommended sequence

### Day 1 - Domain and persistence baseline
- Ticket: `SP1-001`
- Work:
  - Add user model/entity.
  - Add ownership columns on receipts and stations.
  - Create/verify migrations.
  - Update fixtures and factories used by tests.
- Exit criteria:
  - Schema migrates cleanly.
  - Existing app still boots.

### Day 2 - API authentication
- Ticket: `SP1-002`
- Work:
  - Add API auth mechanism.
  - Protect `/api/*` by default.
  - Standardize `401` vs `403` behavior.
- Exit criteria:
  - Anonymous API access blocked.
  - Authenticated smoke test works.

### Day 3 - UI authentication
- Ticket: `SP1-003`
- Work:
  - Add login/logout.
  - Protect `/ui/*` routes.
  - Keep existing Turbo flows working after login.
- Exit criteria:
  - Anonymous user redirected to login.
  - Authenticated user reaches receipts pages.

### Day 4 - Receipt ownership isolation
- Ticket: `SP1-004`
- Work:
  - Inject current user context in receipt create/read/list/delete/export flows.
  - Repository filtering by owner.
  - Verify API and UI paths.
- Exit criteria:
  - User A cannot read or mutate User B receipts.

### Day 5 - Station ownership isolation
- Ticket: `SP1-005`
- Work:
  - Scope station flows by owner.
  - Keep station dedup identity but owner-aware.
  - Validate station API list/get/delete by owner.
- Exit criteria:
  - No cross-user station visibility.

### Day 6 - Object-level authorization
- Ticket: `SP1-006`
- Work:
  - Add voters/policies for object operations.
  - Apply consistently in API/UI mutation points.
- Exit criteria:
  - Object access checks centralized and predictable.

### Day 7 - Security tests hardening
- Ticket: `SP1-007`
- Work:
  - Add/extend unit, integration, functional tests for auth + ownership boundaries.
  - Add negative cases (cross-user read/update/delete, anonymous requests).
- Exit criteria:
  - CI fails on security boundary regression.

### Day 8 - Docs and operational checklist
- Ticket: `SP1-008`
- Work:
  - Document setup/rotation/env requirements.
  - Add troubleshooting and local verification commands.
- Exit criteria:
  - Another dev can reproduce secure setup from docs.

### Day 9 - Buffer and review
- Work:
  - Fix review findings.
  - Tighten edge cases and naming consistency.
  - Run full local quality gates.

### Day 10 - Release prep
- Work:
  - Final regression run.
  - Merge readiness checklist.
  - Tag sprint outcome + known follow-ups.

## Pull request strategy
- Prefer small PRs by ticket or ticket pair:
  - PR1: `SP1-001`
  - PR2: `SP1-002` + `SP1-003`
  - PR3: `SP1-004` + `SP1-005`
  - PR4: `SP1-006` + `SP1-007`
  - PR5: `SP1-008`
- Keep each PR reviewable in < 500 LOC when possible.

## Mandatory checks per PR
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Security-focused manual smoke checks for touched flows

## Risk log (Sprint 1)
- Risk: breaking existing receipt UI/API during ownership injection.
  - Mitigation: add functional tests before deep refactors in repositories.
- Risk: ambiguous 401/403 behavior.
  - Mitigation: define expected matrix early and test it.
- Risk: hidden dependencies on globally visible stations.
  - Mitigation: explicit cross-user tests for station selectors and filters.

## Definition of done (Sprint 1)
- All `SP1-*` tickets marked done.
- No anonymous access to business resources.
- No cross-user data leakage on receipts/stations.
- CI green with added security tests.
- Docs updated for local and CI usage.
