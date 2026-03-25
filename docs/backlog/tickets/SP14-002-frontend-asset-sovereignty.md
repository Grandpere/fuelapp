# SP14-002 - Frontend asset sovereignty and external dependency reduction

## Context
The UI currently uses multiple remote assets (for example fonts and browser libraries). That works, but it weakens CSP options, complicates offline/dev predictability, and creates extra runtime dependencies for a core product surface.

## Scope
- Inventory current remote frontend dependencies.
- Reduce or replace the highest-value external assets with project-managed/local delivery where reasonable.
- Keep the app compatible with current importmap/Twig setup without adding unnecessary complexity.

## Out of scope
- Full design rework.
- Replacing every single third-party asset if the cost is not justified.

## Acceptance criteria
- The app depends on fewer remote frontend assets for core UI rendering.
- Browser delivery becomes more predictable across local/prod.
- The resulting asset model makes stricter CSP realistically achievable.

## Dependencies
- Sprint 14 CSP baseline.

## Status
- todo
