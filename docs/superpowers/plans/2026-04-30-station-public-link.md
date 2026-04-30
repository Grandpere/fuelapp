# Station/Public Durable Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist a durable `publicSourceId` on internal `Station` entities whenever a public station suggestion is selected during receipt creation or import finalization.

**Architecture:** Extend the `Station` domain and Doctrine persistence with a nullable unique public-source identifier, then reuse the existing public-suggestion write flow to attach or validate that link. Keep `Station` as the only receipt-linked entity and reject conflicting relinks instead of silently overwriting.

**Tech Stack:** Symfony 8, Doctrine ORM/DBAL migrations, Twig, PHPUnit (Unit/Integration/Functional), PHPStan, PHP-CS-Fixer.

---

## File Map

### Existing files to modify
- `src/Station/Domain/Station.php`
  - Add `publicSourceId` state, factory/reconstitution support, and link mutation rules.
- `src/Station/Application/Command/CreateStationCommand.php`
  - Allow optional `publicSourceId` during creation.
- `src/Station/Application/Command/CreateStationHandler.php`
  - Pass the new field into station creation.
- `src/Station/Infrastructure/Persistence/Doctrine/Entity/StationEntity.php`
  - Persist nullable unique `public_source_id`.
- `src/Station/Infrastructure/Persistence/Doctrine/Repository/DoctrineStationRepository.php`
  - Map `publicSourceId` both directions and persist updates.
- `src/Receipt/Application/Command/CreateReceiptWithStationHandler.php`
  - Attach link on public selection and reject conflicting relinks.
- `src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php`
  - Catch link conflict error as user-facing validation feedback if needed.
- `src/Receipt/UI/Web/Controller/CreateReceiptController.php`
  - Surface link conflict error without 500.
- `tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`
  - Cover set/idempotent/conflict behaviors.
- `tests/Integration/Station/Infrastructure/DoctrineStationRepositoryTest.php`
  - Round-trip `publicSourceId`.
- `tests/Functional/Receipt/ReceiptWebUiTest.php`
  - Verify saved station keeps link and conflict is user-facing.
- `tests/Functional/Import/ImportWebUiTest.php`
  - Verify import finalize stores link and conflict is user-facing.
- `docs/ai/memory.md`
  - Record any link-conflict or migration lesson discovered.
- `tasks/todo.md`
  - Track `SP40-003` progress.

### New files to create
- `src/Station/Application/Exception/StationPublicSourceConflict.php`
  - Explicit application/domain-safe exception for conflicting relinks.
- `migrations/Version20260430xxxxxx.php`
  - Add `stations.public_source_id` and unique index.
- `tests/Integration/Station/Infrastructure/DoctrineStationRepositoryTest.php`
  - If no repository integration test file exists yet, create one.

---

### Task 1: Add `publicSourceId` to Station domain and persistence

**Files:**
- Modify: `src/Station/Domain/Station.php`
- Modify: `src/Station/Application/Command/CreateStationCommand.php`
- Modify: `src/Station/Application/Command/CreateStationHandler.php`
- Modify: `src/Station/Infrastructure/Persistence/Doctrine/Entity/StationEntity.php`
- Modify: `src/Station/Infrastructure/Persistence/Doctrine/Repository/DoctrineStationRepository.php`
- Create: `migrations/Version20260430xxxxxx.php`
- Test: `tests/Integration/Station/Infrastructure/DoctrineStationRepositoryTest.php`

- [ ] **Step 1: Write failing integration test for station public source round-trip**

```php
public function testItPersistsPublicSourceId(): void
{
    $station = Station::create(
        'TOTAL',
        '40 Rue Robert Schuman',
        '5751',
        'FRISANGE',
        49569000,
        4230000,
        'public-1',
    );

    $this->repository->save($station);
    $reloaded = $this->repository->getForSystem($station->id()->toString());

    self::assertSame('public-1', $reloaded?->publicSourceId());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Integration/Station/Infrastructure/DoctrineStationRepositoryTest.php`
Expected: FAIL because `publicSourceId` is not present yet.

- [ ] **Step 3: Add minimal domain and persistence support**

Implementation checklist:
- add nullable `publicSourceId` property + getter on `Station`
- extend `Station::create()` and `Station::reconstitute()` signatures
- extend `CreateStationCommand` / handler
- add Doctrine entity column `public_source_id`
- map repository hydration/persistence
- generate migration with nullable unique column

