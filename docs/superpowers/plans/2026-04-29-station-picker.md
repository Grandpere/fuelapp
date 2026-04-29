# Station Picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users explicitly attach receipts to an existing station during manual receipt creation and import review, while preserving the current free-form fallback that creates or reuses a station by exact identity.

**Architecture:** Add a shared station-candidate read model for UI suggestions, extend the existing receipt creation command path with an optional selected `stationId`, then reuse that exact write path from both the manual receipt form and import finalization. Keep the UI progressive: candidate picker first, editable station fields still visible, and no silent auto-selection.

**Tech Stack:** Symfony 8, Doctrine ORM/DBAL, Twig, PHP unit/integration/functional tests, existing Make quality gates.

---

## File Structure

### Create
- `src/Station/Application/Search/StationSearchCandidate.php` — small immutable UI candidate DTO.
- `src/Station/Application/Search/StationSearchQuery.php` — input query object for station candidate lookup.
- `src/Station/Application/Search/StationSearchReader.php` — application read interface for candidate lookup.
- `src/Station/Infrastructure/Search/DoctrineStationSearchReader.php` — Doctrine implementation scoped to current user-readable stations.
- `tests/Integration/Station/Infrastructure/DoctrineStationSearchReaderTest.php` — integration coverage for candidate ranking/scope.
- `docs/superpowers/plans/2026-04-29-station-picker.md` — this implementation plan.

### Modify
- `src/Receipt/Application/Command/CreateReceiptWithStationCommand.php` — add optional selected `stationId`.
- `src/Receipt/Application/Command/CreateReceiptWithStationHandler.php` — prefer selected station before identity lookup/create.
- `src/Receipt/UI/Web/Controller/CreateReceiptController.php` — read picker fields, load candidates, validate chosen station, render selection state.
- `src/Import/Application/Command/FinalizeImportJobCommand.php` — add optional selected `stationId`.
- `src/Import/Application/Command/FinalizeImportJobHandler.php` — pass selected station to receipt creation.
- `src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php` — read selected station id from form submit.
- `src/Import/UI/Web/Controller/ImportJobShowWebController.php` — compute station candidate suggestions from parsed station fragments and expose them to Twig.
- `templates/receipt/_form.html.twig` — add station picker block, hidden selected id, clear action, candidate list.
- `templates/import/show.html.twig` — add the same picker block to review/finalize UI.
- `tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php` — selected-station-first behavior.
- `tests/Functional/Receipt/ReceiptWebUiTest.php` — selecting an existing station from manual creation.
- `tests/Functional/Import/ImportWebUiTest.php` — selecting an existing station during import review.
- `config/services.yaml` or relevant autowiring config — wire the new search reader if needed.
- `tasks/todo.md` — add/update the task tracking entry for this feature.

### Maybe Modify (only if needed after inspection)
- `src/Station/Application/Repository/StationRepository.php` — only if current interface lacks a safe method needed by the picker flow.
- `src/Station/Infrastructure/Persistence/Doctrine/Repository/DoctrineStationRepository.php` — only if a lightweight bulk/get helper is actually needed beyond existing methods.
- `docs/ai/memory.md` — only if implementation uncovers a reusable bug/root-cause lesson.

---

### Task 1: Track The Feature And Lock The Shared Search Contract

**Files:**
- Modify: `tasks/todo.md`
- Create: `src/Station/Application/Search/StationSearchCandidate.php`
- Create: `src/Station/Application/Search/StationSearchQuery.php`
- Create: `src/Station/Application/Search/StationSearchReader.php`

- [ ] **Step 1: Add the new task entry to `tasks/todo.md`**

```md
# TODO - SP40-001 Station picker and import matching

## Plan
- [ ] Add a shared station candidate search read model.
- [ ] Support explicit existing-station selection in manual receipt creation.
- [ ] Reuse the same station selection flow during import review finalization.
- [ ] Add or update unit/integration/functional coverage and run quality gates.
```

- [ ] **Step 2: Create `src/Station/Application/Search/StationSearchCandidate.php`**

