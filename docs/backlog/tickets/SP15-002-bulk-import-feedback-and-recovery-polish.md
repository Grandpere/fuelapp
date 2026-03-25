# SP15-002 - Bulk import feedback and recovery polish

## Why
Bulk upload already works, but the web UI reports mixed results through generic flashes. As soon as several files are rejected, the feedback becomes noisy and users have to mentally rebuild what happened.

## Scope
- show a structured post-upload summary on `/ui/imports`
- distinguish accepted vs rejected files clearly
- keep filename/source information visible for rejected ZIP entries
- avoid a flood of generic error flashes for normal mixed-result uploads

## Out of scope
- OCR runtime changes
- queue processing changes
- admin-only import tooling

## Acceptance criteria
- a mixed bulk upload produces a readable summary card on the imports page
- accepted and rejected counts stay visible after redirect
- rejected entries show both filename and source context when relevant
- web functional coverage protects the new summary behavior
