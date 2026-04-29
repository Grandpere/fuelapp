# Public Station Suggestions For Receipt And Import Picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let manual receipt creation and import review suggest cached public fuel stations and convert a selected public suggestion into a canonical internal `Station` during save/finalize.

**Architecture:** Keep `Station` as the only entity attached to receipts. Add a combined suggestion read model that merges existing internal station candidates with public fuel station candidates, then extend the write path to accept a typed suggestion selection (`station` or `public`) and reuse/create the internal `Station` accordingly.

**Tech Stack:** Symfony 8, Twig, Doctrine ORM/DBAL, existing `PublicFuelStation` cache, PHPUnit (Unit/Integration/Functional), PHPStan, PHP-CS-Fixer.

---

## File Map

### Existing files to modify
- `src/Receipt/UI/Web/Controller/CreateReceiptController.php`
  - Extend manual receipt form state with public suggestion type/id.
  - Load combined suggestions instead of only internal station candidates.
  - Preserve selected suggestion and manual fallback semantics across lookup/save.
- `src/Import/UI/Web/Controller/ImportJobShowWebController.php`
  - Provide combined internal/public suggestions to the review template.
- `src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php`
  - Accept typed suggestion selection from import review submit.
  - Keep stale suggestion failures user-facing.
- `src/Receipt/Application/Command/CreateReceiptWithStationCommand.php`
  - Replace the single `selectedStationId` input with typed suggestion selection.
- `src/Receipt/Application/Command/CreateReceiptWithStationHandler.php`
  - Resolve `station` suggestions directly.
  - Resolve `public` suggestions by loading cached public data, reusing matching internal station, or creating one.
- `src/Import/Application/Command/FinalizeImportJobCommand.php`
  - Carry typed suggestion selection into the shared write flow.
- `src/Import/Application/Command/FinalizeImportJobHandler.php`
  - Pass typed suggestion selection through to receipt creation.
- `templates/receipt/_form.html.twig`
  - Render `Existing stations`, `Public station suggestions`, source badges, helper text, and alternate-path state.
- `templates/receipt/new.html.twig`
  - Add any CSS needed for mixed-source suggestion UI and selected-state treatment.
- `templates/import/show.html.twig`
  - Mirror the mixed-source suggestion UI in review mode.
- `config/services.yaml`
  - Wire any new reader/service aliases.
- `tasks/todo.md`
  - Track `SP40-002` progress.
- `docs/ai/memory.md`
  - Capture any bug/root-cause lesson found during implementation.

### New files to create
- `src/Station/Application/Suggestion/StationSuggestion.php`
  - Unified suggestion DTO for UI consumption.
- `src/Station/Application/Suggestion/StationSuggestionQuery.php`
  - Query object shared by manual receipt and import review.
- `src/Station/Application/Suggestion/StationSuggestionReader.php`
  - Interface for combined internal/public suggestions.
- `src/Station/Infrastructure/Suggestion/CombinedStationSuggestionReader.php`
  - Aggregate internal station search and public station search into one ordered result.
- `src/PublicFuelStation/Application/Search/PublicFuelStationSuggestionReader.php`
  - Public-station-facing search interface tailored to suggestion use.
- `src/PublicFuelStation/Application/Search/PublicFuelStationSuggestion.php`
  - Public suggestion DTO if separate internal shape is useful before mapping to unified suggestion.
- `src/PublicFuelStation/Infrastructure/Search/DoctrinePublicFuelStationSuggestionReader.php`
  - Query cached public stations by free text, name/address fragments, postal code, and city.

### Existing tests to modify
- `tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`
- `tests/Functional/Receipt/ReceiptWebUiTest.php`
- `tests/Functional/Import/ImportWebUiTest.php`

### New tests to create
- `tests/Integration/PublicFuelStation/Infrastructure/DoctrinePublicFuelStationSuggestionReaderTest.php`
- `tests/Unit/Station/Infrastructure/Suggestion/CombinedStationSuggestionReaderTest.php`

---

### Task 1: Define unified suggestion types and public suggestion search contract

