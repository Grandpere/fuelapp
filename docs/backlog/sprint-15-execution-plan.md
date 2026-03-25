# Sprint 15 - Execution plan

## Sprint objective
Improve the product flows users touch most often so import, review, and follow-up correction steps feel complete instead of technically available but cumbersome.

## Recommended sequence

### Step 1 - Import review multi-line finalization
- Ticket: `SP15-001`
- Outcome: when OCR extracts several fuel lines, both user and admin review flows can inspect and finalize all of them.

### Step 2 - Bulk import feedback and recovery polish
- Ticket: `SP15-002`
- Outcome: bulk uploads become easier to understand and recover from when some files are accepted and others rejected.

### Step 3 - Product friction cleanup across front/admin
- Ticket: `SP15-003`
- Outcome: remove the remaining small but recurring UX frictions that slow down day-to-day usage.

## Mandatory checks per ticket
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Ask user to run `make phpunit-functional` and share failures.
