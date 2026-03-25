# SP13-001 - Analytics demo dataset and chart dashboard polish

## Context
Analytics needed representative demo data and production-grade charts to make the dashboard reviewable before real-world volume exists.

## Scope
- Add a safe demo seed flow for analytics.
- Improve analytics chart rendering and interactions.
- Keep dashboard readable with and without JavaScript.

## Out of scope
- New analytics business metrics.

## Acceptance criteria
- Demo dataset can populate the analytics dashboard on demand.
- Dashboard charts are readable, interactive, and stable.
- Existing analytics flows remain functional.

## Technical notes
- Chart.js is used directly via importmap/Stimulus.

## Dependencies
- Existing analytics read model and dashboard pages.

## Status
- done
