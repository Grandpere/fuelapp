# SP11-002 - OCR parser hardening for noisy fuel tickets

## Context
Some noisy OCR outputs still fail to extract quantity and unit price consistently on real pump receipts.

## Scope
- Improve parser heuristics for quantity/price extraction around `Volume`/`Prix` patterns.
- Add guardrails to avoid false positives (totals interpreted as unit price).
- Add focused unit coverage with noisy real-world-like OCR samples.

## Out of scope
- OCR provider replacement.
- Manual review UI redesign.

## Acceptance criteria
- Parser extracts quantity and unit price on known noisy ticket patterns.
- Existing parser scenarios keep passing without regression.
- Ambiguous cases still stay reviewable through current import workflow.

## Technical notes
- Keep heuristics deterministic and bounded to local context windows.

## Dependencies
- SP3-005, SP3-007, SP3-011.

## Status
- done
