# SP22-003 - Import queue confidence signals

## Why
The import detail page now explains outcomes well, but the list still forces users to open each import to understand what happened and what they should do next.

## Expected outcome
- `/ui/imports` shows a short, useful signal per row:
  - why an import is blocked or done,
  - what the next likely action is,
  - and a direct shortcut when that action is obvious.

## Notes
- Front-office only for now.
- Admin import triage already has a separate ops-oriented summary and is not required for this ticket.
