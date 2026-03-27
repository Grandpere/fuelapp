# SP27-002 - Import-to-receipt continuity pass

## Why
- The import flow is already much clearer than before, but terminal states still leave some value on the table when a receipt already exists.
- Once an import is `processed` or `duplicate`, the user often wants to continue from the actual receipt context instead of staying on the import page.

## Scope
- add a lightweight continuity block on import detail when the import already points to a receipt
- expose direct shortcuts from that block toward the linked receipt and nearby context when available
- keep the change focused on clearer handoff, not on redesigning the import detail page

## Out of scope
- admin/back-office parity
- new import statuses or queue rules
- changing the finalize/reparse business flow

## Validation
- processed imports make it obvious that the next useful step is the created receipt
- duplicate imports with a linked receipt point clearly to the existing receipt
- when linked vehicle or station context exists, it is reachable in one click from the import detail page