```php
<?php

declare(strict_types=1);

namespace App\Station\Application\Search;

final readonly class StationSearchCandidate
{
    public function __construct(
        public string $id,
        public string $name,
        public string $streetName,
        public string $postalCode,
        public string $city,
        public ?int $latitudeMicroDegrees,
        public ?int $longitudeMicroDegrees,
    ) {
    }

    public function label(): string
    {
        return sprintf('%s - %s, %s %s', $this->name, $this->streetName, $this->postalCode, $this->city);
    }
}
```

- [ ] **Step 3: Create `src/Station/Application/Search/StationSearchQuery.php`**

```php
<?php

declare(strict_types=1);

namespace App\Station\Application\Search;

final readonly class StationSearchQuery
{
    public function __construct(
        public ?string $freeText,
        public ?string $name,
        public ?string $streetName,
        public ?string $postalCode,
        public ?string $city,
        public int $limit = 6,
    ) {
    }
}
```

- [ ] **Step 4: Create `src/Station/Application/Search/StationSearchReader.php`**

```php
<?php

declare(strict_types=1);

namespace App\Station\Application\Search;

interface StationSearchReader
{
    /**
     * @return list<StationSearchCandidate>
     */
    public function search(StationSearchQuery $query): array;
}
```

- [ ] **Step 5: Commit the contract scaffolding**

```bash
git add tasks/todo.md src/Station/Application/Search/StationSearchCandidate.php src/Station/Application/Search/StationSearchQuery.php src/Station/Application/Search/StationSearchReader.php
git commit -m "Add station picker search contracts"
```

### Task 2: Implement The Doctrine Candidate Reader First

**Files:**
- Create: `src/Station/Infrastructure/Search/DoctrineStationSearchReader.php`
- Create: `tests/Integration/Station/Infrastructure/DoctrineStationSearchReaderTest.php`
- Modify: `config/services.yaml`

- [ ] **Step 1: Write the failing integration test for scoped candidate search**

```php
public function testSearchReturnsReadableStationsOrderedByPostalCodeCityAndTextMatch(): void
{
    $owner = $this->createUser('station.search@example.com');
    $visibleA = $this->createOwnedStation($owner, 'PETRO EST', 'LECLERC SEZANNE HYPER', '51120', 'SEZANNE');
    $visibleB = $this->createOwnedStation($owner, 'TOTAL EXPRESS', '40 Rue Robert Schuman', '51120', 'SEZANNE');
    $hidden = $this->createForeignStation('PETRO EST', 'Rue A', '75001', 'Paris');
    $this->em->flush();

    self::loginUser($owner);
    $reader = static::getContainer()->get(StationSearchReader::class);
    self::assertInstanceOf(StationSearchReader::class, $reader);

    $results = $reader->search(new StationSearchQuery('petro sezanne', 'PETRO EST', null, '51120', 'SEZANNE'));

    self::assertCount(2, $results);
    self::assertSame($visibleA->getId()->toRfc4122(), $results[0]->id);
    self::assertSame($visibleB->getId()->toRfc4122(), $results[1]->id);
    self::assertNotContains($hidden->getId()->toRfc4122(), array_map(static fn (StationSearchCandidate $c): string => $c->id, $results), true);
}
```

- [ ] **Step 2: Run the new integration test and verify it fails because the reader does not exist yet**

Run: `php bin/phpunit tests/Integration/Station/Infrastructure/DoctrineStationSearchReaderTest.php -v`
Expected: FAIL with missing class/service.

- [ ] **Step 3: Implement `src/Station/Infrastructure/Search/DoctrineStationSearchReader.php`**

