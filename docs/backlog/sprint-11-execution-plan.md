# Sprint 11 - Execution plan

## Sprint objective
Deliver immediate value by improving OCR import reliability and cleaning UI consistency on high-traffic screens.

## Recommended sequence

### Step 1 - OCR hardening
- Ticket: `SP11-002`
- Outcome: in_progress

### Step 2 - UX consistency polish
- Ticket: `SP11-003`
- Outcome: in_progress

### Deferred item
- Ticket: `SP11-001` (Microsoft OIDC)
- Outcome: blocked (explicitly deferred until needed)

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.
