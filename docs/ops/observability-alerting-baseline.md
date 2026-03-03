# SigNoZ Alerting Baseline (Local -> Prod-ready template)

## Objective
Provide a minimal, low-noise alert baseline that detects real incidents early:
- availability regressions,
- latency degradation,
- telemetry pipeline failure,
- queue pressure.

## Preconditions
- Stack running: `make observability-up`
- Telemetry present (traces/logs) from service `fuelapp`
- SigNoZ UI available on `http://localhost:3301`

## Alert 1 - HTTP 5xx rate spike
- Signal: traces/request errors for service `fuelapp`
- Suggested threshold: `5xx_rate > 2%` over `5m`
- Severity: high
- Why: catches user-facing outages quickly.
- First response:
  1. Check top failing routes in last 15 minutes.
  2. Correlate with recent deploy/config/migration changes.
  3. Check app logs filtered by `http_route` and `severity >= ERROR`.

## Alert 2 - p95 latency degradation
- Signal: request duration p95 on key endpoints
- Suggested threshold: `p95 > 1.5s` over `10m`
- Severity: medium/high (by endpoint criticality)
- Why: catches performance regressions before hard failure.
- First response:
  1. Identify slowest routes and service dependencies.
  2. Check DB latency / slow queries / lock contention.
  3. Check resource pressure (CPU/memory) and backlog.

## Alert 3 - Telemetry ingestion pipeline error
- Signal: `otel-collector` error logs or exporter failures
- Suggested threshold: `error count > 0` over `5m`
- Severity: high (observability blind spot)
- Why: avoids losing observability during incidents.
- First response:
  1. `make observability-logs` and inspect `otel-collector` + `clickhouse`.
  2. Validate DSN and network reachability.
  3. Verify migration check status after upgrades.

## Alert 4 - Queue backlog guardrail (optional, recommended)
- Signal: RabbitMQ queue depth / consumer lag
- Suggested threshold: project-specific backlog > N for `10m`
- Severity: medium
- Why: catches async degradation before user-visible delays escalate.
- First response:
  1. Check consumer process health.
  2. Inspect failed messages and retry strategy.
  3. Temporarily scale consumers if needed.

## SigNoZ implementation checklist
- [ ] Create alert rule folder: `fuelapp/local-baseline`
- [ ] Add 3 mandatory alerts (5xx, p95, ingestion error)
- [ ] Add optional queue backlog alert
- [ ] Define consistent naming:
  - `fuelapp/http/5xx-rate`
  - `fuelapp/http/p95-latency`
  - `fuelapp/otel/ingestion-error`
  - `fuelapp/queue/backlog`
- [ ] Attach runbook URL and owner field to each rule
- [ ] Test each alert with a controlled failure simulation

## Runbook links
- Local observability guide: `docs/ops/observability-local-signoz.md`
- Stack decision log: `docs/ops/observability-stack-decision.md`

## Production notes
- Replace local thresholds with SLO-derived thresholds.
- Route notifications to on-call channel.
- Add maintenance windows/silences for planned operations.
