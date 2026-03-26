# Sprint 21 Execution Plan

## Strategy
- Start with the highest-frequency page: the front receipt list.
- Keep Sprint 21 front-first and defer admin parity unless clear support or operations pressure appears.
- Prefer low-risk, workflow-oriented improvements over large layout rewrites.

## Ticket order
1. SP21-001 - Receipt list productivity pass
2. SP21-002 - Station workflow usefulness pass
3. SP21-003 - Import post-processing clarity

## Validation policy
- Run `make phpstan`
- Run `make phpunit-unit`
- Run `make phpunit-integration`
- Run `make php-cs-fixer-check`
- Ask the user to run `make phpunit-functional` for each completed ticket
