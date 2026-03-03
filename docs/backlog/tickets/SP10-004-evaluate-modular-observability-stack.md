# SP10-004 - Evaluate migration to modular OTel/Loki/Tempo/Prometheus/Grafana stack

## Context
Current Sprint 10 baseline uses SigNoZ to maximize short-term delivery speed.
A future migration to modular stack may offer finer control and interoperability.

## Scope
- Compare SigNoZ baseline versus modular stack on:
  - operational complexity,
  - resource usage,
  - feature coverage (logs/metrics/traces/alerts),
  - production portability.
- Produce migration/no-migration recommendation and phased plan if migration is approved.

## Delivery
- Decision document: `/docs/ops/observability-stack-decision.md`
- Recommendation: keep SigNoZ now, re-evaluate migration when explicit trigger criteria are met.

## Decision criteria
- Keep SigNoZ if target capabilities are met with acceptable operational cost.
- Migrate only if modular stack gives clear measurable value for project goals.

## Status
- done
