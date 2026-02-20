# SP3-007 - Manual review flow for ambiguous imports

## Context
Some OCR parses will stay uncertain.

## Scope
- Add import status `needs_review`.
- Expose parsed draft payload and confidence flags.
- Add API action to confirm/correct and finalize receipt.

## Out of scope
- Full back-office UI polish.

## Acceptance criteria
- Ambiguous imports are recoverable without DB edits.
- Finalization path is audited.

## Dependencies
- SP3-005.

## Status
- todo
