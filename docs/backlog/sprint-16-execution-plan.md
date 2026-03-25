# Sprint 16 - Execution plan

## Sprint objective
Reduce the small front-office frictions that still make routine data entry and navigation feel more technical than they should.

## Recommended sequence

### Step 1 - Receipt create/edit flow polish
- Ticket: `SP16-001`
- Outcome: users can create and edit receipts with human-friendly units and clearer guidance instead of internal storage values.

### Step 2 - Maintenance flow clarity
- Ticket: `SP16-002`
- Outcome: maintenance event/reminder flows expose clearer next actions and less ambiguous empty states.

### Step 3 - Search/filter persistence across key lists
- Ticket: `SP16-003`
- Outcome: receipt/import/admin list actions no longer make users lose their working context as often.

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.
