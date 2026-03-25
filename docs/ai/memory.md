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

## 2026-02-21 - Import file lifecycle after OCR review flow
- Symptom: uploaded import files accumulated even when no longer useful.
- Root cause: files were persisted for async OCR but not cleaned after terminal business outcomes.
- Fix: delete stored file when import becomes `duplicate` or is finalized to `processed`; keep file for `failed`/`needs_review` to preserve retry/review capability.
- Prevention: define file retention behavior per import status explicitly whenever import workflow changes.

## 2026-02-21 - API Platform metadata compatibility: use `openapi` operation object
- Symptom: static analysis failed with unknown `openapiContext` argument on `ApiPlatform\Metadata\Post`.
- Root cause: project API Platform metadata version expects `openapi` (`ApiPlatform\OpenApi\Model\Operation`) instead of legacy context argument.
- Fix: define upload docs with `openapi: new Operation(...)` on the resource operation.
- Prevention: when adding custom docs on metadata operations, align constructor args with installed API Platform version.

## 2026-03-03 - Audit target_id length must be bounded on authentication failures
- Symptom: overlong user-supplied email on login failure could trigger SQL error and return 500 instead of auth error.
- Root cause: raw credential identifier was stored into `admin_audit_logs.target_id` (`VARCHAR(120)`) without truncation.
- Fix: normalize (`trim` + lowercase), fallback to `anonymous`, and truncate to 120 chars before audit writes in API and UI login failure paths.
- Prevention: for any audit field backed by fixed-length DB columns, normalize and bound untrusted input at the call site.

## 2026-03-03 - Correlation IDs from headers must be bounded before audit persistence
- Symptom: oversized `X-Correlation-Id` / `X-Request-Id` could make login failure auditing crash and return 500.
- Root cause: `admin_audit_logs.correlation_id` is `VARCHAR(80)` but request/header correlation IDs were not length-bounded.
- Fix: truncate correlation IDs to 80 chars in request correlation subscriber/context and enforce same bound in Doctrine audit trail before persist.
- Prevention: when correlation/request IDs are user-controlled headers, cap them to DB-safe length at ingress and at persistence boundaries.

## 2026-03-03 - Last-admin guard must apply only to active target admins
- Symptom: demoting an inactive admin could be blocked when there was only one active admin account.
- Root cause: last-admin guard checked global active admin count but not whether the target being demoted was active.
- Fix: enforce last-active-admin protection only when removing `ROLE_ADMIN` from an active admin target.
- Prevention: for cardinality guards on "active" entities, always include target state (`active/inactive`) in the decision predicate.

## 2026-03-25 - Import review UI must mirror finalize handler line capabilities
- Symptom: import review pages showed only one fuel line even when OCR payloads contained several, making multi-line receipts look incomplete in front/admin.
- Root cause: web controllers/templates read only `firstLine` while `FinalizeImportJobHandler` already accepted multiple `CreateReceiptLineCommand`s.
- Fix: expose `reviewLines`, post `lines[...]` arrays from both review forms, and keep legacy single-line fallback only for transition safety.
- Prevention: when a handler supports collections (`lines`, items, rows), verify the paired UI/controller path renders and submits the full collection instead of only the first element.

## 2026-03-25 - Importmap removals can break cached frontend runtime
- Symptom: row-click navigation and Turbo frame actions stopped working after frontend asset cleanup, even though templates still had the right `data-controller` / `data-turbo-frame` wiring.
- Root cause: browsers could keep an older cached `app.js` that still imported removed importmap modules, which broke JS bootstrap early and disabled Turbo/Stimulus behavior across the page.
- Fix: keep compatibility for removed modules during the transition or version asset changes explicitly, then validate runtime behaviors like row-link navigation and frame loading after a hard refresh.
- Prevention: when removing importmap entries, assume old cached entrypoints may still exist client-side; verify JS boot-critical interactions and phase removals carefully.

