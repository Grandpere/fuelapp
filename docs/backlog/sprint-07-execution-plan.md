# Sprint 07 - Execution plan

## Sprint objective
Reduce full page refreshes on critical flows and enable odometer-driven maintenance follow-up.

## Recommended sequence

### Step 1 - Turbo conventions baseline
- Ticket: `SP7-001`
- Outcome: done

### Step 2 - Receipt odometer support
- Ticket: `SP7-002`
- Outcome: done

### Step 3 - Reminder bridge from odometer
- Ticket: `SP7-003`
- Outcome: done

### Step 4 - Contact page + admin shortcut
- Ticket: `SP7-004`
- Outcome: done

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.
