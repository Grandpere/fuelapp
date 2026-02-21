# TODO - SP3-002 Upload API endpoint and validation

## Plan
- [completed] Add authenticated upload API endpoint (`POST /api/imports`) for receipt files.
- [completed] Validate upload constraints (required file, max size, supported types).
- [completed] Persist queued import jobs through application handler and storage adapter.
- [completed] Add tests for handler logic and functional API behavior (anonymous, invalid, success).
- [completed] Run quality gates and sync backlog/docs.
