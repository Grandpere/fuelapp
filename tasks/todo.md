# TODO - SP3-007 Manual review flow for ambiguous imports

## Plan
- [completed] Expose import jobs in API with review metadata (`parsedDraft`, `issues`, `creationPayload`).
- [completed] Add finalize API action to confirm/correct `needs_review` imports.
- [completed] Finalize import by creating receipt via existing domain/application flow.
- [completed] Persist processed audit payload with `finalizedReceiptId`.
- [completed] Add unit, integration, and functional coverage for review/finalization.
- [completed] Run quality checks.
