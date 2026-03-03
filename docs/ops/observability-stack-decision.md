# Observability Stack Decision (Sprint 10)

## Decision summary
- Keep SigNoZ as default stack for current project phase.
- Do not migrate now to modular `OTel Collector + Loki + Tempo + Prometheus + Grafana`.
- Re-evaluate migration only when production constraints require explicit capabilities not reasonably covered by SigNoZ.

## Comparison matrix

| Criterion | SigNoZ baseline | Modular stack (OTel/Loki/Tempo/Prom/Grafana) | Decision impact |
| --- | --- | --- | --- |
| Local setup speed | Very fast (single service profile) | Higher setup effort (multiple services + wiring) | Favors SigNoZ now |
| Operational complexity | Lower | Higher (multi-component operations) | Favors SigNoZ now |
| Team cognitive load | Lower for current team size | Higher (more moving parts) | Favors SigNoZ now |
| Logs/metrics/traces coverage | Sufficient for current scope | Excellent, more granular control | Neutral now |
| Dashboards and alerting | Sufficient baseline | Very flexible and extensible | Neutral now |
| Production portability | Good | Excellent (best-of-breed modularity) | Favors modular later |
| Vendor/tool lock risk | Moderate | Low | Favors modular later |
| Cost in engineering time (short term) | Low | High | Favors SigNoZ now |

## Why no migration now
- Current objective is product delivery speed with reliable diagnostics.
- Sprint 10 already provides local observability value plus correlation-driven troubleshooting.
- Immediate migration would delay business features without clear short-term ROI.

## Triggers that justify migration
- Need strict separation of responsibilities per signal backend (logs vs traces vs metrics).
- Need advanced, custom alerting/routing model that exceeds SigNoZ operational comfort.
- Need explicit long-term stack standardization across environments/teams.
- Measured scale pressure on single-stack model (ingestion volume, retention, query latency).

## Migration readiness checklist (when triggered)
1. Define SLOs and retention targets (logs/metrics/traces).
2. Benchmark ingest/query latency under expected load profile.
3. Validate resource footprint on target infra.
4. Validate runbooks for incident triage and on-call usage.
5. Plan phased cutover:
   - Phase A: OTel collector stabilization.
   - Phase B: traces + metrics mirrored.
   - Phase C: logs routing and dashboards parity.
   - Phase D: alerting parity and final switch.

## Current recommendation
- Keep SigNoZ for Sprint 10 completion and near-term roadmap.
- Re-open migration work as a dedicated implementation sprint only after trigger criteria are met.
