# SP4-008 - Admin import file management (seen/delete)

## Context
Admin needs lightweight operational controls on uploaded import files/jobs.

## Scope
- Add `seen` marker action for import jobs in admin UI.
- Add `delete` action in admin UI to remove import job and its stored file.
- Keep auditability constraints explicit (who/when for admin action).

## Out of scope
- Receipt deletion/edit from admin import screen.
- Full workflow engine for import triage.

## Acceptance criteria
- Admin can mark an import as seen from list/detail views.
- Admin can delete a stored import file + related import job safely.
- Non-admin users cannot access these actions.

## Dependencies
- SP4-003, SP4-004.

## Status
- todo
