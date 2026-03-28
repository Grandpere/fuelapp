# Current Focus

## Active sprint
- Sprint 37 - Stability, security, and observability hardening

## Current goal
- Remove the last recurring technical fragilities, harden sensitive flows, and add just enough observability to make support incidents easier to understand.

## In progress
- SP37-001 is the active implementation slice.
- SP37-002 will follow as a focused security hardening pass.
- SP37-003 will finish the cycle with compact observability improvements.

## Next tickets (ordered)
1. Microsoft OIDC remains deferred

## Notes
- Sprint 37 intentionally changes pace from workflow polish to hardening and diagnostics.
- Stability comes first because recurring test and runtime friction still wastes support and validation time.
- Security follows as a focused pass on admin, import, and auth-sensitive flows.
- Observability stays intentionally small and should only add diagnostics that materially improve triage.
