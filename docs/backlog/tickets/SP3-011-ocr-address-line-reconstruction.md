# SP3-011 - OCR address line reconstruction (multi-line street)

## Context
Some receipt OCR outputs split the street over multiple lines (example: `Route de` on one line, `Troyes` on the next). Current parsing may keep only a partial street value.

## Scope
- Improve import parser heuristics to reconstruct street name from adjacent OCR lines when confidence is acceptable.
- Keep conservative behavior to avoid false positives on noisy tickets.
- Add focused unit tests with real-world noisy OCR samples (including split street lines).

## Out of scope
- OCR engine/provider replacement.
- Manual correction UI redesign.

## Acceptance criteria
- For known split-address patterns, parser outputs full street string (e.g. `Route de Troyes`).
- Existing working imports do not regress (unit + functional suites green).
- If confidence is too low, parser keeps current safe fallback and flags remain reviewable.

## Dependencies
- SP3-005, SP3-007.

## Status
- todo
