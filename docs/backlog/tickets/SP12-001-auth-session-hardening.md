# SP12-001 - Auth and session hardening

## Context
Authentication and session flows are operational but need a focused hardening pass.

## Scope
- Enforce stricter login/session safeguards (session fixation/rotation checks, lockout policy review, CSRF consistency checks).
- Harden password/reset/email verification edge cases.
- Validate admin self-demotion and last-admin safeguards under edge conditions.

## Out of scope
- New identity provider rollout.

## Acceptance criteria
- Critical auth/session misuse scenarios are covered by tests.
- No auth regression in normal login/logout flows.

## Dependencies
- Existing security and admin auth baseline.

## Status
- done
