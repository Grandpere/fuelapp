# SP37-002 - Security hardening pass

## Summary
Run a focused hardening pass on the most sensitive auth, import, and admin actions.

## Delivered scope
- collapse `/api/login` public failures so disabled accounts no longer disclose their status to callers
- keep the precise disabled-account reason in admin audit metadata for operator visibility
- validate session-backed post-login target paths through `SafeReturnPathResolver`
- add focused functional and unit coverage for the hardened contracts