```php
<?php

declare(strict_types=1);

namespace App\Station\Infrastructure\Search;

use App\Station\Application\Search\StationSearchCandidate;
use App\Station\Application\Search\StationSearchQuery;
use App\Station\Application\Search\StationSearchReader;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineStationSearchReader implements StationSearchReader
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function search(StationSearchQuery $query): array
    {
        $qb = $this->em->getRepository(StationEntity::class)->createQueryBuilder('s');
        $qb
            ->select('s')
            ->setMaxResults($query->limit);

        $this->applyReadableByCurrentUser($qb, 's');
        $this->applyStructuredFilters($qb, $query);
        $this->applyFreeTextFilter($qb, $query);
        $this->applyOrdering($qb, $query);

        $entities = $qb->getQuery()->getResult();
        $results = [];
        foreach ($entities as $entity) {
            if (!$entity instanceof StationEntity) {
                continue;
            }

            $results[] = new StationSearchCandidate(
                $entity->getId()->toRfc4122(),
                $entity->getName(),
                $entity->getStreetName(),
                $entity->getPostalCode(),
                $entity->getCity(),
                $entity->getLatitudeMicroDegrees(),
                $entity->getLongitudeMicroDegrees(),
            );
        }

        return $results;
    }

    private function applyStructuredFilters(QueryBuilder $qb, StationSearchQuery $query): void
    {
        if (null !== $query->postalCode && '' !== trim($query->postalCode)) {
            $qb->andWhere('s.postalCode = :postalCode')->setParameter('postalCode', trim($query->postalCode));
        }

        if (null !== $query->city && '' !== trim($query->city)) {
            $qb->andWhere('LOWER(s.city) LIKE :city')->setParameter('city', '%'.mb_strtolower(trim($query->city)).'%');
        }
    }

    private function applyFreeTextFilter(QueryBuilder $qb, StationSearchQuery $query): void
    {
        $terms = array_values(array_filter(preg_split('/\\s+/', mb_strtolower(trim((string) $query->freeText))) ?: []));
        foreach ($terms as $index => $term) {
            $param = 'term'.$index;
            $qb->andWhere(sprintf('(LOWER(s.name) LIKE :%1$s OR LOWER(s.streetName) LIKE :%1$s OR LOWER(s.city) LIKE :%1$s OR s.postalCode LIKE :%1$s)', $param));
            $qb->setParameter($param, '%'.$term.'%');
        }
    }

    private function applyOrdering(QueryBuilder $qb, StationSearchQuery $query): void
    {
        $qb
            ->addOrderBy('CASE WHEN s.postalCode = :preferredPostalCode THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('CASE WHEN LOWER(s.city) = :preferredCity THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->addOrderBy('s.city', 'ASC')
            ->setParameter('preferredPostalCode', (string) ($query->postalCode ?? ''))
            ->setParameter('preferredCity', mb_strtolower((string) ($query->city ?? '')));
    }
}
```

- [ ] **Step 4: Wire the implementation if autowiring alone is not enough**

```yaml
services:
    App\Station\Application\Search\StationSearchReader: '@App\Station\Infrastructure\Search\DoctrineStationSearchReader'
```

- [ ] **Step 5: Run the integration test again and verify it passes**

Run: `php bin/phpunit tests/Integration/Station/Infrastructure/DoctrineStationSearchReaderTest.php -v`
Expected: PASS.

- [ ] **Step 6: Commit the read model**

```bash
git add src/Station/Infrastructure/Search/DoctrineStationSearchReader.php src/Station/Application/Search/StationSearchCandidate.php src/Station/Application/Search/StationSearchQuery.php src/Station/Application/Search/StationSearchReader.php tests/Integration/Station/Infrastructure/DoctrineStationSearchReaderTest.php config/services.yaml
git commit -m "Add station picker candidate search"
```

### Task 3: Extend Receipt Creation To Accept An Explicit Station Selection

**Files:**
- Modify: `src/Receipt/Application/Command/CreateReceiptWithStationCommand.php`
- Modify: `src/Receipt/Application/Command/CreateReceiptWithStationHandler.php`
- Modify: `tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`

- [ ] **Step 1: Add failing unit tests for selected-station-first behavior**

```php
public function testItUsesSelectedStationIdBeforeIdentityLookup(): void
{
    $selectedStation = Station::reconstitute(
        StationId::fromString('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01'),
        'Selected Station',
        '1 Picker Road',
        '51120',
        'SEZANNE',
        null,
        null,
    );

    $stationRepo = new InMemoryStationRepository($selectedStation);
    $stationRepo->setIdentityResult(null);
    $handler = new CreateReceiptWithStationHandler(new CreateReceiptHandler(new InMemoryReceiptRepository()), $stationRepo, new CreateStationHandler($stationRepo, new NullMessageBus()));

    $receipt = $handler(new CreateReceiptWithStationCommand(
        new DateTimeImmutable('2026-04-29T12:00:00+00:00'),
        [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
        'Typed Name',
        'Typed Street',
        '75001',
        'Paris',
        null,
        null,
        selectedStationId: '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01',
    ));

    self::assertSame('018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b01', $receipt->stationId()?->toString());
}

public function testItThrowsWhenSelectedStationDoesNotExist(): void
{
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Selected station was not found.');

    $stationRepo = new InMemoryStationRepository(null);
    $handler = new CreateReceiptWithStationHandler(new CreateReceiptHandler(new InMemoryReceiptRepository()), $stationRepo, new CreateStationHandler($stationRepo, new NullMessageBus()));

    $handler(new CreateReceiptWithStationCommand(
        new DateTimeImmutable('2026-04-29T12:00:00+00:00'),
        [new CreateReceiptLineCommand(FuelType::SP95, 1000, 180, 20)],
        'Typed Name',
        'Typed Street',
        '75001',
        'Paris',
        null,
        null,
        selectedStationId: '018f1f8b-6d3c-7f11-8c0f-3c5f4d3e9b99',
    ));
}
```

