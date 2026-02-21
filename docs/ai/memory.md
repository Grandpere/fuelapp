# AI Memory

Project memory for recurring pitfalls, decisions, and proven fixes.

## How to use
- Add one entry per incident/decision.
- Keep it short, factual, and actionable.
- Prefer prevention rules over long narratives.
- Update this file immediately after a bug is diagnosed/fixed.

## Entry template

```md
## YYYY-MM-DD - Short title
- Symptom:
- Root cause:
- Fix:
- Prevention:
- Links: (optional)
```

## Incident Log

## 2026-02-19 - Docker compose path mismatch
- Symptom: `make up` failed with missing compose file.
- Root cause: path/name drift between compose files and Makefile assumptions.
- Fix: centralized compose usage through `DC` in `Makefile`.
- Prevention: use Make targets; do not hardcode compose file paths in ad-hoc commands.

## 2026-02-19 - Port collisions on local host
- Symptom: stack failed with `port is already allocated` (5432/5672/8080...).
- Root cause: default host ports already used by other local stacks.
- Fix: set alternate host ports in compose env/config.
- Prevention: keep host ports configurable and avoid assuming defaults are free.

## 2026-02-19 - FrankenPHP runtime class missing
- Symptom: `Runtime\\FrankenPhpSymfony\\Runtime not found` at startup.
- Root cause: missing/incompatible runtime package strategy for current Symfony version.
- Fix: align runtime strategy with installed Symfony/runtime packages.
- Prevention: verify package compatibility before adding runtime adapters.

## 2026-02-19 - Twig class not autoloaded while package exists
- Symptom: `Class "Twig\\Environment" not found` during cache clear.
- Root cause: broken/incomplete vendor autoload state.
- Fix: reinstall dependencies and regenerate autoload; verify with `class_exists`.
- Prevention: if class exists on disk but not in runtime, suspect autoload/vendor state first.

## 2026-02-19 - `columns[]` query param parsing errors
- Symptom: export/list errors about non-scalar values and wrong column behavior.
- Root cause: array query param read as scalar.
- Fix: parse as array (`all('columns')` style).
- Prevention: multi-select query params must always be parsed as arrays.

## 2026-02-19 - Flatpickr vendor asset missing
- Symptom: template error `The "flatpickr" vendor asset is missing`.
- Root cause: package added to `importmap.php` without installing vendor assets.
- Fix: run `php bin/console importmap:install` in app container.
- Prevention: every importmap addition requires install step.

## 2026-02-19 - API Platform DELETE returned 404 on custom resource
- Symptom: `DELETE /api/stations/{id}` returned 404 for existing station.
- Root cause: API Platform attempted a pre-read not compatible with this processor-based resource flow.
- Fix: set `read: false` on DELETE operation and handle deletion in processor.
- Prevention: for processor-driven DELETE, explicitly choose `read` behavior.

## 2026-02-20 - Ownership model clarified: user owns receipts, not stations
- Symptom: ownership column was added on `stations` then rejected by business rules.
- Root cause: technical assumption conflicted with domain model.
- Fix: remove `stations.owner_id`; keep ownership on receipts only.
- Prevention: validate ownership semantics against domain language before migration design.

## 2026-02-20 - Symfony security entry point type mismatch
- Symptom: runtime `TypeError` on firewall exception listener (`entry_point` received authenticator with wrong interface).
- Root cause: custom authenticator used as `entry_point` without implementing `AuthenticationEntryPointInterface`.
- Fix: implement `AuthenticationEntryPointInterface` and `start()` in login authenticator.
- Prevention: when declaring `entry_point`, ensure service explicitly implements the expected interface.

## 2026-02-20 - API auth strategy switched from Basic to JWT
- Symptom: Basic auth considered unsuitable for front/mobile usage.
- Root cause: temporary baseline did not match product auth target.
- Fix: implement `/api/login` and Bearer token authenticator for `/api/*`.
- Prevention: align interim security design with expected client architecture early.

## 2026-02-20 - UI logout hardened with CSRF
- Symptom: logout endpoint existed without explicit CSRF hardening requirement.
- Root cause: default logout setup focused on flow completion, not explicit hardening.
- Fix: enable firewall logout CSRF and use POST logout form with token.
- Prevention: for every state-changing UI endpoint, enforce method + CSRF by default.

## 2026-02-20 - Station visibility without station ownership
- Symptom: need user isolation while stations remain globally deduplicated.
- Root cause: domain model states users own receipts, not station entities.
- Fix: scope station repository reads through linked receipts owned by current user.
- Prevention: when ownership is indirect, enforce access perimeter via relationship queries.

