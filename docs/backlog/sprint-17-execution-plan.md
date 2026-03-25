# Sprint 17 - Execution plan

## Sprint objective
Improve the app's operational visibility and hot-path performance now that core user flows are in better shape.

## Recommended sequence

### Step 1 - Analytics query profiling and hot-path optimization
- Ticket: `SP17-001`
- Outcome: dashboard and analytics filters stay responsive as usage grows.

### Step 2 - Import pipeline observability polish
- Ticket: `SP17-002`
- Outcome: duplicate/retry/failure triage becomes faster and more actionable.

### Step 3 - Front runtime weight and cache polish
- Ticket: `SP17-003`
- Outcome: runtime reload/cache issues are less visible and frontend delivery is easier to reason about.

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.
