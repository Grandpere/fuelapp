# SP25-002 - Front import recovery shortcuts

## Why
The import list and detail pages already explain statuses well, but recovering from a failed or duplicate import still takes too much manual navigation back to the upload flow.

## Expected outcome
- Failed and ambiguous imports expose direct replacement-upload shortcuts from both the list and detail pages.
- Resolved imports still offer a quick “upload another file” path without losing the current front-office flow.
- The main upload target on `/ui/imports` is easy to jump back to from recovery actions.

## Notes
- Front-office only for now.
- No admin parity needed at this stage.