- [ ] **Step 2: Run the unit test class and verify it fails**

Run: `php bin/phpunit tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php -v`
Expected: FAIL due to missing `selectedStationId` support.

- [ ] **Step 3: Extend `CreateReceiptWithStationCommand` with the optional selected id**

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
    public ?string $selectedStationId = null,
) {
}
```

- [ ] **Step 4: Update `CreateReceiptWithStationHandler` to prefer the selected station**

```php
public function __invoke(CreateReceiptWithStationCommand $command): Receipt
{
    $station = null;

    if (null !== $command->selectedStationId) {
        $station = $this->stationRepository->get($command->selectedStationId);
        if (null === $station) {
            throw new RuntimeException('Selected station was not found.');
        }
    }

    if (null === $station) {
        $station = $this->stationRepository->findByIdentity(
            $command->stationName,
            $command->stationStreetName,
            $command->stationPostalCode,
            $command->stationCity,
        );
    }

    if (null === $station) {
        // keep existing create/race-handling branch unchanged
    }

    return ($this->receiptHandler)(new CreateReceiptCommand(
        $command->issuedAt,
        $command->lines,
        StationId::fromString($station->id()->toString()),
        null === $command->vehicleId ? null : VehicleId::fromString($command->vehicleId),
        $command->ownerId,
        $command->odometerKilometers,
    ));
}
```

- [ ] **Step 5: Update the test double in `CreateReceiptWithStationHandlerTest.php`**

```php
final class InMemoryStationRepository implements StationRepository
{
    private ?Station $station;
    private ?Station $fallback = null;
    private ?Station $identityResult = null;

    public function setIdentityResult(?Station $station): void
    {
        $this->identityResult = $station;
    }

    public function findByIdentity(string $name, string $streetName, string $postalCode, string $city): ?Station
    {
        if (null !== $this->identityResult) {
            return $this->identityResult;
        }

        if (null !== $this->station) {
            return $this->station;
        }

        return $this->fallback;
    }
}
```

- [ ] **Step 6: Run the unit test class again and verify it passes**

Run: `php bin/phpunit tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php -v`
Expected: PASS.

- [ ] **Step 7: Commit the write-path support**

```bash
git add src/Receipt/Application/Command/CreateReceiptWithStationCommand.php src/Receipt/Application/Command/CreateReceiptWithStationHandler.php tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php
git commit -m "Support explicit station selection for receipt creation"
```

### Task 4: Add The Manual Receipt Picker UI And Controller Wiring

**Files:**
- Modify: `src/Receipt/UI/Web/Controller/CreateReceiptController.php`
- Modify: `templates/receipt/_form.html.twig`
- Modify: `tests/Functional/Receipt/ReceiptWebUiTest.php`

- [ ] **Step 1: Add a failing functional test for selecting an existing station from the create form**

```php
public function testUserCanSelectExistingStationWhenCreatingReceipt(): void
{
    $email = 'receipt.ui.station-picker@example.com';
    $password = 'test1234';
    $owner = $this->createUser($email, $password, ['ROLE_USER']);

    $station = new StationEntity();
    $station->setId(Uuid::v7());
    $station->setName('PETRO EST');
    $station->setStreetName('LECLERC SEZANNE HYPER');
    $station->setPostalCode('51120');
    $station->setCity('SEZANNE');
    $this->em->persist($station);
    $this->createOwnedReceiptForStation($owner, $station);
    $this->em->flush();

    $this->loginWithUiForm($email, $password);

    $page = $this->request('GET', '/ui/receipts/new');
    self::assertSame(Response::HTTP_OK, $page->getStatusCode());
    $content = (string) $page->getContent();
    self::assertStringContainsString('Existing station', $content);
    self::assertStringContainsString('PETRO EST - LECLERC SEZANNE HYPER, 51120 SEZANNE', $content);
    $csrf = $this->extractFormCsrf($content);

    $response = $this->request('POST', '/ui/receipts/new', [
        '_token' => $csrf,
        'issuedAt' => '2026-04-29T12:00',
        'fuelType' => 'diesel',
        'quantityLiters' => '40.000',
        'unitPriceEurosPerLiter' => '1.700',
        'vatRatePercent' => '20',
        'selectedStationId' => $station->getId()->toRfc4122(),
        'stationName' => 'OCR Variant',
        'stationStreetName' => 'OCR Variant Street',
        'stationPostalCode' => '51120',
        'stationCity' => 'SEZANNE',
    ]);

    self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());

    $this->em->clear();
    $saved = $this->em->getRepository(ReceiptEntity::class)->findOneBy(['owner' => $owner]);
    self::assertInstanceOf(ReceiptEntity::class, $saved);
    self::assertSame($station->getId()->toRfc4122(), $saved->getStation()?->getId()->toRfc4122());
}
```

- [ ] **Step 2: Run that functional test manually later through the user handoff**

Do not run `make phpunit-functional` automatically. Keep this test ready for user-side validation.

- [ ] **Step 3: Extend `CreateReceiptController` form data with picker state and candidates**

```php
$formData = [
    // existing fields...
    'selectedStationId' => $prefilledStation?->id()->toString() ?? '',
    'stationSearch' => $prefilledStation?->name() ?? '',
    '_token' => '',
];

