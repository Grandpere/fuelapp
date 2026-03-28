# TODO - SP32-001 Admin receipt editing continuity

## Plan
- [completed] Review the current admin receipt list, detail, and edit flow for missing support continuity and follow-up shortcuts.
- [completed] Add the smallest useful continuity improvements across receipt, import, vehicle, station, and context-filtered admin lists.
- [completed] Update admin functional coverage for the touched receipt support flow.
- [completed] Run targeted syntax/Twig validation for the touched receipt admin flow.
- [pending] Batch user-run `make phpunit-functional` at the end of Sprint 32 after the three commits.

# TODO - SP32-003 Admin dashboard signal quality

## Plan
- [completed] Review the admin dashboard cards and lists for weak prioritization, missing next steps, and noisy signals.
- [completed] Improve the dashboard with clearer urgency grouping and better drill-down actions without broad redesign.
- [completed] Update admin functional coverage for the touched dashboard contract.
- [completed] Run targeted syntax/Twig validation for the touched dashboard flow.
- [pending] Batch user-run `make phpunit-functional` at the end of Sprint 32 after the three commits.

# TODO - SP32-002 Admin user/account support pass

## Plan
- [pending] Review the admin user management list for the most repetitive support actions and missing account-state context.
- [pending] Add the highest-value support shortcuts and clearer account-state signals while keeping the UI compact.
- [pending] Update admin/API functional coverage for the touched account support contract.
- [pending] Run non-functional quality gates (`phpstan`, `unit`, `integration`, `cs-fixer-check`).
- [pending] Batch user-run `make phpunit-functional` at the end of Sprint 32 after the three commits.