## 2026-03-25 - OCR retry budget must not depend on Messenger redelivery count
- Symptom: import jobs could exhaust OCR retries too early after unrelated redeliveries caused by locator/parser/runtime failures.
- Root cause: OCR retry logic reused `RedeliveryStamp::getRetryCountFromEnvelope()`, which counts all transport redeliveries, not only OCR provider retryable failures.
- Fix: persist a dedicated `ocrRetryCount` on `import_jobs` and increment it only when an `OcrProviderException` is explicitly retryable.
- Prevention: when a retry budget belongs to a specific failure class, store and manage that counter in domain state instead of reusing transport-level retry metadata.

## 2026-03-13 - OCR.Space 1MB limit should be handled before provider call
- Symptom: imports could fail with provider-side validation when image uploads exceeded OCR.Space size limit.
- Root cause: OCR adapter sent original stored files directly without pre-upload size normalization.
- Fix: add local image optimization (resize + JPEG quality loop) for oversized JPEG/PNG/WEBP, upload temporary optimized copy only, keep original file unchanged.
- Prevention: for external OCR/AI providers with strict payload limits, enforce adapter-level normalization and explicit target-size thresholds before HTTP calls.

## 2026-03-03 - Analytics export links must propagate active dashboard filters
- Symptom: `/ui/analytics` with `vehicle_id` filter could export receipts from other vehicles.
- Root cause: analytics dashboard export params omitted `vehicle_id`, and export-side filtering did not apply vehicle constraint.
- Fix: propagate `vehicle_id` in analytics export query params and apply vehicle filter in export repository queries/metadata.
- Prevention: when dashboard KPIs and exports share a filter model, keep a single explicit propagation list and assert parity in functional tests.

## 2026-02-21 - DBAL datetime parameter type must match immutable values
- Symptom: analytics projection refresh crashed in integration tests with `Could not convert PHP value of type DateTimeImmutable to type DateTimeType`.
- Root cause: DBAL statement parameter types were declared as `datetime` while values were `DateTimeImmutable`.
- Fix: use `datetime_immutable` for DBAL parameter type mapping in projection state upserts.
- Prevention: when binding `DateTimeImmutable` in DBAL queries, always declare `datetime_immutable` explicitly.

## 2026-02-21 - PostgreSQL nullable filter params need explicit safe casting
- Symptom: analytics KPI endpoints failed with SQLSTATE errors (`indeterminate datatype` / `invalid input syntax for type uuid: ""`) when optional filters were absent.
- Root cause: SQL expressions attempted direct casts on nullable/empty-string parameters inside `OR` predicates.
- Fix: normalize optional filters to empty string and cast with `NULLIF(:param, '')` (UUID) or explicit `CAST(... AS date)` for date filters.
- Prevention: for optional DBAL filters on PostgreSQL, avoid direct casting of raw nullable params; normalize inputs and cast only sanitized values.

## 2026-02-21 - Deci-cents fuel price formatting in UI
- Symptom: analytics dashboard tests expected wrong average EUR/L display.
- Root cause: `averagePriceDeciCentsPerLiter` unit was interpreted as cents instead of deci-cents in assertions.
- Fix: convert deci-cents per liter to EUR/L with `/ 1000` and align tests/UI labels to that unit.
- Prevention: when exposing derived pricing metrics, keep explicit unit labels in DTO/UI and validate conversion formula in functional tests.

## 2026-03-01 - Admin layers must not depend on infrastructure entities
- Symptom: architecture/static checks failed after adding BO user management (`Admin\Application`/`Admin\UI` referenced `UserEntity`).
- Root cause: cross-layer leak from Doctrine entity (Infrastructure) into Application/UI contracts.
- Fix: introduce application-level `AdminUserRecord` + `AdminUserRepository` interface and map Doctrine entities only in infrastructure implementation.
- Prevention: for new admin features, keep DTO/contracts in Application and bind infrastructure repositories via interfaces in DI.

