# SP20-001 - Import review productivity shortcuts

## Goal
Make front-office import review faster when several imports need attention in a row.

## Scope
- expose previous/next queue context for `needs_review` imports
- add a direct "finalize and open next" path
- surface lightweight keyboard shortcuts on the review page
- keep the implementation front-office only for now

## Out of scope
- admin review queue shortcuts
- OCR/parser changes
- changing import business statuses or retry policy
