# SP11-005 - OCR provider circuit breaker for outage bursts

## Context
During provider outages, many imports can fail in cascade and generate noisy retries.

## Scope
- Add short-lived circuit-breaker behavior around OCR provider calls.
- Fast-fail while breaker is open with clear temporary reason.
- Auto-close after cooldown and resume normal processing.

## Out of scope
- Multi-provider orchestration.

## Acceptance criteria
- Repeated provider outages trigger breaker open state.
- While open, jobs are deferred/retried without unnecessary provider calls.
- Metrics/logs expose breaker state transitions.

## Dependencies
- SP11-004.

## Status
- todo
