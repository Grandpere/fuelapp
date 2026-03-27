# Sprint 24 Execution Plan

1. Improve `/ui/analytics` with clearer active-filter context, time shortcuts, and direct next-step links. Completed with a self-heal pass for stale analytics projections after late receipt edits.
2. Return to `/ui/imports` to make list follow-up actions more immediate.
3. Use the final ticket as a light cohesion pass across the main front-office dashboards.

## Validation
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- user-run `make phpunit-functional`
