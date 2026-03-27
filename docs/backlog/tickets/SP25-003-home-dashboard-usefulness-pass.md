# SP25-003 - Home/dashboard usefulness pass

## Why
- Front-office users now have several stronger workflow hubs, but no compact overview screen to start from.
- The next useful step is not a large homepage redesign; it is a small dashboard that points directly at follow-up work.

## Scope
- add `/ui/dashboard` as a lightweight front-office overview
- surface urgent import and maintenance follow-up
- show recent receipts and import activity
- expose quick actions into receipts, imports, maintenance, analytics, vehicles, and stations
- add topbar navigation to make the dashboard a real entry point

## Out of scope
- admin/back-office parity
- changing business rules or introducing new read models
- large visual redesign of every front page

## Validation
- dashboard renders meaningful follow-up states when data exists
- dashboard empty states still give clear next steps
- topbar exposes the dashboard link for normal users and admins