## 2026-02-20 - Historical unowned receipts after ownership rollout
- Symptom: older receipts become invisible once owner filtering is enabled.
- Root cause: legacy rows have `owner_id = NULL`.
- Fix: provide `app:receipts:claim-unowned <email>` operational command.
- Prevention: include backfill/claim path when introducing ownership constraints.

## 2026-02-20 - Symfony 8 voter signatures and generics
- Symptom: static analysis errors on custom voters (missing generics, wrong method signature).
- Root cause: voters not aligned with Symfony 8 `Voter` generic/signature expectations.
- Fix: add `@extends Voter<string, string>` and include optional `?Vote $vote` arg in `voteOnAttribute`.
- Prevention: scaffold new voters from Symfony 8 signature template.

## 2026-02-20 - Functional tests without BrowserKit
- Symptom: functional suite failed with `Class Symfony\Component\BrowserKit\AbstractBrowser not found`.
- Root cause: project does not include `symfony/browser-kit`, so `WebTestCase::createClient()` is unavailable.
- Fix: implement functional HTTP tests with `KernelTestCase` + kernel `Request` handling.
- Prevention: if BrowserKit is not installed, avoid `WebTestCase` and test endpoints via kernel requests.

## 2026-02-20 - OIDC callback login requires explicit authenticator + firewall
- Symptom: programmatic login can fail or bind to wrong firewall when using generic `Security::login`.
- Root cause: passing only one string argument can be interpreted as authenticator name, not firewall.
- Fix: call `Security::login($user, LoginFormAuthenticator::class, 'main')`.
- Prevention: for programmatic UI login, always pass both authenticator class and firewall name explicitly.

## 2026-02-20 - Worker access must bypass user-scoped repository reads
- Symptom: async worker could not load station entities for background processing.
- Root cause: `StationRepository::get()` enforces current-user ownership scope, but Messenger workers have no authenticated user context.
- Fix: add explicit system-level read method (`getForSystem`) for internal jobs and use it in geocoding handler.
- Prevention: for background jobs, always use explicit internal repository methods rather than user-scoped read APIs.

## 2026-02-20 - Dotenv values with spaces must be quoted
- Symptom: app bootstrap/test commands failed parsing `.env` after geocoding User-Agent config.
- Root cause: unquoted env value containing spaces and parentheses.
- Fix: wrap `NOMINATIM_USER_AGENT` value in double quotes.
- Prevention: always quote `.env` values when they contain spaces.

## 2026-02-20 - Dev schema changes require explicit migration handover
- Symptom: runtime failed with missing geocoding columns after restart (`column ... does not exist`).
- Root cause: migration was executed in test flows but not applied on dev database before manual UI validation.
- Fix: run `make db-migrate` after schema changes and before user verification.
- Prevention: in task handover, always list required user commands and explicitly include DB migration when schema changed.

## 2026-02-21 - New Doctrine context requires explicit ORM mapping registration
- Symptom: integration tests failed with `MappingException` saying `ImportJobEntity` was not found in configured namespaces.
- Root cause: new `Import` entity namespace was created, but `config/packages/doctrine.yaml` mappings still listed only `Receipt`, `Station`, and `User`.
- Fix: add `Import` attribute mapping block in Doctrine ORM configuration.
- Prevention: each new bounded context with Doctrine entities must include mapping registration in the same change set.

## 2026-02-21 - File mime validation via `Assert\File` requires Mime component
- Symptom: functional API upload tests returned 500 with `You cannot guess the mime type as the Mime component is not installed`.
- Root cause: `Assert\File(mimeTypes: ...)` depends on `symfony/mime` for mime guessing.
- Fix: install `symfony/mime` and keep `Assert\File` validation.
- Prevention: when introducing `Assert\File` mime checks, request `symfony/mime` installation upfront during task implementation.

## 2026-02-21 - Symfony routes are not auto-listed in API Platform docs
- Symptom: `/api/imports` worked but was missing from `/api/docs`.
- Root cause: API Platform docs list API Platform operations by default; plain Symfony routes are excluded.
- Fix: add an OpenAPI factory decorator to register the upload path and schema explicitly.
- Prevention: for non-API Platform endpoints that must appear in docs, add/update OpenAPI decorator in the same ticket.

## 2026-02-21 - Async import workers must use system-level repository reads
- Symptom: Messenger handlers cannot rely on user-scoped repository methods.
- Root cause: worker context has no authenticated user token.
- Fix: use `ImportJobRepository::getForSystem()` in import async handlers.
- Prevention: for every worker/cron flow, explicitly use system-level read APIs and keep user-scoped methods for HTTP/UI flows only.

