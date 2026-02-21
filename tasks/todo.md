# TODO - SP3-005 Receipt parsing and normalization

## Plan
- [completed] Define parsing contract and parsed draft model for OCR output.
- [completed] Implement regex-based parser for station/date/lines/amounts/VAT normalization.
- [completed] Build validated receipt creation payload candidate from parsed draft.
- [completed] Integrate parser output into import async `needs_review` payload with explicit issues.
- [completed] Add unit coverage for parser and adapt import handler tests.
- [completed] Run quality checks.
