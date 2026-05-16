# TODO - UI i18n migration (fr default, en fallback)

## Plan
- [completed] Define the translation strategy and migration scope with the user.
- [completed] Write the design spec for progressive Symfony translations with `fr` default and `en` fallback.
- [completed] Review the written spec with the user before turning it into an implementation plan.
- [completed] Translate the main user-facing shell plus receipt, import, station, public fuel map, and analytics flows.
- [completed] Translate the admin/back-office shell, dashboard, and import workflows.
- [completed] Translate the remaining user-facing dashboard, vehicle, and maintenance screens plus their tests.
- [completed] Re-run quality gates and prepare the manual functional validation handover.
- [pending] Translate the remaining admin resource screens (users, identities, security, audit, vehicles, stations, receipts, maintenance, diagnostics).
- [pending] Hand over the remaining manual functional admin validation.

# TODO - SP41-003 Topbar account actions responsiveness

## Plan
- [completed] Inspect the shared authenticated shell and confirm why the account controls overflow.
- [completed] Rework the topbar layout so navigation and account actions remain visible without awkward scrolling.
- [completed] Verify the header on desktop and mobile-sized viewports, then run the relevant quality checks.

# TODO - SP38-001 Public fuel station import and cache

## Plan
- [completed] Review project memory and start from an up-to-date dedicated branch.
- [completed] Inspect the data.gouv instant v2 CSV shape and choose a local-cache model.
- [completed] Implement parser, importer, persistence, sync command and tests.
- [completed] Run quality gates and review the diff for PR readiness.
- [pending] Hand over migration/sync commands for user-side validation.

# TODO - SP38-002 Admin public station sync monitor

## Plan
- [completed] Add read-only admin diagnostics for sync health, freshness and failures.

# TODO - SP38-003 Public fuel station map

## Plan
- [completed] Add a public map/list from cached public stations with practical fuel filters.

# TODO - SP38-004 Visited/public station matching

## Plan
- [completed] Add safe matching between user visited stations and public station records.

# TODO - SP39-001 Analytics visited/public station map fusion

## Plan
- [completed] Inspect analytics map and matching data already available in the codebase.
- [completed] Add a read model that enriches visited station points with nearby public station context.
- [completed] Update the analytics map/list UI to show the merged station context clearly.
- [completed] Add or update unit/integration/functional coverage and run quality gates.

# TODO - SP39-002 Nearby public stations around visited stations

## Plan
- [completed] Design a dedicated bulk nearby-public reader for analytics instead of reusing the generic list search.
- [completed] Enrich the analytics station map with nearby public stations not already matched.
- [completed] Update analytics UI and fallback list to distinguish visited, matched public and nearby public stations.
- [pending] Add or update unit/integration/functional coverage and run quality gates.

# TODO - SP40-001 Station picker and import matching

## Plan
- [completed] Add a shared station candidate search read model.
- [completed] Support explicit existing-station selection in manual receipt creation.
- [completed] Reuse the same station selection flow during import review finalization.
- [completed] Add or update unit/integration/functional coverage and run quality gates.

# TODO - SP40-002 Public station suggestions in station picker

## Plan
- [completed] Add a unified suggestion flow that merges internal stations and cached public fuel stations.
- [completed] Let receipt creation resolve a selected public suggestion into an internal `Station` on save.
- [completed] Reuse the same public suggestion flow during import review finalization.
- [completed] Run quality gates and prepare the manual functional validation handover.

# TODO - SP40-003 Durable link between Station and public source

## Plan
- [completed] Add nullable unique `publicSourceId` on `Station` with migration and persistence coverage.
- [completed] Persist the public source link when a public suggestion creates or reuses a station.
- [completed] Reject conflicting public relinks instead of silently overwriting.
- [pending] Finish manual functional validation of the new conflict/link flows and prepare the handover.

# TODO - SP40-004 Station picker UX polish

## Plan
- [pending] Make the active suggestion state explicit in manual receipt creation.
- [pending] Mirror the same clarity in import review without changing business behavior.
- [pending] Add/update the most relevant coverage and run quality gates.

# TODO - SP41-001 Favorite stations

## Plan
- [completed] Add a per-user favorite relation on internal stations with migration and repository support.
- [completed] Expose favorite toggles on station list and station detail pages.
- [completed] Surface favorite state in analytics station context.
- [completed] Add/update unit, integration, and functional coverage and run quality gates.

# TODO - SP41-002 Favorite stations ranking and filtering

## Plan
- [pending] Make favorites rise to the top of the station index while preserving recent-visit ordering inside each group.
- [pending] Add a lightweight `favorites only` filter on the station index with a dedicated empty state.
- [pending] Add or update the relevant functional coverage and run quality gates.
