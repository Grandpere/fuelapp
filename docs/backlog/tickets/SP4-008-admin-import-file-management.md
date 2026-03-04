# SP4-008 - Admin import file management (delete only)

## Context
Admin needs lightweight operational controls on uploaded import files/jobs.

## Scope
- Add `delete` action in admin UI to remove import job and its stored file.
- Keep auditability constraints explicit (who/when for admin action).

## Out of scope
- Receipt deletion/edit from admin import screen.
- Full workflow engine for import triage.

## Acceptance criteria
- Admin can delete a stored import file + related import job safely.
- Non-admin users cannot access these actions.

## Dependencies
- SP4-003, SP4-004.

## Status
- done
