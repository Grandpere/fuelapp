# Favorite Stations Ranking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make favorite stations rise to the top of the station index and add a compact `favorites only` view without expanding analytics semantics.

**Architecture:** Keep the change focused on the station list controller and template. Reuse the existing per-row `isFavorite` annotation from `SP41-001`, then apply presentation-level filtering and ordering in the web layer. Analytics stays mostly unchanged in this ticket.

**Tech Stack:** Symfony 8, Twig, Doctrine-backed repositories, PHPUnit functional tests, PHPStan, PHP-CS-Fixer.

---

## File map

### Existing files to modify
- `tasks/todo.md`
  - track `SP41-002` progress
- `docs/ai/memory.md`
  - only update if a real bug/root-cause is discovered during implementation
- `src/Station/UI/Web/Controller/ListStationsController.php`
  - read the new filter flag, keep batch favorite lookup, filter/sort rows, expose view state to Twig
- `templates/station/index.html.twig`
  - add `Favorites only` control, active-state rendering, and dedicated filtered empty state
- `tests/Functional/Station/StationWebUiTest.php`
  - cover favorites-first ordering and filter behavior

### Existing files to verify but likely leave unchanged
- `src/Analytics/UI/Web/Controller/AnalyticsDashboardController.php`
- `templates/analytics/index.html.twig`
  - only touch these if a very small presentation-only ordering tweak becomes clearly worthwhile

### No new persistence files
- no migration
- no new repository contract
- no new command/handler

---

### Task 1: Add station-index filter and favorites-first ordering

**Files:**
- Modify: `tasks/todo.md`
- Modify: `src/Station/UI/Web/Controller/ListStationsController.php`
- Test later: `tests/Functional/Station/StationWebUiTest.php`

- [ ] **Step 1: Mark the ticket in `tasks/todo.md`**

Add a new block for `SP41-002` with this checklist:

```md
# TODO - SP41-002 Favorite stations ranking and filtering

## Plan
- [pending] Make favorites rise to the top of the station index while preserving recent-visit ordering inside each group.
- [pending] Add a lightweight `favorites only` filter on the station index with a dedicated empty state.
- [pending] Add or update the relevant functional coverage and run quality gates.
```

- [ ] **Step 2: Write the failing functional assertions first**

In `tests/Functional/Station/StationWebUiTest.php`, add a new test skeleton for ordering/filtering before touching the controller:

```php
public function testStationListCanPrioritizeAndFilterFavoriteStations(): void
{
    $this->markTestIncomplete('Implemented in the next steps of SP41-002.');
}
```

Expected first run: the test suite should report one incomplete test, confirming the new scenario is wired in.

- [ ] **Step 3: Replace the placeholder with a real failing scenario**

Create three visible stations for the same user:
- station A: favorite, older visit
- station B: non-favorite, newer visit
- station C: favorite, no visit or older visit depending on the simplest fixture shape

Assert both:
- default `/ui/stations` shows favorite rows before non-favorites
- `/ui/stations?favorites=1` hides non-favorites

Use direct string-position assertions instead of fragile HTML parsing if that stays reliable:

```php
$content = (string) $response->getContent();
self::assertLessThan(
    strpos($content, 'Newest Non Favorite Station'),
    strpos($content, 'Older Favorite Station'),
);
self::assertStringNotContainsString('Newest Non Favorite Station', $favoritesOnlyContent);
```

Run:
```bash
make phpunit-functional
```
Expected: the new test fails because the controller still uses the old ordering and has no filter support.

- [ ] **Step 4: Implement the filter flag in `ListStationsController`**

Add a request-aware action signature and a small parser:

```php
#[Route('/ui/stations', name: 'ui_station_list', methods: ['GET'])]
public function __invoke(Request $request): Response
{
    $favoritesOnly = '1' === (string) $request->query->get('favorites');
    // existing row building
}
```

Also add the import:

```php
use Symfony\Component\HttpFoundation\Request;
```

- [ ] **Step 5: Filter rows after favorite annotation and before sorting**

After `isFavorite` is added to each row:

```php
if ($favoritesOnly) {
    $stationRows = array_values(array_filter(
        $stationRows,
        static fn (array $stationRow): bool => true === $stationRow['isFavorite'],
    ));
}
```

- [ ] **Step 6: Apply the new sort order**

Replace the current comparator with one that sorts by:
1. `isFavorite DESC`
2. `latestIssuedAt DESC` with `null` last
3. `name`, `city`

Use a comparator shaped like this:

```php
usort(
    $stationRows,
    static function (array $left, array $right): int {
        if ($left['isFavorite'] !== $right['isFavorite']) {
            return $left['isFavorite'] ? -1 : 1;
        }

        $leftDate = $left['latestIssuedAt'];
        $rightDate = $right['latestIssuedAt'];

        if ($leftDate === $rightDate) {
            return [$left['name'], $left['city']] <=> [$right['name'], $right['city']];
        }

        if (null === $leftDate) {
            return 1;
        }

        if (null === $rightDate) {
            return -1;
        }

        return $rightDate <=> $leftDate;
    },
);
```

- [ ] **Step 7: Expose the filter state to Twig**

Pass a boolean flag in the render payload:

```php
return $this->render('station/index.html.twig', [
    'stationRows' => $stationRows,
    'favoritesOnly' => $favoritesOnly,
]);
```

