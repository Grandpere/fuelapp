# Sprint 02 - Execution plan (day-by-day)

## Sprint objective
Add asynchronous station geocoding with reliable status tracking and production-safe retry behavior.

## Assumptions
- Sprint 01 is merged and stable (auth + ownership already enforced).
- Messenger transport is available and worker commands are already part of local workflow.
- Free provider first (Nominatim), abstracted behind an interface.

## Recommended sequence

### Day 1 - Domain contract and persistence
- Ticket: `SP2-001`
- Work:
  - Define geocoding contract (`GeocoderInterface`).
  - Add station geocoding fields/status (`pending`, `success`, `failed`) + timestamps + last error.
  - Create migration and update mapping/tests fixtures.
- Exit criteria:
  - Schema migrated successfully.
  - Existing station flows still work.

### Day 2 - Async message and handler skeleton
- Ticket: `SP2-002`
- Work:
  - Add `GeocodeStationAddressMessage` and handler.
  - Configure routing to async transport.
  - Add retry + failure transport baseline.
- Exit criteria:
  - Message dispatch and worker consumption validated.
  - Status transitions visible in DB/logs.

### Day 3 - Nominatim adapter implementation
- Ticket: `SP2-003`
- Work:
  - Implement provider adapter with explicit User-Agent.
  - Add request throttling strategy (1 req/s policy-safe).
  - Normalize provider response to internal DTO.
- Exit criteria:
  - Valid addresses resolve.
  - Provider/network errors mapped cleanly.

### Day 4 - Triggering strategy
- Ticket: `SP2-004`
- Work:
  - Dispatch geocoding on station create.
  - Redispatch only when address identity changes.
  - Keep idempotent guard to avoid duplicate in-flight jobs.
- Exit criteria:
  - Create/change address triggers geocode.
  - No unnecessary requeue on unchanged data.

### Day 5 - Observability and retries
- Ticket: `SP2-005`
- Work:
  - Structured logs with station id, attempt, status, provider outcome.
  - Persist failure reason.
  - Document retry/backoff/dead-letter policy.
- Exit criteria:
  - Failed runs diagnosable quickly.
  - Retry policy deterministic and documented.

### Day 6 - Automated tests
- Ticket: `SP2-006`
- Work:
  - Unit tests: adapter mapping/errors.
  - Integration tests: handler + status transitions.
  - Cases: no result, timeout, malformed response, rate-limit.
- Exit criteria:
  - Test matrix covers happy + failure paths.

### Day 7 - API/UI exposure of status
- Linked to `SP2-001`/`SP2-005`
- Work:
  - Expose station geocoding status in API output.
  - Optional UI badge in station-related views.
- Exit criteria:
  - Current status visible to client/operator.

### Day 8 - Operational hardening
- Work:
  - Verify worker restart behavior.
  - Ensure poison-message behavior goes to failure transport.
  - Add make target(s) if missing for worker + failed retries.
- Exit criteria:
  - Local ops flow documented and tested.

### Day 9 - Buffer and review
- Work:
  - Resolve code-review findings.
  - Tighten edge cases and static analysis warnings.
  - Final quality run.

### Day 10 - Release prep
- Work:
  - Final regression.
  - Update docs and sprint notes.
  - Mark follow-ups for Sprint 03 import pipeline.

## Pull request strategy
- PR1: `SP2-001` (contract + schema)
- PR2: `SP2-002` + `SP2-004` (async pipeline + trigger)
- PR3: `SP2-003` (provider adapter)
- PR4: `SP2-005` + `SP2-006` (observability + tests)
- PR5: status exposure/docs

## Mandatory checks per PR
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- Manual async smoke check:
  - create station -> status `pending`
  - run worker -> status `success` or `failed`

## Risk log (Sprint 2)
- Risk: provider usage policy violations (rate limits/user-agent).
  - Mitigation: throttle + clear adapter config + cache strategy.
- Risk: duplicate queue jobs for same station.
  - Mitigation: idempotency guard at handler level.
- Risk: async failures hidden from operators.
  - Mitigation: persisted failure reason + explicit logs + failure transport.

## Definition of done (Sprint 2)
- All `SP2-*` tickets marked done.
- Station geocoding fully async and non-blocking.
- Status lifecycle visible and reliable.
- Retries/failures operationally manageable.
- Tests and docs updated; CI green.
