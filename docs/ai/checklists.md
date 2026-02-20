# Checklists

Reusable, update-friendly checklists for delivery quality.

## 1) Daily implementation checklist
- [ ] Confirm target ticket ID (e.g. `SPx-yyy`).
- [ ] Confirm scope and out-of-scope from ticket file.
- [ ] Identify impacted layers (`Domain/Application/Infrastructure/UI`).
- [ ] Implement smallest viable slice.
- [ ] Add/update tests for changed behavior (mandatory):
- [ ] `Unit` for domain/application rules.
- [ ] `Integration` for persistence/repository/infrastructure behavior.
- [ ] `Functional` for HTTP/security/UI/API flows when endpoints/pages are touched.
- [ ] Run quality gates locally.
- [ ] Update docs if behavior/contracts changed.

## 2) PR checklist
- [ ] PR title includes ticket ID.
- [ ] Clear summary: problem, approach, impact.
- [ ] Security/ownership implications documented.
- [ ] Migration impact documented (if any).
- [ ] Backward compatibility impact documented.
- [ ] Commands run and results listed.
- [ ] Screenshots/HTTP examples added for UI/API changes.

## 3) Symfony/API change checklist
- [ ] Route requirements validate IDs strictly (UUID regex/validation).
- [ ] Invalid input returns controlled 4xx (not 500).
- [ ] API Platform operation `read` behavior verified where processor is custom.
- [ ] DTO validation rules updated.
- [ ] Provider/processor behavior covered by tests.

## 4) Database/migration checklist
- [ ] `make db-diff` only when migrations are up to date.
- [ ] Migration SQL reviewed (indexes, constraints, FK actions).
- [ ] Test database migration executed.
- [ ] Roll-forward path verified (no destructive surprises).

## 5) Async job checklist (Messenger)
- [ ] Message + handler are idempotent.
- [ ] Retry strategy defined.
- [ ] Failure state persisted and readable.
- [ ] Logs include job ID/entity ID/correlation context.
- [ ] Manual reprocess path defined if needed.

## 6) Front/UI checklist
- [ ] Mobile + desktop behavior verified.
- [ ] Empty/loading/error states are visible and usable.
- [ ] Filtering/sorting/export parity verified.
- [ ] Forms have server-side validation and clear feedback.

## 7) Importmap/assets checklist
- [ ] New JS package added to `importmap.php`.
- [ ] `php bin/console importmap:install` executed.
- [ ] Turbo frame/page reload behavior verified.

## 8) Final local gate checklist
- [ ] `make phpstan`
- [ ] `make phpunit-unit`
- [ ] `make phpunit-integration`
- [ ] `make php-cs-fixer-check`

## Maintenance rule
When a recurring issue appears, append a short entry in `/docs/ai/memory.md` and, if process-related, update this checklist file.
