# FuelApp - Goals, Roadmap, Sprints

## Product goals
- Track fuel receipts with reliable financial data (no float drift).
- Automate data capture (upload image/PDF, async processing).
- Add operational value: analytics, maintenance planning, reminders.
- Keep the architecture clean (DDD style already started) and production-safe.

## Technical goals
- Secure data first (auth, ownership, protected API/UI resources).
- Async-first for slow/external operations (geocoding, OCR/import).
- Strong observability (job status, retry visibility, failure diagnostics).
- Idempotent ingestion (no duplicate receipt imports).

## Prioritized roadmap
1. Security and ownership.
2. Async geocoding of station coordinates.
3. Async receipt import from image/PDF.
4. Back-office management for entities and failed imports.
5. Vehicle maintenance log and planned costs.
6. Advanced dashboards and exports.

## Sprint plan (proposal)

### Sprint 1 - Security and ownership
- User model, authentication for API/UI.
- Ownership isolation on receipts/stations.
- Access control tests and policies.

### Sprint 2 - Geocoding async
- Messenger job for geocoding station address.
- Free provider integration (Nominatim first) with rate-limit friendly behavior.
- Retry/backoff and status tracking.

### Sprint 3 - Import async (image/PDF)
- Upload endpoint + storage + import job entity.
- OCR/parsing pipeline to create receipt/station.
- Manual review workflow for ambiguous parsing.

### Sprint 4 - Back-office
- Admin UI/API for stations/vehicles/reference values.
- Import monitoring and retry/fix actions.

### Sprint 5 - Maintenance domain
- Maintenance events, reminders by date/odometer.
- Planned vs actual maintenance costs.

### Sprint 6 - Analytics and exports
- KPI dashboards (cost/month, avg price/L, consumption trends).
- Export improvements (filtered datasets, XLSX/PDF if needed).

## Definition of done (for each ticket)
- Business behavior implemented and tested.
- PHPStan green, tests green, CI green.
- API behavior documented (inputs/outputs/errors).
- No regression on existing receipt flows.

## Notes for future decisions
- Geocoding free tier limits can change: provider must be behind an interface.
- OCR quality varies by receipt format: keep human review step in scope.
- Do not mix ownership and back-office bypass logic without explicit policy.

## Backlog organization
- Sprint overview files: `docs/backlog/sprint-XX.md`.
- Detailed ticket files: `docs/backlog/tickets/SPX-YYY-*.md`.
- One ticket file = one deliverable with acceptance criteria.
