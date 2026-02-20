# SP3-005 - Receipt parsing and normalization

## Context
OCR output must become consistent business data.

## Scope
- Parse merchant/date/line/amount/tax fields from OCR output.
- Normalize units and monetary precision.
- Build validated creation command payload.

## Out of scope
- UX review UI.

## Acceptance criteria
- Parsed payload respects domain invariants.
- Invalid parse cases are marked for manual review.

## Dependencies
- SP3-004.

## Status
- todo
