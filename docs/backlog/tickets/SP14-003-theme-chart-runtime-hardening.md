# SP14-003 - Theme/chart runtime hardening and test coverage

## Context
The new theme toggle and analytics charts work well, but they add client-side coordination that should be hardened against navigation/runtime edge cases and protected by explicit regression tests.

## Scope
- Consolidate theme runtime behavior so updates are deterministic across navigation and partial reloads.
- Harden chart redraw/update behavior on theme changes and page lifecycle events.
- Add targeted regression coverage for theme persistence and chart refresh behavior.

## Out of scope
- New analytics features.
- New visual redesign work.

## Acceptance criteria
- Theme changes are applied consistently without requiring manual refreshes.
- Chart screens keep correct colors/labels after theme switches and navigation.
- Regressions are covered by tests instead of manual-only validation.

## Dependencies
- Sprint 13 theme toggle and analytics chart runtime.

## Status
- todo
