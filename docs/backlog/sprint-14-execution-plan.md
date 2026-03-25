# Sprint 14 - Execution plan

## Sprint objective
Harden the new frontend/runtime surface so theming, charts, and shared UI delivery remain secure, cacheable, and maintainable as the product grows.

## Recommended sequence

### Step 1 - Security headers and CSP baseline
- Ticket: `SP14-001`
- Outcome: define a realistic browser hardening baseline compatible with the current app.

### Step 2 - Asset sovereignty and dependency reduction
- Ticket: `SP14-002`
- Outcome: reduce CDN/runtime coupling so the app can move toward a stricter CSP and more predictable local/prod delivery.

### Step 3 - Theme/chart runtime hardening
- Ticket: `SP14-003`
- Outcome: make theme + chart behavior more robust under navigation, cache, and JS runtime changes, with explicit regression coverage.

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.
