# TODO - SP3-004 OCR adapter abstraction and first provider

## Plan
- [completed] Define OCR provider contract and normalized extraction model.
- [completed] Implement first OCR provider adapter (`OCR.Space`) with clean error mapping.
- [completed] Add stored-file locator abstraction and local implementation for async workers.
- [completed] Integrate OCR call in import async handler and map outcomes to statuses (`needs_review` / `failed`).
- [completed] Add unit/integration coverage for provider + handler behavior.
- [completed] Run quality checks.
