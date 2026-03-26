# SP15-003 - Front/admin product friction cleanup

## Why
Several core flows are technically complete but still leave users/admins without a clear next action after an import reaches a terminal state. Small navigation gaps create unnecessary back-and-forth.

## Scope
- add better next-step actions on import detail pages after terminal outcomes
- expose direct links to the created receipt after successful finalization
- expose direct links to the original import when a job is marked duplicate
- keep front/admin detail pages aligned on those outcomes

## Out of scope
- OCR/parser changes
- import list filtering changes
- new admin features beyond existing import support

## Acceptance criteria
- processed import detail shows a direct action to open the created receipt
- duplicate import detail shows a direct action to open the original import
- admin import detail offers the equivalent shortcuts
- functional coverage protects the new terminal-state shortcuts

## Delivered
- front import detail now shows direct next-step actions for `processed` and `duplicate` outcomes
- admin import detail mirrors the same shortcuts
- duplicate detection now also catches semantically identical receipts after OCR, not only byte-identical files
- receipt list rows use the shared `row-link` controller again, restoring consistent detail navigation
