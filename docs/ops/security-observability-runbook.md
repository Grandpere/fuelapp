# Security Observability And Incident Runbook

## Scope
This runbook defines how to detect and triage security-relevant events with the local SigNoZ stack.
It complements:
- `docs/ops/observability-local-signoz.md`
- `docs/ops/observability-alerting-baseline.md`
- `docs/security/auth-and-ops.md`

## Signals To Monitor
### 1) Authentication abuse
- API login failures (`security.login.failure`, `channel=api_password`)
- UI login failures (`security.login.failure`, `channel=ui_password`)
- Rate-limited auth responses (`HTTP 429` on `/api/login`)

### 2) Upload/import abuse
- Rate-limited upload endpoints (`HTTP 429` on `/api/imports`, `/api/imports/bulk`)
- Upload validation rejects (`HTTP 422`) spike
- Suspicious ZIP rejects (path traversal / oversized entries)

### 3) Admin-sensitive actions
- Role changes
- User activation/deactivation
- Password reset triggers
- Email verification toggles/resend

## Alert Baseline (Security-Focused)
Define these rules in SigNoZ alerting, low-noise defaults:

1. `SEC-AUTH-FAILURE-SPIKE`
- Condition: `security.login.failure` count >= 20 in 5m for same `target_id` OR same source IP.
- Severity: high
- First response:
  1. Check if failures are distributed (credential stuffing) or focused (single account attack).
  2. Confirm rate-limit responses are present.

2. `SEC-AUTH-RATE-LIMIT-SPIKE`
- Condition: `/api/login` status `429` count >= 20 in 5m.
- Severity: medium
- First response:
  1. Validate no false positive from test scripts.
  2. If persistent, evaluate temporary stricter limit window.

3. `SEC-UPLOAD-ABUSE-SPIKE`
- Condition: combined `429/422` on `/api/imports` and `/api/imports/bulk` >= 30 in 10m.
- Severity: medium
- First response:
  1. Check whether rejections are mostly malformed payloads, rate limits, or ZIP path violations.
  2. Validate no ongoing user-facing regression.

4. `SEC-ADMIN-SENSITIVE-ACTION-BURST`
- Condition: >= 10 admin-sensitive actions in 10m by same actor.
- Severity: high
- First response:
  1. Confirm expected maintenance/admin session.
  2. If unexpected, immediately review actor session and recent auth failures.

## SigNoZ Query Starters
Use logs search with structured fields where available.

### Auth failures
- `action = "security.login.failure"`
- Narrow by channel:
  - `details.channel = "api_password"`
  - `details.channel = "ui_password"`

### Auth success/failure ratio
- Success: `action = "security.login.success"`
- Failure: `action = "security.login.failure"`
- Compare counts in same 5m window.

### Rate-limited API login
- `http.route = "/api/login" AND http.status_code = 429`

### Upload abuse
- `http.route IN ["/api/imports", "/api/imports/bulk"] AND http.status_code IN [422,429]`

### Admin-sensitive actions
- `action IN ["admin.user.toggled_active","admin.user.toggled_admin","admin.user.reset_password","admin.user.toggled_email_verification","admin.user.resend_verification"]`

## Triage Procedure
1. Confirm time window and affected actor(s):
- filter by `correlation_id`, `target_id`, `actor_id`, `http.route`.
2. Determine pattern:
- single-account brute force, distributed spray, scripted upload abuse, or suspicious admin burst.
3. Contain:
- verify rate-limit behavior is active and returning controlled `429`.
- if needed, disable target account temporarily in BO.
4. Validate impact:
- check app health and ingestion pipeline remain healthy.
5. Record outcome:
- summarize trigger, scope, action, and follow-up in incident log.

## Local Verification Checklist
1. Start stack:
- `make observability-up`
2. Generate controlled test events:
- 6+ failed `/api/login` attempts for same email
- repeated `/api/imports` invalid requests to trigger `422` and then `429`
3. Verify in SigNoZ:
- logs visible for `security.login.failure`
- `429` visible for targeted routes
4. Confirm runbook links are attached to alert rules.

## Escalation Rules
- Escalate immediately if:
  - high-volume failures across many accounts in short window,
  - repeated admin-sensitive actions outside planned maintenance,
  - observability blind spot (`otel-collector`/ClickHouse ingestion failure).

## Notes
- Local thresholds are conservative defaults. Tune by observed noise in your environment.
- Keep this runbook aligned with security tickets and actual alert rule names in SigNoZ.