- [ ] **Step 4: Run integration test to verify it passes**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Integration/Station/Infrastructure/DoctrineStationRepositoryTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Station/Domain/Station.php src/Station/Application/Command/CreateStationCommand.php src/Station/Application/Command/CreateStationHandler.php src/Station/Infrastructure/Persistence/Doctrine/Entity/StationEntity.php src/Station/Infrastructure/Persistence/Doctrine/Repository/DoctrineStationRepository.php migrations/Version20260430xxxxxx.php tests/Integration/Station/Infrastructure/DoctrineStationRepositoryTest.php
git commit -m "Add public source id to stations"
```

---

### Task 2: Attach and validate the link in the public-suggestion write path

**Files:**
- Modify: `src/Receipt/Application/Command/CreateReceiptWithStationHandler.php`
- Create: `src/Station/Application/Exception/StationPublicSourceConflict.php`
- Test: `tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`

- [ ] **Step 1: Write failing unit tests for link set, idempotence, and conflict**

```php
public function testItStoresPublicSourceIdOnCreatedStation(): void
public function testItKeepsSamePublicSourceIdWhenAlreadyLinked(): void
public function testItRejectsDifferentPublicSourceIdForAlreadyLinkedStation(): void
```

Assertions:
- created/reused station gets `publicSourceId === 'public-1'`
- same link stays accepted
- conflicting link raises `StationPublicSourceConflict`

- [ ] **Step 2: Run targeted unit test to verify failure**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`
Expected: FAIL on missing link behavior.

- [ ] **Step 3: Implement minimal link-attachment logic**

Implementation checklist:
- create `StationPublicSourceConflict`
- after resolving selected public suggestion to internal station:
  - if no link, attach selected source
  - if same link, keep going
  - if different link, throw conflict exception
- when creating a brand new station from public suggestion, pass `publicSourceId` into `CreateStationCommand`
- keep behavior unchanged for pure internal-station or manual flows

- [ ] **Step 4: Run targeted unit test to verify pass**

Run: `docker compose -f resources/docker/compose.yml --env-file resources/docker/.env exec -T app vendor/bin/phpunit --configuration phpunit.xml tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Receipt/Application/Command/CreateReceiptWithStationHandler.php src/Station/Application/Exception/StationPublicSourceConflict.php tests/Unit/Receipt/Application/Command/CreateReceiptWithStationHandlerTest.php
git commit -m "Persist public source link on station selection"
```

---

### Task 3: Surface link conflicts cleanly in receipt and import web flows

**Files:**
- Modify: `src/Receipt/UI/Web/Controller/CreateReceiptController.php`
- Modify: `src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php`
- Test: `tests/Functional/Receipt/ReceiptWebUiTest.php`
- Test: `tests/Functional/Import/ImportWebUiTest.php`

- [ ] **Step 1: Write failing functional tests for conflict recovery**

Receipt scenario:
- station already linked to `public-1`
- user selects `public-2` for the same internal station identity
- expect user-facing error, not a 500

Import scenario:
- same conflict path during finalize
- expect redirect back with clear error message

- [ ] **Step 2: Run targeted functional tests to verify current failure**

Run later by user per project rule:
- `make phpunit-functional`
Expected: FAIL before controller exception handling is updated.

- [ ] **Step 3: Catch and expose `StationPublicSourceConflict`**

Implementation checklist:
- receipt web controller: add validation-style error handling around save
- import finalize controller: catch `StationPublicSourceConflict` in same branch as current user-facing station errors
- preserve non-conflict failures unchanged

- [ ] **Step 4: Ask user to rerun functional suite**

Ask for:
- `make phpunit-functional`
- share exact failing output if anything still fails

- [ ] **Step 5: Commit**

```bash
git add src/Receipt/UI/Web/Controller/CreateReceiptController.php src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php tests/Functional/Receipt/ReceiptWebUiTest.php tests/Functional/Import/ImportWebUiTest.php
git commit -m "Handle station public link conflicts in web flows"
```

---

### Task 4: Run full local quality gates and update project docs

**Files:**
- Modify: `docs/ai/memory.md`
- Modify: `tasks/todo.md`

- [ ] **Step 1: Record any discovered lesson**

If implementation confirms a stable pattern, add a short entry like:

```md
## 2026-04-30 - Station/public links must reject conflicting relinks
- Symptom:
- Root cause:
- Fix:
- Prevention:
```

- [ ] **Step 2: Mark `SP40-003` progress in `tasks/todo.md`**

Update all checklist lines to completed once code and tests are done.

- [ ] **Step 3: Run project quality gates**

Run:
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`

Expected:
- all green locally

- [ ] **Step 4: Ask user for final manual validation**

Ask user to run, in order:
- `make restart-app`
- `make phpunit-functional`

Manual checks:
- receipt creation from a public suggestion still works
- import finalize from a public suggestion still works
- conflict path shows a user-facing message

- [ ] **Step 5: Commit**

```bash
git add docs/ai/memory.md tasks/todo.md
git commit -m "Document station public link rules"
```

---

## Self-Review

### Spec coverage
- `publicSourceId` on `Station`: covered by Task 1
- attach link on public suggestion: covered by Task 2
- reject conflicts: covered by Task 2 + Task 3
- no admin coverage now: reflected in scope, no implementation task added
- tests + quality gates: covered by Tasks 1 through 4

### Placeholder scan
- no `TBD` or deferred implementation placeholders remain in task steps
- each task states concrete files, commands, and expected behavior

### Type consistency
- single field name used everywhere: `publicSourceId`
- single conflict type used everywhere: `StationPublicSourceConflict`