$stationCandidates = $this->stationSearchReader->search(new StationSearchQuery(
    $this->nullIfEmpty($formData['stationSearch']),
    $this->nullIfEmpty($formData['stationName']),
    $this->nullIfEmpty($formData['stationStreetName']),
    $this->nullIfEmpty($formData['stationPostalCode']),
    $this->nullIfEmpty($formData['stationCity']),
));
```

- [ ] **Step 4: Validate the selected station id in `CreateReceiptController`**

```php
$selectedStationId = $this->nullIfEmpty($formData['selectedStationId']);
if (null !== $selectedStationId && null === $this->stationRepository->get($selectedStationId)) {
    $errors[] = 'Selected station was not found.';
}
```

- [ ] **Step 5: Pass the selected station id into `CreateReceiptWithStationCommand`**

```php
$command = new CreateReceiptWithStationCommand(
    // existing args...
    $this->nullIfEmpty($formData['vehicleId']),
    $ownerId,
    odometerKilometers: $this->toNullableInt($formData['odometerKilometers']),
    selectedStationId: $this->nullIfEmpty($formData['selectedStationId']),
);
```

- [ ] **Step 6: Render the picker block in `templates/receipt/_form.html.twig`**

```twig
<label class="label">
    Existing station (optional)
    <input class="input" type="text" name="stationSearch" value="{{ formData.stationSearch }}" placeholder="Search by name, street, postal code, or city">
    <small class="muted">Pick an existing station when one already matches this receipt.</small>
