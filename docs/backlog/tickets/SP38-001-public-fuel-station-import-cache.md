# SP38-001 - Public fuel station import and cache

## Summary
Import the official data.gouv instant fuel station feed into a local cache so future map and admin features do not depend on live external calls.

## Delivered scope
- add a `PublicFuelStation` bounded context with parser, importer and Doctrine persistence
- store station identity, coordinates, address, region/department, services, 24/24 flag and per-fuel price/outage snapshots
- add sync-run tracking for operational diagnostics
- add `app:public-fuel-stations:sync` and `make public-fuel-stations-sync`
- cover parser/import/repository behavior with unit and integration tests

## Out of scope
- public map UI
- admin sync monitor UI
- visited station matching
