# SP11-003 - UX consistency polish for front/admin critical views

## Context
Core views are functional but still have small visual inconsistencies in actions/buttons/layout that reduce perceived product quality.

## Scope
- Standardize compact action buttons and action rows in list tables.
- Remove repeated inline style fragments where a shared utility class is enough.
- Align front/admin critical pages to common interaction patterns.

## Out of scope
- Full redesign or new component library.
- New business features.

## Acceptance criteria
- Action buttons on critical tables are consistently aligned and sized.
- No regression in existing front/admin flows.
- Templates are cleaner with fewer ad-hoc inline style overrides.

## Technical notes
- Keep changes incremental and compatible with existing style tokens.

## Dependencies
- Existing base layout and list templates.

## Status
- done