</label>
{% if stationCandidates is not empty %}
    <div class="choice-stack">
        {% for candidate in stationCandidates %}
            <label class="choice-card">
                <input type="radio" name="selectedStationId" value="{{ candidate.id }}" {% if formData.selectedStationId == candidate.id %}checked{% endif %}>
                <span><strong>{{ candidate.name }}</strong><br>{{ candidate.streetName }}, {{ candidate.postalCode }} {{ candidate.city }}</span>
            </label>
        {% endfor %}
        <label class="choice-card">
            <input type="radio" name="selectedStationId" value="" {% if formData.selectedStationId == '' %}checked{% endif %}>
            <span>Do not use an existing station</span>
        </label>
    </div>
{% endif %}
```

- [ ] **Step 7: Commit the manual receipt picker UI**

```bash
git add src/Receipt/UI/Web/Controller/CreateReceiptController.php templates/receipt/_form.html.twig tests/Functional/Receipt/ReceiptWebUiTest.php
git commit -m "Add station picker to receipt creation"
```

### Task 5: Reuse The Picker In Import Review Finalization

**Files:**
- Modify: `src/Import/Application/Command/FinalizeImportJobCommand.php`
- Modify: `src/Import/Application/Command/FinalizeImportJobHandler.php`
- Modify: `src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php`
- Modify: `src/Import/UI/Web/Controller/ImportJobShowWebController.php`
- Modify: `templates/import/show.html.twig`
- Modify: `tests/Functional/Import/ImportWebUiTest.php`

- [ ] **Step 1: Add a failing functional test for selecting an existing station during import review**

```php
public function testUserCanFinalizeImportUsingSelectedExistingStation(): void
{
    $email = 'import.web.station-picker@example.com';
    $password = 'test1234';
    $user = $this->createUser($email, $password);

    $station = new StationEntity();
    $station->setId(Uuid::v7());
    $station->setName('PETRO EST');
    $station->setStreetName('LECLERC SEZANNE HYPER');
    $station->setPostalCode('51120');
    $station->setCity('SEZANNE');
    $this->em->persist($station);

    $this->createOwnedReceiptForStation($user, $station);
    $job = $this->createNeedsReviewJob($user, 'picker-review.jpg', '2026-04-29 10:00:00', 'x');
    $this->em->persist($job);
    $this->em->flush();

    $sessionCookie = $this->loginWithUiForm($email, $password);
    $jobId = $job->getId()->toRfc4122();

    $page = $this->request('GET', '/ui/imports/'.$jobId, [], [], $sessionCookie);
    self::assertSame(Response::HTTP_OK, $page->getStatusCode());
    $content = (string) $page->getContent();
    self::assertStringContainsString('Existing station', $content);
    self::assertStringContainsString('PETRO EST - LECLERC SEZANNE HYPER, 51120 SEZANNE', $content);
    $csrf = $this->extractFinalizeCsrfToken($content, $jobId);

    $response = $this->request('POST', '/ui/imports/'.$jobId.'/finalize', [
        '_token' => $csrf,
        'selectedStationId' => $station->getId()->toRfc4122(),
    ], [], $sessionCookie);

    self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());

    $this->em->clear();
    $savedReceipt = $this->em->getRepository(ReceiptEntity::class)->findOneBy(['owner' => $user]);
    self::assertInstanceOf(ReceiptEntity::class, $savedReceipt);
    self::assertSame($station->getId()->toRfc4122(), $savedReceipt->getStation()?->getId()->toRfc4122());
}
```

- [ ] **Step 2: Extend `FinalizeImportJobCommand` with `selectedStationId`**

```php
public function __construct(
    public string $importJobId,
    public ?DateTimeImmutable $issuedAt = null,
    public ?array $lines = null,
    public ?string $stationName = null,
    public ?string $stationStreetName = null,
    public ?string $stationPostalCode = null,
    public ?string $stationCity = null,
    public ?int $latitudeMicroDegrees = null,
    public ?int $longitudeMicroDegrees = null,
    public ?int $odometerKilometers = null,
    public ?string $selectedStationId = null,
) {
}
```

- [ ] **Step 3: Pass the selected id through the finalize controller and handler**

```php
($this->finalizeImportJobHandler)(new FinalizeImportJobCommand(
    $id,
    $this->toNullableDateTime($request->request->get('issuedAt')),
    $this->toNullableLines($request),
    $this->toNullableString($request->request->get('stationName')),
    $this->toNullableString($request->request->get('stationStreetName')),
    $this->toNullableString($request->request->get('stationPostalCode')),
    $this->toNullableString($request->request->get('stationCity')),
    $this->toNullableInt($request->request->get('latitudeMicroDegrees')),
    $this->toNullableInt($request->request->get('longitudeMicroDegrees')),
    $this->toNullableInt($request->request->get('odometerKilometers')),
    $this->toNullableString($request->request->get('selectedStationId')),
));
```

```php
$receipt = ($this->createReceiptWithStationHandler)(new CreateReceiptWithStationCommand(
    $issuedAt,
    $lines,
    $stationName,
    $stationStreetName,
    $stationPostalCode,
    $stationCity,
    $latitudeMicroDegrees,
    $longitudeMicroDegrees,
    ownerId: $job->ownerId(),
    odometerKilometers: $odometerKilometers,
    selectedStationId: $command->selectedStationId,
));
```

- [ ] **Step 4: Expose import review candidates from `ImportJobShowWebController`**

```php
$stationCandidates = $this->stationSearchReader->search(new StationSearchQuery(
    implode(' ', array_filter([
        $this->readStringValue($creationPayload, 'stationName'),
        $this->readStringValue($creationPayload, 'stationStreetName'),
        $this->readStringValue($creationPayload, 'stationPostalCode'),
        $this->readStringValue($creationPayload, 'stationCity'),
    ])),
    $this->readStringValue($creationPayload, 'stationName'),
    $this->readStringValue($creationPayload, 'stationStreetName'),
    $this->readStringValue($creationPayload, 'stationPostalCode'),
    $this->readStringValue($creationPayload, 'stationCity'),
));
```

- [ ] **Step 5: Render the same picker block in `templates/import/show.html.twig`**

```twig
<div class="form-stack card-subtle card-subtle-block">
    <label class="label">
        Existing station (optional)
        <input class="input" type="text" name="stationSearch" value="{{ stationSearch|default('') }}" readonly>
        <small class="muted">Pick an existing station when the OCR values are close but not exact.</small>
    </label>
    {% if stationCandidates is not empty %}
        <div class="choice-stack">
            {% for candidate in stationCandidates %}
                <label class="choice-card">
                    <input type="radio" name="selectedStationId" value="{{ candidate.id }}">
                    <span><strong>{{ candidate.name }}</strong><br>{{ candidate.streetName }}, {{ candidate.postalCode }} {{ candidate.city }}</span>
                </label>
            {% endfor %}
            <label class="choice-card">
                <input type="radio" name="selectedStationId" value="" checked>
                <span>Keep manual station fields</span>
            </label>
        </div>
    {% endif %}
