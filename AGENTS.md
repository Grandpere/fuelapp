# AGENTS.md

Project collaboration rules for coding agents.

## 1) Scope And Context
- Stack: Symfony 8 + API Platform + FrankenPHP (worker mode) + PostgreSQL + Redis + RabbitMQ + Mercure.
- Runtime: Docker compose.
- Architecture: DDD-ish with feature-first folders and `Domain`, `Application`, `Infrastructure`, `UI` layers.

## 2) Core Engineering Principles
- Simplicity first: implement the smallest correct change.
- Minimal impact: touch only what is required.
- No lazy fixes: solve root cause, avoid temporary hacks.
- Senior-quality bar: code must be maintainable, testable and reviewable.

## 3) Workflow Orchestration
- Plan-first default for non-trivial work (roughly 3+ steps).
- Re-plan if implementation diverges or blockers appear.
- For complex work, write short explicit specs before coding.
- Tests are part of delivery, not optional: for each functional change, add or update the most relevant tests (`Unit`, `Integration`, `Functional`).
- Verification before done:
  - validate behavior,
  - review diff quality,
  - run quality/test checks,
  - ensure change is PR-ready.
- Demand elegance for non-trivial tasks: if solution feels hacky, pause and improve design.
- Autonomous bug fixing is expected from logs/tests, without unnecessary user back-and-forth.

## 4) Task Management
- Track planned tasks in `tasks/todo.md` when the task is multi-step.
- Keep progress/status updated while working.
- Summarize what changed and why at completion.
- Capture lessons from corrections/incidents in `/docs/ai/memory.md`.
- At task start, quickly review known lessons before coding.

## 5) Commands And Execution
- Use `Makefile` targets first.
- Compose command source of truth is `Makefile` variables (`DC`, `DC_EXEC`).
- Do not guess compose paths/flags manually if a make target already exists.

## 6) Project Conventions
- IDs: UUIDv7 (Symfony UID), Doctrine `uuid` type.
- Money/quantities: integer-based units only (no float math in domain persistence).
- API-first for business resources; UI must go through application/domain flows.
- Ownership/security constraints must be explicit; no implicit global access.

## 7) File And Refactor Guardrails
- Never create empty placeholder directories.
- Never create duplicate folders/files with suffixes like ` 2`, `copy`, `-old`, etc.
- If rename/replace is needed, update/move existing path instead of parallel duplicates.
- Do not revert unrelated user changes.
- Avoid destructive git commands (`reset --hard`, etc.) unless explicitly requested.
- `config/reference.php` may be auto-updated by Symfony tooling; include it in commits by default without blocking for confirmation.
- Mandatory duplicate-suffix control before each commit:
- scan for files and directories matching `* 2*` (excluding `vendor/` and `var/`),
- if exact duplicates: remove immediately,
- if content differs: stop and ask user which one to keep,
- run the scan again to confirm zero `* 2*` paths remain before commit.

## 8) Quality Gate (Before PR)
Run at least:
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`

And when web/security/API behavior changed:
- run `make phpunit-functional` (or equivalent targeted functional suite).

If JS packages are added to importmap:
- run `php bin/console importmap:install` in app container.

## 9) Documentation Workflow
- Update `/docs/ai/memory.md` when a bug/root cause is found and fixed.
- Update `/docs/ai/checklists.md` when delivery process changes.
- Keep sprint/backlog docs aligned in `/docs/backlog`.

## 10) Dependencies Policy
- If a missing dependency blocks a clean implementation, ask the user before introducing a workaround.
- Prefer adding the proper dependency (with user confirmation) over shipping a degraded fallback.
- When asking, state the package name and why it is required.
