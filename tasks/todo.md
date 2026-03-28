# TODO - SP37-001 Stability hardening pass

## Plan
- [completed] Review the recurring technical failures and fragile validations seen in the last few sprints.
- [completed] Apply the smallest root-cause fixes that reduce reruns and flaky support behavior.
- [completed] Update the most relevant tests and project memory for the stabilized flows.
- [completed] Run targeted syntax/Twig validation for the touched stability work.
- [pending] Batch user-run `make phpunit-functional` after the autonomous hardening pass.

# TODO - SP37-002 Security hardening pass

## Plan
- [completed] Review sensitive auth, import, and admin mutation flows with a security lens.
- [completed] Apply the smallest concrete hardening changes that improve safety without product churn.
- [completed] Update security-relevant tests and project memory for the hardened behavior.
- [completed] Run targeted syntax/Twig validation for the touched security work.
- [pending] Batch user-run `make phpunit-functional` after the autonomous hardening pass.

# TODO - SP37-003 Observability support pass

## Plan
- [pending] Review the least legible support and incident flows after the stability/security passes.
- [pending] Add compact diagnostics, correlation cues, or support breadcrumbs where they provide real triage value.
- [pending] Update the most relevant tests and project memory for the new observability contract.
- [pending] Run targeted syntax/Twig validation for the touched observability work.
- [pending] Batch user-run `make phpunit-functional` after the autonomous hardening pass.
