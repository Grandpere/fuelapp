# Sprint 38 - Public fuel station directory

## Goal
Add a local public fuel station directory from the official data.gouv fuel price feed, then expose it safely for users and support workflows.

## Tickets
1. `SP38-001` Public fuel station import and cache
2. `SP38-002` Admin public station sync monitor
3. `SP38-003` Public fuel station map
4. `SP38-004` Visited/public station matching

## Scope notes
- Use the data.gouv "prix des carburants - flux instantané v2 améliorée" source as the ingestion source.
- Keep public stations separate from user visited stations; matching comes after both models are reliable.
- Cache locally and serve from our database instead of coupling map rendering to a live external feed.
- Admin coverage is read-only diagnostics first: sync status, counts, freshness and failures, not manual CRUD.
