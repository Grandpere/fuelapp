# AI Memory

Project memory for recurring pitfalls, decisions, and proven fixes.

## How to use
- Add one entry per incident/decision.
- Keep it short, factual, and actionable.
- Prefer prevention rules over long narratives.
- Update this file immediately after a bug is diagnosed/fixed.

## Entry template

```md
## YYYY-MM-DD - Short title
- Symptom:
- Root cause:
- Fix:
- Prevention:
- Links: (optional)
```

## Incident Log

## 2026-02-19 - Docker compose path mismatch
- Symptom: `make up` failed with missing compose file.
- Root cause: path/name drift between compose files and Makefile assumptions.
- Fix: centralized compose usage through `DC` in `Makefile`.
- Prevention: use Make targets; do not hardcode compose file paths in ad-hoc commands.

## 2026-02-19 - Port collisions on local host
- Symptom: stack failed with `port is already allocated` (5432/5672/8080...).
- Root cause: default host ports already used by other local stacks.
- Fix: set alternate host ports in compose env/config.
- Prevention: keep host ports configurable and avoid assuming defaults are free.

## 2026-02-19 - FrankenPHP runtime class missing
- Symptom: `Runtime\\FrankenPhpSymfony\\Runtime not found` at startup.
- Root cause: missing/incompatible runtime package strategy for current Symfony version.
- Fix: align runtime strategy with installed Symfony/runtime packages.
- Prevention: verify package compatibility before adding runtime adapters.

## 2026-02-19 - Twig class not autoloaded while package exists
- Symptom: `Class "Twig\\Environment" not found` during cache clear.
- Root cause: broken/incomplete vendor autoload state.
- Fix: reinstall dependencies and regenerate autoload; verify with `class_exists`.
- Prevention: if class exists on disk but not in runtime, suspect autoload/vendor state first.

## 2026-02-19 - `columns[]` query param parsing errors
- Symptom: export/list errors about non-scalar values and wrong column behavior.
- Root cause: array query param read as scalar.
- Fix: parse as array (`all('columns')` style).
- Prevention: multi-select query params must always be parsed as arrays.

## 2026-02-19 - Flatpickr vendor asset missing
- Symptom: template error `The "flatpickr" vendor asset is missing`.
- Root cause: package added to `importmap.php` without installing vendor assets.
- Fix: run `php bin/console importmap:install` in app container.
- Prevention: every importmap addition requires install step.

## 2026-02-19 - API Platform DELETE returned 404 on custom resource
- Symptom: `DELETE /api/stations/{id}` returned 404 for existing station.
- Root cause: API Platform attempted a pre-read not compatible with this processor-based resource flow.
- Fix: set `read: false` on DELETE operation and handle deletion in processor.
- Prevention: for processor-driven DELETE, explicitly choose `read` behavior.

## 2026-02-20 - Ownership model clarified: user owns receipts, not stations
- Symptom: ownership column was added on `stations` then rejected by business rules.
- Root cause: technical assumption conflicted with domain model.
- Fix: remove `stations.owner_id`; keep ownership on receipts only.
- Prevention: validate ownership semantics against domain language before migration design.

## 2026-02-20 - Symfony security entry point type mismatch
- Symptom: runtime `TypeError` on firewall exception listener (`entry_point` received authenticator with wrong interface).
- Root cause: custom authenticator used as `entry_point` without implementing `AuthenticationEntryPointInterface`.
- Fix: implement `AuthenticationEntryPointInterface` and `start()` in login authenticator.
- Prevention: when declaring `entry_point`, ensure service explicitly implements the expected interface.

## 2026-02-20 - API auth strategy switched from Basic to JWT
- Symptom: Basic auth considered unsuitable for front/mobile usage.
- Root cause: temporary baseline did not match product auth target.
- Fix: implement `/api/login` and Bearer token authenticator for `/api/*`.
- Prevention: align interim security design with expected client architecture early.

## 2026-02-20 - UI logout hardened with CSRF
- Symptom: logout endpoint existed without explicit CSRF hardening requirement.
- Root cause: default logout setup focused on flow completion, not explicit hardening.
- Fix: enable firewall logout CSRF and use POST logout form with token.
- Prevention: for every state-changing UI endpoint, enforce method + CSRF by default.

## 2026-02-20 - Station visibility without station ownership
- Symptom: need user isolation while stations remain globally deduplicated.
- Root cause: domain model states users own receipts, not station entities.
- Fix: scope station repository reads through linked receipts owned by current user.
- Prevention: when ownership is indirect, enforce access perimeter via relationship queries.

## 2026-02-20 - Historical unowned receipts after ownership rollout
- Symptom: older receipts become invisible once owner filtering is enabled.
- Root cause: legacy rows have `owner_id = NULL`.
- Fix: provide `app:receipts:claim-unowned <email>` operational command.
- Prevention: include backfill/claim path when introducing ownership constraints.

## 2026-02-20 - Symfony 8 voter signatures and generics
- Symptom: static analysis errors on custom voters (missing generics, wrong method signature).
- Root cause: voters not aligned with Symfony 8 `Voter` generic/signature expectations.
- Fix: add `@extends Voter<string, string>` and include optional `?Vote $vote` arg in `voteOnAttribute`.
- Prevention: scaffold new voters from Symfony 8 signature template.

## 2026-02-20 - Functional tests without BrowserKit
- Symptom: functional suite failed with `Class Symfony\Component\BrowserKit\AbstractBrowser not found`.
- Root cause: project does not include `symfony/browser-kit`, so `WebTestCase::createClient()` is unavailable.
- Fix: implement functional HTTP tests with `KernelTestCase` + kernel `Request` handling.
- Prevention: if BrowserKit is not installed, avoid `WebTestCase` and test endpoints via kernel requests.

## Standing Decisions
- Use integer-based monetary and quantity units in domain/storage.
- Keep feature-first DDD foldering (`Receipt/*`, `Station/*`, etc.).
- Prefer async for external/slow operations (geocoding, OCR/import).
- Sprint and backlog source of truth lives in `/docs/backlog`.
