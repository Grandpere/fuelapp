# SP13-003 - Light/Dark theme toggle

## Context
The horizontal UX pass improved layout consistency, but the current visual language is effectively dark-only. A proper theme toggle keeps the existing mood available while making the product more comfortable for users who prefer a lighter, more neutral UI.

## Scope
- Refactor shared UI tokens so dark/light themes are driven by semantic CSS variables.
- Add a persistent theme toggle in the common shell with system-preference fallback.
- Adjust high-visibility front/admin screens so the light theme remains readable and coherent.

## Out of scope
- Adding more than two themes.
- Per-user server persistence or profile settings.
- Full visual redesign of every page.

## Acceptance criteria
- Users can switch between dark and light themes from the UI.
- The selected theme persists across navigation and reloads.
- Key front/admin screens remain readable and visually coherent in both themes.

## Technical notes
- Keep the current palette as the dark baseline.
- Apply the theme before paint to avoid flash/flicker.
- Prefer shared semantic tokens over per-page overrides.

## Dependencies
- Existing Sprint 13 layout harmonization work.

## Status
- in_progress