## 2026-02-21 - PhpSpreadsheet requires GD extension in app image
- Symptom: `composer require phpoffice/phpspreadsheet` failed with missing `ext-gd`.
- Root cause: project Docker image did not install the PHP GD extension required by PhpSpreadsheet.
- Fix: add `gd` to `install-php-extensions` in `Dockerfile` and rebuild the app image before installing dependency.
- Prevention: when adding spreadsheet/image-capable libraries, verify required PHP extensions against container image in the same change set.

## 2026-02-21 - Access control assertions need real routed endpoints
- Symptom: security tests against non-existing URLs returned 404 before access checks, hiding role policy behavior.
- Root cause: firewall access checks are not a substitute for route existence in functional assertions.

## 2026-03-04 - ZIP upload must validate temporary path before `ZipArchive::open`
- Symptom: ZIP import could crash with `ZipArchive::open(): Argument #1 ($filename) must not be empty`.
- Root cause: bulk upload flow called `ZipArchive::open($uploadedFile->getPathname())` without guarding invalid uploads or missing temp files.
- Fix: reject unusable uploads early (`isValid`, non-empty readable temp pathname) and return a controlled rejected item instead of throwing.
- Prevention: for every `UploadedFile` processing path, validate upload validity + filesystem availability before any low-level file/zip operation.
- Fix: add minimal routed admin probes (`/api/admin/ping`, `/ui/admin`) and assert role outcomes on those routes.
- Prevention: when testing security boundaries, target concrete routes under the protected prefix.

## 2026-02-21 - API Platform custom resources with PATCH/DELETE need explicit `read: false` when not entity-backed
- Symptom: admin PATCH operations returned 404 from `ReadProvider` before reaching custom processors.
- Root cause: API Platform attempted default pre-read on DTO resources without matching entity provider.
- Fix: set `read: false` on PATCH/DELETE operations and load domain objects in custom processors.
- Prevention: for non-entity API resources driven by custom processors, explicitly choose `read` behavior per operation.

## 2026-02-21 - Import review payload can be nested under `parsedDraft.creationPayload`
- Symptom: user UI had no finalize action on `needs_review` imports even when extracted data looked complete.
- Root cause: UI/controller logic only checked root `creationPayload`, while async parser payload stores command-ready data under `parsedDraft.creationPayload`.
- Fix: support both payload shapes in UI action visibility and finalize handler fallback path resolution.
- Prevention: when reading import payload fields, always account for both legacy/root and nested parser structures.

## 2026-02-21 - UI layer must not depend on Infrastructure entities
- Symptom: PHPStan architecture rule (`phpat`) failed when an API processor referenced `UserEntity`.
- Root cause: ownership validation was implemented in UI state processor using Doctrine entity class directly.
- Fix: move owner existence check behind `VehicleRepository` application contract and keep UI depending only on application/domain abstractions.
- Prevention: when adding validation in UI/API state classes, route cross-layer checks through repository/service interfaces instead of infrastructure entity imports.

## 2026-02-21 - Admin finalize of import jobs must preserve original receipt owner
- Symptom: finalizing a `needs_review` import from admin context can create receipts owned by admin instead of import owner.
- Root cause: receipt persistence defaulted to current authenticated user from token storage.
- Fix: propagate owner context from import job through finalize/create-receipt flow and persist via `ReceiptRepository::saveForOwner`.
- Prevention: for system/admin workflows acting on user-owned resources, pass explicit owner context instead of relying on current session user.

## 2026-02-21 - Admin mutation flows require immutable audit records with correlation id
- Symptom: troubleshooting admin-side mutations across API/UI is hard without centralized trace events.
- Root cause: critical admin actions lacked persisted actor/target/change metadata and request correlation.
- Fix: add `admin_audit_logs` immutable store and record actions with actor, target, diff summary, timestamp, and correlation id.
- Prevention: every new admin mutation endpoint/controller must append an audit event through `AdminAuditTrail`.

