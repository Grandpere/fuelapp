# SP10-005 - Observability hardening (secrets + alerting baseline)

## Context
Sprint 10 baseline is operational (SigNoZ + traces/metrics/logs), but still uses placeholder/local defaults and has no explicit alerting baseline.
This ticket secures day-2 operations before scaling usage.

## Scope
- Move sensitive observability values to non-versioned local env where applicable:
  - `SIGNOZ_JWT_SECRET`
  - `SIGNOZ_TOKENIZER_JWT_SECRET`
  - any future DSN credentials if changed from local defaults.
- Document a clear env policy for local/dev/prod in ops docs.
- Define and configure a minimum alerting baseline in SigNoZ:
  - HTTP 5xx rate threshold,
  - p95 latency threshold,
  - ingestion/collector error signal,
  - optional queue backlog guardrail.
- Add incident response quick steps for each alert class.

## Out of scope
- Full multi-environment observability IaC rollout.
- Pager/notification provider integration beyond local baseline.
- Migration to modular stack (already evaluated in SP10-004).

## Acceptance criteria
- Secrets policy is documented and enforced in local setup docs.
- At least 3 actionable alerts are defined with concrete thresholds and owner actions.
- Runbook includes "what fired / what to check / how to recover" per alert type.
- Existing telemetry flow remains functional after hardening changes.

## Technical notes
- Keep `resources/docker/.env.example` safe and non-sensitive.
- Keep real local values in `resources/docker/.env` or `.env.local` (non-committed in shared/public contexts).
- Prefer deterministic, low-noise thresholds to avoid alert fatigue.

## Dependencies
- SP10-001 to SP10-004 (completed baseline + runbook + stack decision).

## Status
- done
