# Sprint 12 - Execution plan

## Sprint objective
Systematically harden critical security paths before scaling feature scope.

## Recommended sequence

### Step 1 - Auth/session hardening
- Ticket: `SP12-001`
- Outcome: todo

### Step 2 - API abuse + input hardening
- Ticket: `SP12-002`
- Outcome: todo

### Step 3 - Import/upload hardening
- Ticket: `SP12-003`
- Outcome: todo

### Step 4 - Security observability/runbook hardening
- Ticket: `SP12-004`
- Outcome: todo

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.
