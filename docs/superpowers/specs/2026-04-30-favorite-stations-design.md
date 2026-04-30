# SP41-001 Favorite stations design

## Goal
Add lightweight per-user favorite stations so people can quickly mark the stations they care about most and spot them easily in the app.

## Why now
The station model is now much more stable:
- manual receipts can reuse existing stations
- public station suggestions resolve into internal `Station`
- internal stations can keep a durable `publicSourceId`

That makes `Station` the right canonical object for favorites. We do not need a parallel favorite concept on `PublicFuelStation` in this first version.

## Scope
This ticket covers:
- a user-owned favorite relation on internal `Station`
- toggle actions from station list and station detail pages
- favorite visibility in analytics station context
- simple visual highlighting in station list, station detail, and analytics

This ticket does not cover:
- favorites on `PublicFuelStation`
- filters such as `favorites only`
- sorting favorites first everywhere
- admin/back-office support
- notifications, sharing, or team-level favorites

## Domain decision
Favorites belong to a user, not to a station globally.

Recommended model:
- `FavoriteStation`
  - `id` UUIDv7
  - `ownerId` string UUID
  - `stationId` string UUID
  - `createdAt` DateTimeImmutable

Persistence rules:
- unique constraint on `(owner_id, station_id)`
- deleting a station should cascade-delete its favorite rows
- deleting a user should cascade-delete its favorite rows

This keeps the feature explicit and avoids polluting `Station` with user-specific flags.

## Application design
### Write side
Add a small toggle command flow:
- `ToggleFavoriteStationCommand(ownerId, stationId)`
- `ToggleFavoriteStationHandler`

Behavior:
- if the user already favorited the station, remove the favorite
- otherwise create it
- reject access when the station is not visible to the user

Alternative considered:
- separate `AddFavoriteStationCommand` and `RemoveFavoriteStationCommand`

Decision:
- start with toggle because the UI entry points are simple star buttons and the behavior is naturally binary

### Read side
Add a favorite reader/repository API so controllers can efficiently answer:
- is this station favorited by the current user?
- which station ids are favorited among a list of station ids?

Recommended read API:
- `FavoriteStationRepository::isFavorite(string $ownerId, string $stationId): bool`
- `FavoriteStationRepository::favoriteStationIds(string $ownerId, array $stationIds): array`

This avoids N+1 queries in list/analytics screens.

## UI behavior
### Station list `/ui/stations`
Add a compact favorite toggle in each row:
- empty star when not favorited
- filled star when favorited
- POST form with CSRF

Visual effect:
- keep current table structure
- add a small badge or stronger title treatment for favorites
- no row reordering in v1 unless trivial

### Station detail `/ui/stations/{id}`
Add a favorite toggle near the main actions:
- `Add favorite`
- `Remove favorite`

Also show a small status hint in station context:
- `Favorite station`

### Analytics `/ui/analytics`
Do not add editing controls in v1.
Instead:
- show whether the currently filtered station is a favorite when a station filter is active
- optionally mark visited station rows or map fallback items as favorites if the data is already available at low cost

The key idea is visibility first, editing second.

## Security
Favorites are per authenticated user.

Rules:
- users can only toggle favorites for stations they can view
- use existing station ownership/view rules instead of inventing a new access model

## Admin coverage
Deferred deliberately.

Why admin support could be useful later:
- support/debugging user preference issues
- account cleanup/audit

Why it is not necessary now:
- favorites are low-risk personal preferences
- there is no operational workflow depending on them yet

Impact of deferring admin support:
- no product blocker
- only reduced support visibility if a user reports a favorites issue

## Migration
Add a new table, for example `favorite_stations`:
- `id UUID PRIMARY KEY`
- `owner_id UUID NOT NULL`
- `station_id UUID NOT NULL`
- `created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL`
- foreign keys to `users(id)` and `stations(id)` with cascade delete
- unique index on `(owner_id, station_id)`

## Tests
### Unit
- toggling creates a favorite when none exists
- toggling removes an existing favorite
- toggling rejects inaccessible/missing station ids

### Integration
- repository can answer `isFavorite()`
- repository can return favorite ids for a station-id list
- unique constraint behaves correctly

### Functional
- station list can add/remove a favorite
- station detail can add/remove a favorite
- analytics shows favorite context for a selected favorite station

## UX notes
We should avoid oversized star buttons or floating controls.
Keep the visual treatment compact and consistent with the existing button scale.

## Follow-up tickets
Natural next steps after this ticket:
1. favorites-first sorting or filtering in station list and analytics
2. favorite hints in receipt creation/picker flows
3. optional public-station favorite concept only if product really needs it later