## 2026-02-21 - Test container removes unused private aliases
- Symptom: integration test failed to fetch a newly added repository interface alias from `self::getContainer()`.
- Root cause: Symfony test container can remove/in-line private services not referenced by runtime wiring.
- Fix: instantiate the repository directly with `EntityManagerInterface` in integration tests for new skeleton contexts until a runtime consumer exists.
- Prevention: in early-stage context skeleton tickets, avoid assuming interface aliases are retrievable from test container before first real consumer is wired.

## 2026-02-21 - UI layer user-context lookup must not depend on infrastructure entities
- Symptom: architecture guard (`phpat`) failed when maintenance API state classes imported `UserEntity`.
- Root cause: authenticated user id resolution was implemented directly in UI layer via Doctrine entity type checks.
- Fix: introduce `Shared\Application\Security\AuthenticatedUserIdProvider` with infrastructure implementation and inject the abstraction in UI/API state classes.
- Prevention: user/session lookup in UI/Application should use dedicated abstractions, never infrastructure entity classes directly.

## 2026-02-21 - Twig dashboard must match read-model fields exactly
- Symptom: maintenance dashboard returned HTTP 500 with Twig runtime error about missing `currencyCode` on variance model.
- Root cause: template assumed a field that does not exist on `MaintenanceCostVariance` (`plannedCostCents`, `actualCostCents`, `varianceCents` only).
- Fix: render KPI currency explicitly (`EUR`) and keep template aligned with actual DTO/read-model properties.
- Prevention: for new UI pages bound to read models, validate template field names against constructor/public properties and cover with functional UI assertion.

## 2026-02-21 - Functional tests should not fetch CSRF token from container without request session
- Symptom: functional test failed with `SessionNotFoundException` when generating CSRF token in helper via container token manager.
- Root cause: token manager session storage needs an active request session; test helper called it outside request lifecycle.
- Fix: extract CSRF token directly from rendered form HTML and post it back as browser would do.
- Prevention: in functional web tests, prefer CSRF extraction from response content over direct token manager calls.

## Standing Decisions
- Use integer-based monetary and quantity units in domain/storage.
- Keep feature-first DDD foldering (`Receipt/*`, `Station/*`, etc.).
- Prefer async for external/slow operations (geocoding, OCR/import).
- Sprint and backlog source of truth lives in `/docs/backlog`.

## 2026-02-22 - Analytics optional filters must use dynamic SQL for index usage
- Symptom: KPI endpoints can degrade with larger datasets when optional filters are encoded as `(:param = '' OR column = ...)` predicates.
- Root cause: PostgreSQL query planner often cannot use selective indexes efficiently with broad optional `OR` predicates.
- Fix: build strict dynamic `WHERE` clauses that include only active filters (`owner`, `vehicle`, `station`, `fuel`, `from/to`).
- Prevention: for read-heavy analytics endpoints, avoid optional `OR` filter patterns; prefer dynamic predicates + matching composite indexes.

## 2026-02-22 - BrowserKit preferred for session-driven functional tests
- Symptom: manual `KernelTestCase` + handcrafted requests make UI/session/CSRF functional tests verbose and fragile.
- Root cause: without BrowserKit client, cookie/session handling was duplicated in many tests.
- Fix: install `symfony/browser-kit` and migrate priority UI suites to `WebTestCase::createClient()`.
- Prevention: for authenticated UI/session flows, default to BrowserKit functional client instead of manual kernel request plumbing.

## 2026-03-01 - SigNoZ standalone container needs explicit ClickHouse backend wiring
- Symptom: SigNoZ UI returned `internal` with message `failed to get tbl statement`.
- Root cause: `signoz/signoz` container was running without reachable ClickHouse (`dial tcp [::1]:9000: connect: connection refused`), then with default ClickHouse user network-disabled.
- Fix: add dedicated `clickhouse` service in observability profile, configure `SIGNOZ_TELEMETRYSTORE_CLICKHOUSE_DSN` with explicit credentials, align `CLICKHOUSE_USER`/`CLICKHOUSE_PASSWORD`, and restart observability stack.
- Prevention: for SigNoZ local setup, always provision ClickHouse + DSN + credentials together; treat `failed to get tbl statement` as ClickHouse connectivity/auth first.

