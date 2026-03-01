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
2. Open SigNoZ UI on `http://localhost:3301` (default).
3. Confirm container health with `make ps`.

## Notes
- This baseline focuses on local stack availability first.
- Structured logs + correlation and richer telemetry wiring are tracked in `SP10-002`.
- Migration evaluation toward modular stack is tracked in `SP10-004`.
