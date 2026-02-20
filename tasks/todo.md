# TODO - SP2-002 Messenger job geocoding

## Plan
- [completed] Implement geocoding message + handler with idempotent status processing.
- [completed] Add internal station repository read path for worker (no user token scope).
- [completed] Wire create station flow to dispatch geocoding message.
- [completed] Configure Messenger async/failure transport routing and retry baseline.
- [completed] Add/update unit and integration tests for dispatch + handler behavior.
- [completed] Run quality gates and update backlog/docs.
