# SP1-001 - User model and ownership columns

## Context
Resources are not user-scoped yet.

## Scope
- Add `User` domain/entity and persistence.
- Add `owner_id` (or equivalent) on receipts.
- Backfill strategy for existing unowned receipts.

## Out of scope
- Login forms and token issuance.

## Acceptance criteria
- Receipts are linked to owner identity (transition phase can be nullable).
- Doctrine migrations apply cleanly.
- Existing datasets can be claimed via operational command.

## Dependencies
- None.

## Status
- done
