# SP41-001 Favorite stations implementation plan

## Objective
Implement per-user favorite stations on top of internal `Station`, with simple toggle actions and lightweight visibility in station list, station detail, and analytics.

## Delivery strategy
Keep this feature intentionally small:
- one new persistence model
- one toggle command flow
- read-side batching to avoid N+1 queries
- UI only where the value is obvious
- no sorting/filtering/admin in this ticket

## Step 1 - Persistence model and migration
Add the new favorite entity and persistence table.

### Changes
- create domain model `FavoriteStation`
- create repository contract for favorites
- add Doctrine entity and repository implementation
- add migration for `favorite_stations`

### Table shape
- `id UUID PRIMARY KEY`
- `owner_id UUID NOT NULL`
- `station_id UUID NOT NULL`
- `created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL`
- unique index `(owner_id, station_id)`
- foreign keys with `ON DELETE CASCADE`

### Tests
- integration test for repository persistence
- integration test for unique pair enforcement
- integration test for `favoriteStationIds()` batching

## Step 2 - Toggle command flow
Add a single application command that flips favorite state.

### Changes
- `ToggleFavoriteStationCommand`
- `ToggleFavoriteStationHandler`

### Rules
- reject invalid/missing station
- reject station not viewable by current user
- if favorite exists, delete it
- otherwise create it

### Tests
- unit test create favorite
- unit test remove favorite
- unit test reject missing station
- unit test reject unauthorized station

## Step 3 - Web entry points
Expose toggle endpoints for station list and station detail.

### Changes
- add POST controller for favorite toggle
- accept `return_to` or redirect context safely
- reuse existing station view access checks
- success flash optional and compact

### UI entry points
- `templates/station/index.html.twig`
  - compact star button per row
- `templates/station/show.html.twig`
  - favorite toggle near header actions
  - small status hint in station context

### Tests
- functional: station list toggle add/remove
- functional: station detail toggle add/remove
- functional: invalid CSRF or inaccessible station remains safe

## Step 4 - Analytics visibility
Show favorite state where it is already meaningful without adding editing controls.

### Changes
- batch-load favorite ids for visited station points shown in analytics
- mark visited station rows/fallback items with `isFavorite`
- when a station filter is active and that station is favorite, show a small badge/hint

### Constraints
- no toggle actions in analytics in v1
- no favorites-only filter
- no favorites-first sorting yet

### Tests
- functional: analytics page shows favorite context when selected station is favorite
- unit or integration only if a small helper/read model is introduced

## Step 5 - Quality and handover
Run project gates and prepare manual validation.

### Commands
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`

### User-side validation
Ask the user to run:
- `make restart-app`
- `make phpunit-functional`

### Front checks
- station list star toggle
- station detail favorite state
- analytics favorite badge/context

## Notes and guardrails
- keep favorite visuals compact; avoid giant star buttons
- do not add public-station favorites in this ticket
- do not add admin coverage now
- prefer batched favorite lookups instead of per-row repository calls
