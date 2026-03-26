# SP15-001 - Import review multi-line finalization

## Why
The import finalization handler already supports several receipt lines, but both web review screens only expose the first parsed line. That makes legitimate OCR results look incomplete and forces users/admins to abandon the review UI for multi-line receipts.

## Scope
- expose every parsed/import creation line in the user review screen
- expose every parsed/import creation line in the admin review screen
- submit all edited lines through the existing finalize flow
- keep validation errors explicit when one line is partially filled

## Out of scope
- OCR/parser changes
- dynamic add/remove line JS
- API contract changes

## Acceptance criteria
- user review page shows all available lines from payload
- admin review page shows all available lines from payload
- finalize controllers accept multiple lines from the web form
- multi-line finalization creates a receipt with all submitted lines
- front/admin functional coverage protects the new behavior
