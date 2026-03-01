# SP10-002 - Structured logs + correlation

## Context
Need deterministic troubleshooting across HTTP and async jobs.

## Scope
- Add structured app logs (`request_id`, `user_id`, `job_id`, `correlation_id`).
- Propagate correlation ID from HTTP to Messenger handlers.

## Status
- done
