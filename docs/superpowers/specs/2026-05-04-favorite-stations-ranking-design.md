# SP41-002 Favorite stations ranking and filtering design

## Goal
Make favorites more useful in daily browsing by surfacing them first in the station index and by allowing a focused `favorites only` view.

## Why now
`SP41-001` introduced stable per-user favorites on internal `Station` records. The next valuable step is to make that preference affect the main station browsing experience without expanding the scope into heavier analytics semantics.

## Scope
This ticket covers:
- favorites-first ordering on `/ui/stations`
- a lightweight `favorites only` filter on `/ui/stations`
- small supporting visibility in analytics only if it remains presentation-only
- updated functional coverage for ordering/filter behavior

This ticket does not cover:
- a `favorites only` filter on `/ui/analytics`
- changing KPI calculations based on favorites
- favorites on `PublicFuelStation`
- admin/back-office support
- global search or picker changes

## Product decision
The station index becomes the main operational screen for favorites.

Behavior:
- favorites should appear first by default
- non-favorites still remain visible unless the user explicitly filters
- the filter must be URL-stable so the view can be refreshed/shared/bookmarked locally

Recommended query param:
- `favorites=1`

When absent or invalid:
- show all visible stations

## Ordering rules
Sort station rows with this priority:
1. `isFavorite DESC`
2. `latestIssuedAt DESC`, with `null` values last
3. `name ASC`
4. `city ASC`

This keeps favorites prominent while preserving the current “most recently useful first” behavior inside each group.

## Station list UX
### Default view
- keep the current table
- keep the current favorite toggle button per row
- keep the current `Favorite` badge
- add a compact filter control near the page actions or table header

Recommended control:
- a small secondary button or pill-like toggle labeled `Favorites only`
- active state clearly visible
- a nearby reset path remains obvious

### Empty states
We need two empty-state messages:
- no visible stations at all:
  - keep the current generic empty state
- `favorites=1` but no favorite station:
  - show a specific message such as `No favorite station matches this view yet.`
  - include clear next actions:
    - return to all stations
    - add receipt / open imports if useful

## Analytics impact
Keep analytics intentionally light in this ticket.

Allowed change:
- preserve the existing favorite badge/context already added in `SP41-001`
- optionally sort the displayed visited-station fallback list with favorites first only if it is a pure presentation change and low-risk

Not allowed in this ticket:
- changing chart data
- changing KPI aggregation scope
- adding new analytics filters

Decision:
- do not add a new analytics filter now
- keep the main implementation centered on `/ui/stations`

## Technical design
### Controller
`/Users/lorenzomarozzo/PhpstormProjects/fuelapp/src/Station/UI/Web/Controller/ListStationsController.php`
- read query param `favorites`
- reuse the existing batch lookup of favorite station IDs
- annotate each row with `isFavorite`
- if `favorites=1`, filter rows down to favorites only
- apply the new sort order
- pass an explicit view flag to Twig, for example `favoritesOnly`

### Template
`/Users/lorenzomarozzo/PhpstormProjects/fuelapp/templates/station/index.html.twig`
- add the filter control
- preserve favorite toggles and existing actions
- render the dedicated empty state for `favoritesOnly` when no rows remain
- keep spacing/button sizes aligned with the current station index UI

### Analytics
No controller contract change is required unless we decide to re-order the visible fallback list. If we do, keep it local to presentation and do not change analytics query semantics.

## Security
No new access model is introduced.

Rules stay the same:
- station visibility still comes from existing receipt/station access rules
- favorites only filters within the already visible station set
- no user can infer another user’s favorites through ordering/filtering

## Admin coverage
Deferred.

Why it could be useful later:
- support requests like “my favorites disappeared”
- auditing preference-related incidents

Why it is not needed now:
- this ticket only affects personal UI ordering/filtering
- no operational workflow depends on favorite ranking

Impact of deferring:
- no product blocker
- only reduced support visibility if a user reports a preference issue

## Tests
### Functional
`/Users/lorenzomarozzo/PhpstormProjects/fuelapp/tests/Functional/Station/StationWebUiTest.php`
- favorites appear before non-favorites in the station list
- `favorites=1` shows only favorites
- `favorites=1` with no favorites shows the dedicated empty state
- toggling a favorite still keeps the redirect/filter context stable where relevant

### Unit / integration
Only extend these if the sorting/filtering logic is extracted into a dedicated helper. Otherwise the existing repository/command tests from `SP41-001` are enough.

## Follow-up tickets
Natural next steps after this ticket:
1. `favorites only` or favorites-first behavior in analytics if real usage asks for it
2. favorite-aware station picker hints
3. public-source enrichment on favorite station detail pages
