# Local Observability (SigNoZ baseline)

## Overview
This project uses a local SigNoZ baseline for Sprint 10 observability.
It is isolated behind Docker profile `observability`.

## Start/Stop
- Start: `make observability-up`
- Stop: `make observability-down`
- Logs: `make observability-logs`

## URLs and ports
- SigNoZ UI: `http://localhost:${SIGNOZ_PORT:-3301}`
- OTLP gRPC endpoint: `localhost:${SIGNOZ_OTLP_GRPC_PORT:-14317}`
- OTLP HTTP endpoint: `http://localhost:${SIGNOZ_OTLP_HTTP_PORT:-14318}`

## First checks
1. Run `make observability-up`.
2. Restart app container once to load OTEL env vars: `make restart-app`.
3. Open SigNoZ UI on `http://localhost:3301` (default).
4. Confirm container health with `make ps`.
5. Generate traffic:
   - open front pages (`/ui/*`),
   - run one async flow (example: import or geocoding),
   - optionally consume jobs with `make messenger-consume-async`.
6. In SigNoZ, check:
   - Traces for service `fuelapp`,
   - metrics related to request/job activity.

## Signals available in this baseline
- Logs: structured JSON on app output with `correlation_id`, `request_id`, `user_id`, `user_email`, `job_id`, `http_method`, `http_path`, `http_route`.
- Traces/metrics: OTLP endpoints are exposed by SigNoZ, ready for incremental instrumentation.
- Correlation propagation: `X-Correlation-Id` is accepted on HTTP requests, returned on responses, and propagated through Messenger jobs.

## Standard diagnostics flow
1. Reproduce the issue and capture timestamp + endpoint + user.
2. Capture `X-Correlation-Id` from the HTTP response or browser network tab.
3. In SigNoZ Logs, filter by `correlation_id` to get the full request + async chain.
4. If async involved, check Messenger state:
   - queue consumer status with `make ps`,
   - failed messages with `make messenger-failed-show`.
5. If DB- or migration-related symptoms appear, verify schema state with `make db-migrate` and recheck logs.

## Common query patterns (SigNoZ logs)
- Single request/job chain:
  - filter: `correlation_id = "<uuid>"`
- User-scoped issue:
  - filter: `user_id = "<uuid>"` or `user_email = "<email>"`
- Route-level failures:
  - filter: `http_route = "<route_name>"` and `severity >= ERROR`
- Job failures:
  - filter: `job_id != ""` and `severity >= ERROR`

## Operational commands (app side)
- Start/stop observability:
  - `make observability-up`
  - `make observability-down`
- Follow SigNoZ service logs:
  - `make observability-logs`
- Async diagnostics:
  - `make messenger-consume-async`
  - `make messenger-failed-show`
  - `make messenger-failed-retry-all`

## Troubleshooting
- SigNoZ UI not reachable:
  1. Confirm service is running: `make ps`.
  2. Check service logs: `make observability-logs`.
  3. Ensure `SIGNOZ_PORT` is free on host.
- Logs visible in app stdout but not in SigNoZ:
  1. Validate OTLP port mapping in `resources/docker/compose.yml`.
  2. Confirm app telemetry export target matches `SIGNOZ_OTLP_*` endpoints.
- Correlation ID missing:
  1. Check response headers include `X-Correlation-Id`.
  2. Verify logs contain `correlation_id` field.
  3. Verify messenger middleware is enabled on `messenger.bus.default`.

## Local vs production notes
- Local:
  - single-node SigNoZ baseline for speed and low cost.
  - permissive retention acceptable for dev.
- Production target:
  - managed retention policy, auth/SSO, backups, and alerting rules.
  - capacity planning (ingest volume, storage, cardinality control).
  - hardening of PII policy in logs (minimize sensitive fields where needed).
- Decision follow-up:
  - modular stack evaluation remains tracked in `SP10-004`.