- [ ] **Step 8: Run only the targeted station functional test**

Run:
```bash
make phpunit-functional
```
Expected: the new station test should move closer to passing, with remaining failures now pointing to missing Twig/UI states rather than ordering logic.

- [ ] **Step 9: Commit the controller-side progress**

```bash
git add tasks/todo.md src/Station/UI/Web/Controller/ListStationsController.php tests/Functional/Station/StationWebUiTest.php
git commit -m "Add favorite station ordering"
```

### Task 2: Add the station-index filter UI and dedicated empty state

**Files:**
- Modify: `templates/station/index.html.twig`
- Modify: `tests/Functional/Station/StationWebUiTest.php`

- [ ] **Step 1: Extend the failing functional test with explicit UI assertions**

Add assertions for:
- `Favorites only` label
- active filter URL `?favorites=1`
- reset/all-stations path
- dedicated empty-state copy when the filtered view is empty

Example assertions:

```php
self::assertStringContainsString('Favorites only', $content);
self::assertStringContainsString('/ui/stations?favorites=1', $content);
self::assertStringContainsString('No favorite station matches this view yet.', $filteredEmptyContent);
```

Run:
```bash
make phpunit-functional
```
Expected: fail on missing filter UI and empty-state copy.

- [ ] **Step 2: Add compact filter controls near the page actions**

In `templates/station/index.html.twig`, introduce a small filter row or action group above the table, for example:

```twig
<div class="stations-filters">
    <a class="btn {{ favoritesOnly ? 'btn-primary' : 'btn-secondary' }} btn-compact" href="{{ path('ui_station_list', {favorites: 1}) }}">Favorites only</a>
    {% if favoritesOnly %}
        <a class="btn btn-secondary btn-compact" href="{{ path('ui_station_list') }}">Show all stations</a>
    {% endif %}
</div>
```

Add minimal CSS so the control sits naturally with the existing page language.

- [ ] **Step 3: Keep favorite-toggle redirects compatible with the filtered view**

Update the favorite toggle hidden redirect in each row to preserve current filter context:

```twig
<input type="hidden" name="_redirect" value="{{ favoritesOnly ? path('ui_station_list', {favorites: 1}) : path('ui_station_list') }}">
```

This prevents users from getting bounced out of the filtered view after toggling.

- [ ] **Step 4: Add the dedicated filtered empty state**

Split the existing empty-state branch:

```twig
{% if stationRows is empty %}
    {% if favoritesOnly %}
        <div class="empty-state">
            <p>No favorite station matches this view yet.</p>
            <div class="actions-wrap">
                <a class="btn btn-secondary" href="{{ path('ui_station_list') }}">Show all stations</a>
            </div>
        </div>
    {% else %}
        {# existing generic empty state #}
    {% endif %}
{% else %}
```

- [ ] **Step 5: Run the targeted functional suite again**

Run:
```bash
make phpunit-functional
```
Expected: the station-list ranking/filter test should now pass, or any remaining failure should point to exact copy/URL mismatches that are straightforward to align.

- [ ] **Step 6: Commit the UI polish**

```bash
git add templates/station/index.html.twig tests/Functional/Station/StationWebUiTest.php
git commit -m "Add favorites-only station filter"
```

### Task 3: Verify analytics stays stable and finish quality gates

**Files:**
- Verify: `src/Analytics/UI/Web/Controller/AnalyticsDashboardController.php`
- Verify: `templates/analytics/index.html.twig`
- Modify only if needed: `docs/ai/memory.md`

- [ ] **Step 1: Decide whether analytics needs any code change at all**

Read the current analytics favorite behavior and confirm it already satisfies the spec:
- favorite badge on visited station fallback rows
- selected-station favorite hint

If both are already present and working, do not change analytics code in this ticket.

- [ ] **Step 2: Only if a bug appears, fix it minimally and document the root cause**

If implementation reveals a regression or confusing edge case, update:
- the smallest relevant analytics file
- `/Users/lorenzomarozzo/PhpstormProjects/fuelapp/docs/ai/memory.md`

Use the existing incident format only when there is a genuine bug/root cause, not for routine feature work.

- [ ] **Step 3: Run quality gates**

Run:
```bash
make phpstan
make phpunit-unit
make phpunit-integration
make php-cs-fixer-check
```

Expected:
- all green locally
- no new static-analysis or formatting regressions

- [ ] **Step 4: Scan for duplicate-suffix files before commit handoff**

Run:
```bash
find . -path './vendor' -prune -o -path './var' -prune -o \( -name '* 2*' -o -path '* 2*' \) -print
```

Expected:
- no output

- [ ] **Step 5: Prepare user-side validation handoff**

Ask the user to run, in order:
```bash
make restart-app
make phpunit-functional
```

Manual front checks to list explicitly:
- default `/ui/stations` shows favorites first
- `/ui/stations?favorites=1` shows only favorites
- the filtered empty state appears when no station is favorited
- toggling a favorite from the filtered view keeps the user in the same view when appropriate

- [ ] **Step 6: Commit the finished ticket**

```bash
git add tasks/todo.md src/Station/UI/Web/Controller/ListStationsController.php templates/station/index.html.twig tests/Functional/Station/StationWebUiTest.php docs/ai/memory.md
git commit -m "Improve favorite station ranking"
```
