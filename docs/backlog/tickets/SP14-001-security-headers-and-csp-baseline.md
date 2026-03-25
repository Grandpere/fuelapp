# SP14-001 - Security headers and CSP baseline

## Context
Sprint 13 introduced a richer browser-side surface (theme toggle, Chart.js behavior, analytics interactions), but the current delivery stack still relies on permissive browser defaults and inline runtime behavior.

## Scope
- Add an explicit baseline for security headers on UI responses:
  - `Content-Security-Policy` (realistic initial policy),
  - `Referrer-Policy`,
  - `X-Frame-Options` / `frame-ancestors`,
  - `Permissions-Policy`.
- Keep the baseline compatible with the current app before stricter follow-up tightening.
- Add regression coverage for expected headers on key UI routes.

## Out of scope
- Full CSP lockdown in a single ticket.
- Replacing all external assets by itself.

## Acceptance criteria
- UI responses expose a documented security-header baseline.
- CSP is explicit and deliberate, not implicit browser behavior.
- Functional or integration coverage protects the baseline against regression.

## Dependencies
- Existing Symfony security/runtime stack.
- Sprint 13 frontend/theme additions.

## Status
- todo
