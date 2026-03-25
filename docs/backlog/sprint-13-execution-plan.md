# Sprint 13 Execution Plan

## Objective
Finish the horizontal UX polish that makes the app feel like a single product instead of a set of feature screens.

## Step 1 - Analytics demo and chart readability
- Seed realistic demo data for dashboard review.
- Replace fragile custom chart rendering with a maintained chart library.
- Stabilize analytics interactions and no-JS fallbacks.

## Step 2 - Navigation and layout cohesion
- Consolidate shared page/header/action patterns in base/admin layouts.
- Remove remaining high-visibility inline styles from front/admin views.
- Align filter blocks, table wrappers, action rows, and cross-links between related sections.

## Step 3 - Theme flexibility
- Add shared light/dark semantic tokens at layout level.
- Provide a persistent UI toggle with system-preference fallback.
- Validate high-visibility screens in both modes, especially analytics and maintenance dashboards.

## Validation
- Non-functional quality gates on every ticket.
- User-run `make phpunit-functional` after visible web changes.
