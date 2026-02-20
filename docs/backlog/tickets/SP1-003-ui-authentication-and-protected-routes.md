# SP1-003 - UI authentication and protected routes

## Context
Web pages currently expose business data.

## Scope
- Add UI authentication flow.
- Protect `/ui/*` routes.
- Add logout flow.

## Out of scope
- Password reset and email verification.

## Acceptance criteria
- Anonymous users are redirected to login.
- Authenticated users can access receipts pages.
- CSRF/security checks remain green.

## Dependencies
- SP1-001.

## Status
- done
