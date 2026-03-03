# Sprint 10 - Execution plan

## Sprint objective
Ship a usable local observability setup fast, then decide if/when to migrate to a more modular stack.

## Recommended sequence

### Step 1 - SigNoZ baseline
- Ticket: `SP10-001`
- Outcome: done

### Step 2 - Structured logs and correlation
- Ticket: `SP10-002`
- Outcome: done

### Step 3 - Runbook and diagnostics
- Ticket: `SP10-003`
- Outcome: done

### Step 4 - Modular stack decision ticket
- Ticket: `SP10-004`
- Outcome: done

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.

### Step 5 - Hardening baseline
- Ticket: `SP10-005`
- Outcome: todo