## 2026-02-21 - OCR provider errors must distinguish retryable vs permanent
- Symptom: import jobs can either need retry (provider outage) or immediate failure (invalid input/api key).
- Root cause: a single generic OCR exception type loses retry semantics required by Messenger.
- Fix: model OCR failures with explicit retryable/permanent mapping and persist clear failure reasons on import jobs.
- Prevention: for every external provider adapter, encode retry semantics in exceptions and keep handler behavior deterministic.

## 2026-02-21 - Parse output should include both issues and a validated command candidate
- Symptom: OCR data can be partially parsed, but downstream flows need to know if payload is directly usable.
- Root cause: parsing-only output without explicit command candidate forces duplicated validation logic later.
- Fix: return parsed draft with normalized fields, explicit issues list, and `creationPayload` only when required fields are complete.
- Prevention: for extraction pipelines, always separate `raw parsed data` from `validated command-ready payload`.

## 2026-02-21 - Duplicate import must short-circuit before OCR
- Symptom: re-uploading the same receipt can trigger full OCR/parsing again and risks duplicate receipt creation.
- Root cause: async handler lacked deterministic duplicate gate before expensive processing.
- Fix: compare owner + file checksum upfront, mark job as `duplicate`, persist structured payload (`duplicateOfImportJobId`, fingerprint), and stop processing.
- Prevention: for async ingest pipelines, enforce idempotency checks before calling external providers.

## 2026-02-21 - API Platform write operations expect JSON-LD by default
- Symptom: manual finalize endpoint returned `415 Unsupported Media Type` with `application/json`.
- Root cause: API Platform content negotiation for resource operations defaults to `application/ld+json`.
- Fix: call endpoint with `Content-Type: application/ld+json` (or widen operation formats explicitly if needed).
- Prevention: for new API Platform POST/PATCH operations, validate accepted content types in functional tests.

## 2026-02-21 - Integration and functional suites must run sequentially (shared test DB lifecycle)
- Symptom: intermittent integration failures (`database "app_test" does not exist`) when running multiple suites concurrently.
- Root cause: both suites execute drop/create/migrate on the same test database.
- Fix: run `make phpunit-integration` and `make phpunit-functional` sequentially.
- Prevention: avoid parallel execution of suites that reset shared database state.

## 2026-02-21 - API Platform metadata compatibility: use `openapi` operation object
- Symptom: static analysis failed with unknown `openapiContext` argument on `ApiPlatform\Metadata\Post`.
- Root cause: project API Platform metadata version expects `openapi` (`ApiPlatform\OpenApi\Model\Operation`) instead of legacy context argument.
- Fix: define upload docs with `openapi: new Operation(...)` on the resource operation.
- Prevention: when adding custom docs on metadata operations, align constructor args with installed API Platform version.

## 2026-02-21 - Access control assertions need real routed endpoints
- Symptom: security tests against non-existing URLs returned 404 before access checks, hiding role policy behavior.
- Root cause: firewall access checks are not a substitute for route existence in functional assertions.
- Fix: add minimal routed admin probes (`/api/admin/ping`, `/ui/admin`) and assert role outcomes on those routes.
- Prevention: when testing security boundaries, target concrete routes under the protected prefix.

## 2026-02-21 - API Platform custom resources with PATCH/DELETE need explicit `read: false` when not entity-backed
- Symptom: admin PATCH operations returned 404 from `ReadProvider` before reaching custom processors.
- Root cause: API Platform attempted default pre-read on DTO resources without matching entity provider.
- Fix: set `read: false` on PATCH/DELETE operations and load domain objects in custom processors.
- Prevention: for non-entity API resources driven by custom processors, explicitly choose `read` behavior per operation.

## 2026-02-21 - UI layer must not depend on Infrastructure entities
- Symptom: PHPStan architecture rule (`phpat`) failed when an API processor referenced `UserEntity`.
- Root cause: ownership validation was implemented in UI state processor using Doctrine entity class directly.
- Fix: move owner existence check behind `VehicleRepository` application contract and keep UI depending only on application/domain abstractions.
- Prevention: when adding validation in UI/API state classes, route cross-layer checks through repository/service interfaces instead of infrastructure entity imports.

## Standing Decisions
- Use integer-based monetary and quantity units in domain/storage.
- Keep feature-first DDD foldering (`Receipt/*`, `Station/*`, etc.).
- Prefer async for external/slow operations (geocoding, OCR/import).
- Sprint and backlog source of truth lives in `/docs/backlog`.
