# Current Focus

## Active sprint
- Sprint 37 - Stability, security, and observability hardening

## Current goal
- Remove the last recurring technical fragilities, harden sensitive flows, and add just enough observability to make support incidents easier to understand.

## In progress
- SP37-001 is implemented and ready for grouped validation at the end of the autonomous hardening pass.
- SP37-002 is implemented and ready for grouped validation at the end of the autonomous hardening pass.
- SP37-003 is the active implementation slice.
- SP37-003 will finish the cycle with compact observability improvements.

## Next tickets (ordered)
1. Microsoft OIDC remains deferred

## Notes
- Sprint 37 intentionally changes pace from workflow polish to hardening and diagnostics.
- Stability comes first because recurring test and runtime friction still wastes support and validation time.
- SP37-001 now stabilizes XLSX export delivery around temp-file binary responses and bounded functional validation.
- SP37-002 now hardens auth-sensitive login behavior by collapsing public API login failures and validating session-backed post-login redirect targets.
- Observability stays intentionally small and should only add diagnostics that materially improve triage.
