# Sprint 38 Execution Plan

1. Import and cache public fuel stations from the data.gouv instant v2 feed with a repeatable console sync command.
2. Add admin diagnostics for sync status, freshness, counts and failed runs before exposing the data broadly.
3. Build the public station map from the local cache with useful fuel/service filters.
4. Add matching between visited stations and public stations once both sides are stable enough to avoid confusing users.

## Validation
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- User-run functional suite when UI/API behavior is added.