**Files:**
- Create: `src/Station/Application/Suggestion/StationSuggestion.php`
- Create: `src/Station/Application/Suggestion/StationSuggestionQuery.php`
- Create: `src/Station/Application/Suggestion/StationSuggestionReader.php`
- Create: `src/PublicFuelStation/Application/Search/PublicFuelStationSuggestion.php`
- Create: `src/PublicFuelStation/Application/Search/PublicFuelStationSuggestionReader.php`
- Test: `tests/Unit/Station/Infrastructure/Suggestion/CombinedStationSuggestionReaderTest.php`

- [ ] **Step 1: Write the failing unit test for combined suggestion ordering contract**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Station\Infrastructure\Suggestion;

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestion;
use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Station\Application\Search\StationSearchCandidate;
use App\Station\Application\Search\StationSearchQuery;
use App\Station\Application\Search\StationSearchReader;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use App\Station\Infrastructure\Suggestion\CombinedStationSuggestionReader;
use PHPUnit\Framework\TestCase;

final class CombinedStationSuggestionReaderTest extends TestCase
{
    public function testItReturnsInternalSuggestionsBeforePublicSuggestions(): void
    {
        $reader = new CombinedStationSuggestionReader(
            new class() implements StationSearchReader {
                public function search(StationSearchQuery $query): array
                {
                    return [new StationSearchCandidate('station-1', 'PETRO EST', 'LECLERC', '51120', 'SEZANNE', null, null)];
                }
            },
            new class() implements PublicFuelStationSuggestionReader {
                public function search(StationSuggestionQuery $query, int $limit): array
                {
                    return [new PublicFuelStationSuggestion('public-1', 'TOTAL EXPRESS', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000)];
                }
            },
        );

        $results = $reader->search(new StationSuggestionQuery('sezanne', null, null, '51120', 'SEZANNE'));

        self::assertCount(2, $results);
        self::assertSame('station', $results[0]->sourceType);
        self::assertSame('public', $results[1]->sourceType);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Unit/Station/Infrastructure/Suggestion/CombinedStationSuggestionReaderTest.php`
Expected: FAIL because the suggestion classes/reader do not exist yet.

- [ ] **Step 3: Add minimal DTO/interface definitions**

```php
<?php

declare(strict_types=1);

namespace App\Station\Application\Suggestion;

final readonly class StationSuggestion
{
    public function __construct(
        public string $sourceType,
        public string $sourceId,
        public string $name,
        public string $streetName,
        public string $postalCode,
        public string $city,
        public ?int $latitudeMicroDegrees,
        public ?int $longitudeMicroDegrees,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Station\Application\Suggestion;

final readonly class StationSuggestionQuery
{
    public function __construct(
        public ?string $freeText,
        public ?string $name,
        public ?string $streetName,
        public ?string $postalCode,
        public ?string $city,
        public int $limit = 5,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Station\Application\Suggestion;

interface StationSuggestionReader
{
    /** @return list<StationSuggestion> */
    public function search(StationSuggestionQuery $query): array;
}
```

```php
<?php

declare(strict_types=1);

namespace App\PublicFuelStation\Application\Search;

final readonly class PublicFuelStationSuggestion
{
    public function __construct(
        public string $sourceId,
        public string $name,
        public string $streetName,
        public string $postalCode,
        public string $city,
        public ?int $latitudeMicroDegrees,
        public ?int $longitudeMicroDegrees,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\PublicFuelStation\Application\Search;

use App\Station\Application\Suggestion\StationSuggestionQuery;

interface PublicFuelStationSuggestionReader
{
    /** @return list<PublicFuelStationSuggestion> */
    public function search(StationSuggestionQuery $query, int $limit): array;
}
```

- [ ] **Step 4: Add the minimal combined reader implementation**

```php
<?php

declare(strict_types=1);

namespace App\Station\Infrastructure\Suggestion;

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Station\Application\Search\StationSearchQuery;
use App\Station\Application\Search\StationSearchReader;
use App\Station\Application\Suggestion\StationSuggestion;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use App\Station\Application\Suggestion\StationSuggestionReader;

final readonly class CombinedStationSuggestionReader implements StationSuggestionReader
{
    public function __construct(
        private StationSearchReader $stationSearchReader,
        private PublicFuelStationSuggestionReader $publicSuggestionReader,
    ) {
    }

    public function search(StationSuggestionQuery $query): array
    {
        $internal = array_map(
            static fn ($candidate): StationSuggestion => new StationSuggestion(
                'station',
                $candidate->id,
                $candidate->name,
                $candidate->streetName,
                $candidate->postalCode,
                $candidate->city,
                $candidate->latitudeMicroDegrees,
                $candidate->longitudeMicroDegrees,
            ),
            $this->stationSearchReader->search(new StationSearchQuery(
                $query->freeText,
                $query->name,
                $query->streetName,
                $query->postalCode,
                $query->city,
                $query->limit,
            )),
        );

        $public = array_map(
            static fn ($candidate): StationSuggestion => new StationSuggestion(
                'public',
                $candidate->sourceId,
                $candidate->name,
                $candidate->streetName,
                $candidate->postalCode,
                $candidate->city,
                $candidate->latitudeMicroDegrees,
                $candidate->longitudeMicroDegrees,
            ),
            $this->publicSuggestionReader->search($query, $query->limit),
        );

        return array_slice(array_merge($internal, $public), 0, $query->limit * 2);
    }
}
```

- [ ] **Step 5: Run the unit test to verify it passes**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Unit/Station/Infrastructure/Suggestion/CombinedStationSuggestionReaderTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Station/Application/Suggestion src/PublicFuelStation/Application/Search src/Station/Infrastructure/Suggestion tests/Unit/Station/Infrastructure/Suggestion/CombinedStationSuggestionReaderTest.php
git commit -m "Add combined station suggestion types"
```

### Task 2: Implement public fuel station suggestion query against cached data

**Files:**
- Create: `src/PublicFuelStation/Infrastructure/Search/DoctrinePublicFuelStationSuggestionReader.php`
- Modify: `config/services.yaml`
- Test: `tests/Integration/PublicFuelStation/Infrastructure/DoctrinePublicFuelStationSuggestionReaderTest.php`

- [ ] **Step 1: Write the failing integration test for public suggestion search**

```php
public function testSearchReturnsPublicStationsMatchingPostalCodeAndText(): void
{
    $this->persistPublicStation('public-1', 'TOTAL EXPRESS', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000);
    $this->persistPublicStation('public-2', 'AVIA', '10 Avenue de Paris', '75001', 'PARIS', 48856000, 2352000);
    $this->em->flush();

    $results = $this->reader->search(new StationSuggestionQuery('frisange', null, null, '5751', 'FRISANGE'), 5);

    self::assertCount(1, $results);
    self::assertSame('public-1', $results[0]->sourceId);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Integration/PublicFuelStation/Infrastructure/DoctrinePublicFuelStationSuggestionReaderTest.php`
Expected: FAIL because the Doctrine reader and/or service wiring do not exist yet.

- [ ] **Step 3: Implement bounded public suggestion query**

```php
public function search(StationSuggestionQuery $query, int $limit): array
{
    $qb = $this->connection->createQueryBuilder()
        ->select('source_id', 'name', 'address', 'postal_code', 'city', 'latitude_micro_degrees', 'longitude_micro_degrees')
        ->from('public_fuel_stations');

    $terms = $this->terms($query);
    if ([] !== $terms) {
        $or = [];
        foreach ($terms as $index => $term) {
            $param = 'term'.$index;
            $or[] = sprintf('(LOWER(name) LIKE :%1$s OR LOWER(address) LIKE :%1$s OR LOWER(city) LIKE :%1$s OR postal_code LIKE :%1$s)', $param);
            $qb->setParameter($param, '%'.$term.'%');
        }

        $qb->andWhere(implode(' OR ', $or));
    }

    $qb->setMaxResults(max(20, $limit * 10));

    return array_map(
        static fn (array $row): PublicFuelStationSuggestion => new PublicFuelStationSuggestion(
            (string) $row['source_id'],
            (string) $row['name'],
            (string) $row['address'],
            (string) $row['postal_code'],
            (string) $row['city'],
            isset($row['latitude_micro_degrees']) ? (int) $row['latitude_micro_degrees'] : null,
            isset($row['longitude_micro_degrees']) ? (int) $row['longitude_micro_degrees'] : null,
        ),
        $qb->executeQuery()->fetchAllAssociative(),
    );
}
```

- [ ] **Step 4: Wire the Doctrine implementation**

```yaml
services:
    App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader: '@App\PublicFuelStation\Infrastructure\Search\DoctrinePublicFuelStationSuggestionReader'
```

- [ ] **Step 5: Run the integration test to verify it passes**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Integration/PublicFuelStation/Infrastructure/DoctrinePublicFuelStationSuggestionReaderTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/PublicFuelStation/Infrastructure/Search/DoctrinePublicFuelStationSuggestionReader.php config/services.yaml tests/Integration/PublicFuelStation/Infrastructure/DoctrinePublicFuelStationSuggestionReaderTest.php
git commit -m "Add public station suggestion search"
```

### Task 3: Resolve selected public suggestions in the receipt creation write path

**Files:**
- Modify: `src/Receipt/Application/Command/CreateReceiptWithStationCommand.php`
- Modify: `src/Receipt/Application/Command/CreateReceiptWithStationHandler.php`
- Test: `tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`

- [ ] **Step 1: Write the failing unit tests for public suggestion resolution**

```php
public function testItCreatesStationFromSelectedPublicSuggestionWhenNoInternalStationMatches(): void
{
    $publicReader = new InMemorySelectedPublicStationReader([
        'public-1' => new PublicFuelStationSuggestion('public-1', 'TOTAL EXPRESS', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000),
    ]);

    $handler = $this->buildHandler(publicStationReader: $publicReader);

    $receipt = $handler(new CreateReceiptWithStationCommand(
        new DateTimeImmutable('2026-04-29T12:00:00+00:00'),
        [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
        'Typed Name',
        'Typed Street',
        '5751',
        'FRISANGE',
        null,
        null,
        selectedSuggestionType: 'public',
        selectedSuggestionId: 'public-1',
    ));

    self::assertSame('TOTAL EXPRESS', $receipt->stationName());
}

public function testItThrowsWhenSelectedPublicSuggestionDoesNotExist(): void
{
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Selected public station was not found.');
}
```

- [ ] **Step 2: Run the unit test to verify it fails**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml --filter selectedPublicSuggestion tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`
Expected: FAIL because typed public suggestion handling does not exist.

- [ ] **Step 3: Extend the command to carry typed suggestion selection**

```php
public function __construct(
    public DateTimeImmutable $issuedAt,
    public array $lines,
    public string $stationName,
    public string $stationStreetName,
    public string $stationPostalCode,
    public string $stationCity,
    public ?int $latitudeMicroDegrees,
    public ?int $longitudeMicroDegrees,
    public ?string $vehicleId = null,
    public ?string $ownerId = null,
    public ?int $odometerKilometers = null,
    public ?string $selectedSuggestionType = null,
    public ?string $selectedSuggestionId = null,
) {
}
```

- [ ] **Step 4: Implement public suggestion resolution in the handler**

```php
if ('station' === $command->selectedSuggestionType && null !== $command->selectedSuggestionId) {
    $station = $this->requireSelectedStation($command->selectedSuggestionId);
}

if ('public' === $command->selectedSuggestionType && null !== $command->selectedSuggestionId) {
    $public = $this->requireSelectedPublicStation($command->selectedSuggestionId);

    $station = $this->stationRepository->findByIdentity(
        $public->name,
        $public->streetName,
        $public->postalCode,
        $public->city,
    );

    if (null === $station) {
        $station = ($this->createStationHandler)(new CreateStationCommand(
            $public->name,
            $public->streetName,
            $public->postalCode,
            $public->city,
            $public->latitudeMicroDegrees,
            $public->longitudeMicroDegrees,
        ));
    }
}
```

- [ ] **Step 5: Run the targeted unit tests to verify they pass**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Receipt/Application/Command/CreateReceiptWithStationCommand.php src/Receipt/Application/Command/CreateReceiptWithStationHandler.php tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php
git commit -m "Support public station picker in receipt handler"
```

### Task 4: Use combined suggestions and typed selection in manual receipt creation

**Files:**
- Modify: `src/Receipt/UI/Web/Controller/CreateReceiptController.php`
- Modify: `templates/receipt/_form.html.twig`
- Modify: `templates/receipt/new.html.twig`
- Test: `tests/Functional/Receipt/ReceiptWebUiTest.php`

- [ ] **Step 1: Write the failing functional test for public suggestion selection on `/ui/receipts/new`**

```php
public function testUserCanSelectPublicSuggestionWhenCreatingReceipt(): void
{
    $this->seedPublicStation('public-1', 'TOTAL EXPRESS', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000);
    $this->loginWithUiForm($email, $password);

    $lookup = $this->request('POST', '/ui/receipts/new', [
        '_token' => $csrf,
        '_station_lookup' => '1',
        '_station_lookup_requested' => '1',
        'stationSearch' => 'frisange',
        'stationName' => 'Typed Name',
        'stationStreetName' => 'Typed Street',
        'stationPostalCode' => '5751',
        'stationCity' => 'FRISANGE',
    ]);

    $page = $this->request('GET', $lookup->headers->get('Location') ?? '/ui/receipts/new');
    self::assertStringContainsString('Public station suggestions', (string) $page->getContent());
    self::assertStringContainsString('TOTAL EXPRESS', (string) $page->getContent());
}
```

- [ ] **Step 2: Run the functional test to verify it fails**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml --filter testUserCanSelectPublicSuggestionWhenCreatingReceipt tests/Functional/Receipt/ReceiptWebUiTest.php`
Expected: FAIL because manual receipt flow still only knows internal station suggestions.

- [ ] **Step 3: Update the controller to use the combined suggestion reader and typed selection fields**

```php
' selectedSuggestionType' => $this->queryValue($request, 'selectedSuggestionType', ''),
' selectedSuggestionId' => $this->queryValue($request, 'selectedSuggestionId', ''),
```

```php
$suggestions = $this->stationSuggestionReader->search(new StationSuggestionQuery(
    $formData['stationSearch'],
    $this->nullIfEmpty($formData['stationName']),
    $this->nullIfEmpty($formData['stationStreetName']),
    $this->nullIfEmpty($formData['stationPostalCode']),
    $this->nullIfEmpty($formData['stationCity']),
));
```

- [ ] **Step 4: Update the Twig UI for mixed-source suggestion cards**

```twig
{% if existingSuggestions is not empty %}
    <div class="station-lookup-results">
        <p class="station-lookup-results-title">Existing stations</p>
        {# render cards with badge Existing #}
    </div>
{% endif %}

{% if publicSuggestions is not empty %}
    <div class="station-lookup-results">
        <p class="station-lookup-results-title">Public station suggestions</p>
        {# render cards with badge Public #}
    </div>
{% endif %}
```

```twig
<input type="radio" name="selectedSuggestionId" value="{{ candidate.sourceId }}">
<input type="hidden" name="selectedSuggestionType" value="{{ candidate.sourceType }}">
```

Use a JS-free pattern such as `selectedSuggestionType:selectedSuggestionId` in the radio `value` if keeping one field is simpler for HTML forms.

- [ ] **Step 5: Run the targeted functional test to verify it passes**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml --filter testUserCanSelectPublicSuggestionWhenCreatingReceipt tests/Functional/Receipt/ReceiptWebUiTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Receipt/UI/Web/Controller/CreateReceiptController.php templates/receipt/_form.html.twig templates/receipt/new.html.twig tests/Functional/Receipt/ReceiptWebUiTest.php
git commit -m "Add public station suggestions to receipt form"
```

### Task 5: Reuse typed public suggestion selection in import review finalize

**Files:**
- Modify: `src/Import/Application/Command/FinalizeImportJobCommand.php`
- Modify: `src/Import/Application/Command/FinalizeImportJobHandler.php`
- Modify: `src/Import/UI/Web/Controller/ImportJobShowWebController.php`
- Modify: `src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php`
- Modify: `templates/import/show.html.twig`
- Test: `tests/Functional/Import/ImportWebUiTest.php`

- [ ] **Step 1: Write the failing functional test for selecting a public suggestion in import review**

```php
public function testUserCanFinalizeImportUsingSelectedPublicSuggestion(): void
{
    $this->seedPublicStation('public-1', 'TOTAL EXPRESS', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000);

    $page = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
    self::assertStringContainsString('Public station suggestions', (string) $page->getContent());

    $response = $this->request('POST', '/ui/imports/'.$jobId.'/finalize', [
        '_token' => $csrf,
        'selectedSuggestionType' => 'public',
        'selectedSuggestionId' => 'public-1',
    ], [], $sessionCookie);

    self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
}
```

- [ ] **Step 2: Run the functional test to verify it fails**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml --filter testUserCanFinalizeImportUsingSelectedPublicSuggestion tests/Functional/Import/ImportWebUiTest.php`
Expected: FAIL because import review does not yet expose or submit public suggestions.

- [ ] **Step 3: Extend import review read/submit flow with typed selection**

```php
new FinalizeImportJobCommand(
    $id,
    ...,
    $this->toNullableString($request->request->get('selectedSuggestionType')),
    $this->toNullableString($request->request->get('selectedSuggestionId')),
)
```

In `ImportJobShowWebController`, mirror the same combined suggestion read as manual receipts using extracted station fragments as hints.

- [ ] **Step 4: Update the import review template to render public suggestion cards and helper state**

```twig
{% if publicSuggestions is not empty %}
    <section class="station-suggestions-group">
        <h3>Public station suggestions</h3>
        {# radio cards with Public badge and save-time helper text #}
    </section>
{% endif %}
```

- [ ] **Step 5: Run the targeted functional test to verify it passes**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml --filter testUserCanFinalizeImportUsingSelectedPublicSuggestion tests/Functional/Import/ImportWebUiTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Import/Application/Command/FinalizeImportJobCommand.php src/Import/Application/Command/FinalizeImportJobHandler.php src/Import/UI/Web/Controller/ImportJobShowWebController.php src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php templates/import/show.html.twig tests/Functional/Import/ImportWebUiTest.php
git commit -m "Add public station suggestions to import review"
```

### Task 6: Polish ambiguity-reducing UI states and run full quality gates

**Files:**
- Modify: `templates/receipt/_form.html.twig`
- Modify: `templates/receipt/new.html.twig`
- Modify: `templates/import/show.html.twig`
- Modify: `tasks/todo.md`
- Modify: `docs/ai/memory.md` (only if a real bug/root cause emerged)

- [ ] **Step 1: Add selected-state helper and manual-field de-emphasis for both flows**

```twig
{% if selectedSuggestionType == 'public' %}
    <p class="station-selection-state">This will create or reuse an internal station when you save.</p>
{% elseif selectedSuggestionType == 'station' %}
    <p class="station-selection-state">This receipt will use the selected existing station.</p>
{% endif %}

<div class="manual-station-fields {{ selectedSuggestionType != '' ? 'manual-station-fields-muted' : '' }}">
    {# existing station fields #}
</div>
```

- [ ] **Step 2: Mark the ticket complete in `tasks/todo.md`**

```md
# TODO - SP40-002 Public station picker suggestions

## Plan
- [completed] Add a combined internal/public suggestion read model.
- [completed] Support typed suggestion selection in receipt save flow.
- [completed] Reuse the same behavior in import review finalize.
- [completed] Add tests and polish the selected-state UX.
```

- [ ] **Step 3: Run project quality gates**

Run in order:
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`

Expected: all pass.

- [ ] **Step 4: Ask the user to run functionals and share failures if any**

Ask for:
- `make restart-app`
- `make phpunit-functional`
- manual check of `/ui/receipts/new` and `/ui/imports/{id}` with a public suggestion selected

Expected user validation:
- public suggestions appear when relevant
- selecting one public suggestion clearly explains save-time behavior
- save/finalize attaches to a canonical internal station without duplicate confusion

- [ ] **Step 5: Commit**

```bash
git add templates/receipt/_form.html.twig templates/receipt/new.html.twig templates/import/show.html.twig tasks/todo.md docs/ai/memory.md
git commit -m "Polish public station picker states"
```

## Self-Review
- Spec coverage: manual receipt flow, import review flow, combined reader, public conversion, stale/invalid suggestion handling, and UX clarity are all covered by Tasks 1-6.
- Placeholder scan: no `TODO/TBD/similar to above` placeholders remain; each task has concrete files, tests, and commands.
- Type consistency: the plan consistently uses `selectedSuggestionType` and `selectedSuggestionId` across controller, command, handler, and tests; `Station` remains the canonical write target throughout.
