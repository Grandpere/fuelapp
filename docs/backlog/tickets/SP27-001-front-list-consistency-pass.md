# SP27-001 - Front list consistency pass

## Why
- The main front-office lists were improved over several sprints, but some interaction patterns still drift from one page to another.
- Users should not have to relearn whether a list opens inline, uses `Open` versus `Detail`, or carries extra visual chrome that other list screens do not need.

## Scope
- align the main front lists on direct navigation instead of inline receipt creation shortcuts
- harmonize action labels and row-level expectations where the remaining differences add friction
- simplify the stations list so it behaves more like the other worklists
- keep the changes lightweight and avoid rebuilding the screens

## Out of scope
- admin/back-office parity
- new filters or new data panels
- redesigning the overall visual language of list screens

## Validation
- vehicle, station, receipt, and import lists feel closer in interaction style
- "Add receipt" from list pages navigates consistently instead of mixing inline and full-page behavior
- the stations list no longer carries extra dashboard-like chrome compared with the other front worklists
