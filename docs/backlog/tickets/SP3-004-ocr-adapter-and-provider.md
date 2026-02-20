# SP3-004 - OCR adapter abstraction and first provider

## Context
OCR provider may change over time.

## Scope
- Define OCR interface contract.
- Implement first provider adapter (free/low-cost starter).
- Normalize raw OCR output for parser.

## Out of scope
- Final parsing rules.

## Acceptance criteria
- OCR provider can be swapped without domain changes.
- Adapter errors are mapped to import job failures cleanly.

## Dependencies
- SP3-003.

## Status
- todo
