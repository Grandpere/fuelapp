# SP11-006 - OCR fallback strategy (secondary provider/local option)

## Context
Single-provider dependency on OCR.Space can block imports during external incidents.

## Scope
- Define and implement fallback strategy when primary OCR provider is unavailable.
- Keep provider abstraction clean and configurable.
- Document operational behavior and tradeoffs.

## Out of scope
- Paid provider rollout without explicit approval.

## Acceptance criteria
- Import flow remains partially operational when OCR.Space is degraded.
- Fallback path is test-covered and observable.
- Provider selection is explicit in configuration.

## Technical notes
- Prefer free/local-first options per project dependency policy.

## Dependencies
- SP11-004, SP11-005.

## Status
- todo
