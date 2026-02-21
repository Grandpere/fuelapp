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
- do not run `make phpunit-functional` automatically from agent actions.
- ask the user to run `make phpunit-functional` (or equivalent targeted functional suite) manually.
- request the user to share failing output/errors, then fix based on those errors.

If JS packages are added to importmap:
- run `php bin/console importmap:install` in app container.

## 9) Documentation Workflow
- Update `/docs/ai/memory.md` when a bug/root cause is found and fixed.
- Update `/docs/ai/checklists.md` when delivery process changes.
- Keep sprint/backlog docs aligned in `/docs/backlog`.

## 10) Dependencies Policy
- Never add a new dependency (Composer, npm, system package, Docker image/tooling) without explicit user approval first.
- Before any dependency install, ask the user systematically and wait for confirmation.
- In the request, always explain:
  - exact package name,
  - why it is needed for a clean implementation,
  - what degrades or becomes workaround-heavy without it.
- If the dependency is refused, document the chosen fallback and its limitations in the handover summary.
- Prefer free solutions first for all app capabilities.
- Preferred order when choosing tooling/providers:
  - no external API (local/self-hosted or built-in project dependency),
  - free external API if local option is not realistic short-term,
  - paid external API only if explicitly approved by the user.
- Even for free options, any new project dependency must still follow the approval flow above.

## 11) User Runbook At Handover
- At the end of each task, explicitly state whether the user has commands to run.
- If commands are required, list them clearly and in execution order.
- If no command is required, explicitly say: "No action needed on your side."
- Explicitly state `make restart-app` status using one of:
  - `Required` (must be run),
  - `Recommended` (safe refresh after infra/runtime/config changes),
  - `Not needed` (pure code/template changes hot-reload safely).
- With FrankenPHP worker mode, Twig/CSS/UI changes may still require `make restart-app`; mention this explicitly when relevant.

## 12) Admin Coverage Decision For New Features
- For every new feature, systematically evaluate whether an admin/back-office exposure is needed.
- Before implementing admin coverage, ask the user for confirmation.
- In that confirmation request, always explain:
  - why adding admin support is useful (operations, support, audit, moderation, recovery, etc.),
  - whether it is necessary now or can be deferred,
  - what the concrete impact is if admin support is not added now.
- If admin coverage is deferred/refused, mention this explicitly in task handover and backlog notes.

## 13) Commit And PR Discipline
- Commit at the end of each completed task/ticket (do not batch multiple tickets without reason).
- Keep commits scoped and readable so task-level review is straightforward.
- At the end of each sprint, open a dedicated GitHub PR for that sprint scope.
- Always send the PR URL to the user for review before merge.