</div>
```

- [ ] **Step 6: Commit the import review reuse**

```bash
git add src/Import/Application/Command/FinalizeImportJobCommand.php src/Import/Application/Command/FinalizeImportJobHandler.php src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php src/Import/UI/Web/Controller/ImportJobShowWebController.php templates/import/show.html.twig tests/Functional/Import/ImportWebUiTest.php
git commit -m "Reuse station picker in import review"
```

### Task 6: Verification, Cleanup, And Handover

**Files:**
- Modify: `docs/ai/memory.md` only if a reusable bug/root-cause is discovered.
- Maybe Modify: `config/reference.php` if Symfony tooling updates it during checks.

- [ ] **Step 1: Run unit tests**

Run: `make phpunit-unit`
Expected: PASS.

- [ ] **Step 2: Run integration tests**

Run: `make phpunit-integration`
Expected: PASS.

- [ ] **Step 3: Run static analysis**

Run: `make phpstan`
Expected: PASS.

- [ ] **Step 4: Run formatter check**

Run: `make php-cs-fixer-check`
Expected: PASS.

- [ ] **Step 5: Run duplicate-suffix scan before final commit**

Run: `find . -path ./vendor -prune -o -path ./var -prune -o \( -name '* 2*' -print \)`
Expected: no output.

- [ ] **Step 6: Prepare user-side functional validation request**

Ask the user to run:
```bash
make phpunit-functional
```

Expected focus areas to mention in handoff:
- `/ui/receipts/new` existing station selection
- `/ui/imports/{id}` needs-review existing station selection
- free-form fallback still creates/uses a station when nothing is selected

- [ ] **Step 7: Commit any final fixes after user functional feedback**

```bash
git add <fixed-files>
git commit -m "Fix station picker functional feedback"
```

---

## Self-Review

### Spec coverage
- Shared station candidate reader: covered in Tasks 1-2.
- Manual receipt selection flow: covered in Tasks 3-4.
- Import review reuse: covered in Task 5.
- Tests and quality gates: covered in Task 6.
- Deferred favorites/admin/public-station linking: intentionally excluded from implementation scope.

### Placeholder scan
- No `TODO`/`TBD` placeholders remain in the execution steps.
- Every task includes exact file paths and concrete code/commands.

### Type consistency
- The optional selected id is consistently named `selectedStationId` across command, controller, Twig, and tests.
- The read-side contract consistently uses `StationSearchReader`, `StationSearchQuery`, and `StationSearchCandidate`.