## 2026-03-03 - OTel env booleans must use true/false (not 1/0)
- Symptom: telemetry looked enabled but instrumentation/export behaved unexpectedly, with warning `Invalid boolean value "1" interpreted as "false"`.
- Root cause: OpenTelemetry PHP SDK does not accept `1/0` for boolean env vars like `OTEL_PHP_AUTOLOAD_ENABLED` and `OTEL_SDK_DISABLED`.
- Fix: use explicit boolean strings (`true`/`false`) in env files.
- Prevention: for all OTel boolean env vars, never use numeric booleans; enforce textual booleans in `.env*` and docs.

## 2026-03-04 - OCR outage bursts require provider-level circuit breaker
- Symptom: multiple imports queued during OCR.Space instability kept failing/retrying and amplified provider pressure.
- Root cause: retry/backoff alone still sent every job to the provider, with no shared short-lived "pause" state.
- Fix: add cache-backed circuit breaker in `OcrSpaceOcrProvider` (failure counter + open cooldown); when open, fail fast with retryable exception so Messenger retries later without external call.
- Prevention: for external providers used by async workers, combine retry/backoff with a shared circuit breaker to avoid cascade failures.

## 2026-03-04 - Retry exhaustion fallback should preserve manual import recovery
- Symptom: after transient OCR outages, imports ended in `failed` and required admin-style retry loops instead of user recovery.
- Root cause: exhausted retry path marked jobs as permanently failed without creating reviewable payload.
- Fix: on retry exhaustion, mark job `needs_review` with explicit fallback payload/issue (`OCR provider unavailable after retries`) and keep manual finalize path available.
- Prevention: for ingest pipelines, prefer degradable `needs_review` fallback over hard-fail when data can still be entered manually.

## 2026-03-04 - Retry exhaustion threshold must match Messenger max retries
- Symptom: OCR fallback to `needs_review` never appeared in UI/BO despite repeated transient failures.
- Root cause: handler threshold used 5 attempts while Messenger transport `async.retry_strategy.max_retries` was 3, so fallback branch was unreachable in production flow.
- Fix: align handler threshold to 3 and update tests to use `RedeliveryStamp(3)`.
- Prevention: when implementing retry-based state transitions, always keep handler thresholds aligned with transport retry strategy.

## 2026-03-05 - Login hardening needs explicit rate limiting on both UI and API
- Symptom: auth flows had no explicit brute-force limits, allowing repeated password attempts without backoff.
- Root cause: login throttling was not configured on the main firewall and `/api/login` had no dedicated limiter.
- Fix: enable `login_throttling` on `main` firewall and add `api_login` rate limiter with controlled `429` + `Retry-After` in `ApiLoginController`.
- Prevention: for each authentication entry point (UI/API), enforce an explicit rate-limit policy and add functional coverage for throttling behavior.

## 2026-03-05 - FrankenPHP `hot_reload` requires local Mercure hub in same server config
- Symptom: Caddy/FrankenPHP config validation failed with `unable to enable hot reloading: no Mercure hub configured`.
- Root cause: `hot_reload` depends on a Mercure hub being configured on the same FrankenPHP server.
- Fix: add `mercure { ... }` block to app Caddyfile and validate with `frankenphp validate --adapter caddyfile`.
- Prevention: when enabling `hot_reload`, always configure Mercure in the same Caddy server block.

## 2026-03-05 - Abuse hardening should return explicit 4xx on oversized and high-frequency API auth/upload inputs
- Symptom: sensitive endpoints could rely only on infrastructure limits, making abuse controls less explicit at application level.
- Root cause: no explicit payload-size check on `/api/login` and no dedicated limiter on API upload endpoints.
- Fix: add login JSON size guard (`413`) and dedicated rate-limiters for `/api/imports` + `/api/imports/bulk` with `429` and `Retry-After`.
- Prevention: for critical ingress endpoints, define both size and frequency constraints in app logic and cover with functional tests.

