# TODO - SP3-009 Optional API Platform native upload operation refactor

## Plan
- [completed] Move `POST /api/imports` from Symfony route attribute to API Platform operation metadata.
- [completed] Keep multipart validation and response contract (`id`, `status`, `createdAt`) unchanged.
- [completed] Remove custom OpenAPI decorator and keep `/api/imports` documented natively.
- [completed] Validate no behavior regression on upload/auth/docs through existing suites.
- [completed] Run quality checks.
