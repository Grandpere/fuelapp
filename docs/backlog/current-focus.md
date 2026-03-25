# Current Focus

## Active sprint
- Sprint 12 - Security hardening

## Current goal
- Finish the dedicated security hardening pass with concrete, low-risk protections across auth/session and import/upload surfaces.

## In progress
- `SP12-001` - Auth and session hardening
- `SP12-002` - API abuse protection and input hardening
- `SP12-003` - Import/upload security hardening

## Next tickets (ordered)
1. `SP12-001` - Auth and session hardening
2. `SP12-002` - API abuse protection and input hardening
3. `SP12-003` - Import/upload security hardening
4. `SP12-004` - Security observability and incident runbook hardening
5. `SP11-001` - Microsoft OIDC rollout (deferred)

## Notes
- Sprint 11 OCR resilience work is delivered enough to switch active focus to Sprint 12.
- Recent Sprint 12 progress already delivered:
  - explicit session fixation / logout invalidation guarantees,
  - UI session revocation when a user is disabled after login,
  - stale API token regression coverage for disabled users,
  - redundant verification resend blocking for already verified accounts,
  - controlled validation responses for unusable upload files,
  - ZIP entry-limit regression coverage.
- Microsoft OIDC remains deferred unless product priority changes.
- Security observability baseline/runbook is already documented; remaining work is mostly alignment/closure unless a new gap appears.
