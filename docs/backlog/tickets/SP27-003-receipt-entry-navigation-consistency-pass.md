# SP27-003 - Receipt entry navigation consistency pass

## Why
- Most of the front-office now opens receipt creation as a dedicated page, but a few hubs were still mixing in inline receipt entry.
- That inconsistency is exactly the kind of small front friction Sprint 27 is supposed to remove before shifting effort to admin/back-office work.

## Scope
- remove the remaining `receipt_form_frame` receipt-entry shortcuts from front hubs and empty states
- keep the visible actions the same, but normalize them to full-page navigation
- update functional coverage so we do not regress toward mixed inline vs full-page receipt entry behavior

## Out of scope
- changing receipt form fields or submission behavior
- admin/back-office parity
- redesigning dashboard, vehicle, station, or receipt pages

## Validation
- dashboard, vehicle, station, and receipt empty-state shortcuts all open the dedicated receipt creation page
- no front-office page in this scope still relies on `receipt_form_frame` for receipt creation