## 2026-03-05 - ZIP import hardening must validate entry paths and stream size before OCR handoff
- Symptom: ZIP imports could accept suspicious entry paths or spend resources copying oversized entries before business validation.
- Root cause: archive entry path checks and copy-time size guards were incomplete in bulk upload processing.
- Fix: reject dangerous entry paths (`../`, absolute, control chars), cap ZIP entry count, enforce streamed per-entry size limit, and require mime/extension consistency.
- Prevention: for archive-based uploads, validate path safety and resource limits before creating temp files/jobs.

## 2026-03-05 - FrankenPHP hot reload is not fully reliable for all Twig/UI updates in local setup
- Symptom: UI text/template change was not visible until manual `make restart-app`.
- Root cause: despite `hot_reload` + worker watch, local refresh behavior can still miss some template updates depending on runtime/browser state.
- Fix: keep `make restart-app` as `Recommended` in handover for Twig/UI changes.
- Prevention: do not report `Not needed` by default for Twig/UI changes; prefer conservative restart guidance.

## 2026-03-05 - `restart-app` should include readiness wait to avoid transient "site inaccessible"
- Symptom: right after restart, browser can show temporary connection errors for a few seconds.
- Root cause: command returned before web endpoint became ready.
- Fix: chain `wait-app` after `restart-app` and poll `/ui/login` from inside app container until ready (or timeout).
- Prevention: keep restart commands blocking until service readiness is confirmed.

## 2026-03-05 - Security hardening sprint needs dedicated observability runbook, not only generic alerts
- Symptom: security events were logged but incident triage lacked a single operational checklist focused on auth/upload/admin abuse.
- Root cause: observability docs existed, but security-specific thresholds and response flow were scattered.
- Fix: add `docs/ops/security-observability-runbook.md` with alert matrix, query starters, triage steps, and local verification flow.
- Prevention: for security sprint deliverables, always add a dedicated runbook artifact with explicit triggers and response actions.

## 2026-03-13 - Import upload limits should be split by file family for OCR workflows
- Symptom: valid receipt images >1MB were rejected at upload stage before OCR auto-compression could run.
- Root cause: a single global 1MB upload limit was enforced for all file types in API and bulk upload validators.
- Fix: set upload limits to 8MB for images (JPEG/PNG/WEBP) and keep 1MB for PDF, with explicit user-facing messaging.
- Prevention: when provider constraints differ by media type, enforce differentiated limits in upload validation instead of one global cap.

## 2026-03-13 - GD pre-processing must respect PHP memory budget in worker context
- Symptom: async worker crashed with `Allowed memory size exhausted` during OCR image optimization.
- Root cause: oversized image resampling (`imagecreatetruecolor`) was attempted without checking available runtime memory budget.
- Fix: add pre-allocation guards based on `memory_limit`, current memory usage, pixel estimation, and safety margins; skip risky GD processing and fail with deterministic business error instead of fatal crash.
- Prevention: any image transformation in workers must include memory-budget checks before creating GD resources.

## 2026-03-13 - OCR parser must be validated against real noisy payloads, not only idealized fixtures
- Symptom: imports from user-provided receipts had frequent missing fields despite data being present in OCR text.
- Root cause: parser heuristics were tuned on clean fixtures and failed on compact/single-line payloads (city over-capture, station/street mis-detection, fuel patterns like `Excellium 98`).
- Fix: harden parser with real-sample-driven rules (postal pattern `L-xxxx`, city sanitation, stricter street candidate logic, noisy quantity/unit-price extraction) and add dedicated regression tests.
- Prevention: for OCR parsing changes, always include at least one regression fixture copied from production-like payloads.
