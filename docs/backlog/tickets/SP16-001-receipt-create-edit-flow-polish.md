# SP16-001 - Receipt create/edit flow polish

## Why
The receipt forms still expose internal units like `mL` and `deci-cents/L`, which makes manual entry feel more technical than the rest of the app.

## Scope
- replace receipt form inputs with human-friendly values (`L`, `€/L`) while preserving integer storage internally
- improve hints/examples on manual receipt creation
- align line editing with the same unit conventions
- keep the change front-office only for now

## Out of scope
- admin receipt form redesign
- receipt list filter redesign
- domain/persistence unit changes

## Acceptance criteria
- manual receipt creation accepts liters and `€/L` values without exposing storage units
- receipt line editing uses the same unit conventions
- backend still persists integer `mL` and integer `deci-cents/L`
- invalid decimal input yields clear validation errors
- unit and functional coverage protect the new parsing behavior

## Admin coverage
- Deferred on purpose for this ticket.
- Why useful later: admin support would keep support/ops flows aligned with front-office ergonomics.
- Why not necessary now: the primary friction is on the everyday user-facing data-entry path.
- Impact if deferred: admin receipt editing still uses the older internal-unit style until a later follow-up.
