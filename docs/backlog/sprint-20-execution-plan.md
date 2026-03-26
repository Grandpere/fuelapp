# Sprint 20 Execution Plan

## Strategy
- Start with the highest-frequency user loop: reviewing imports that need manual confirmation.
- Keep Sprint 20 front-first; admin coverage is intentionally deferred unless product or support needs prove it worthwhile later.
- Reuse existing import review flows and keep the implementation lightweight: queue context, fewer clicks, and stronger navigation continuity.

## Ticket order
1. SP20-001 - Import review productivity shortcuts
2. SP20-002 - Receipt and maintenance cross-links completion
3. SP20-003 - Vehicle dashboard usefulness pass

## Validation policy
- Run `make phpstan`
- Run `make phpunit-unit`
- Run `make phpunit-integration`
- Run `make php-cs-fixer-check`
- Ask the user to run `make phpunit-functional` for each completed ticket
