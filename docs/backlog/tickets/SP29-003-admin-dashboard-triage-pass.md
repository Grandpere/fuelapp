# SP29-003 - Admin dashboard triage pass

## Why
- The current admin dashboard mostly exposes totals and navigation links, but it does not help support users decide what needs attention first.
- After Sprint 28, admin imports, receipts, and maintenance flows are much more useful individually; the dashboard should now point operators into those flows quickly.

## Scope
- surface admin triage metrics around failed imports, imports needing review, and due maintenance reminders
- add recent activity blocks for receipts and imports so the admin hub feels alive and actionable
- add direct drill-down shortcuts into the most useful support queues
- keep the implementation lightweight by reusing existing repositories and admin routes

## Out of scope
- redesigning the whole admin shell
- advanced analytics or owner-level dashboard segmentation
- introducing new back-office workflows not already available elsewhere in admin

## Validation
- the admin dashboard clearly highlights imports needing attention and due maintenance work
- recent receipts and recent imports are visible without leaving the dashboard
- the most common support queues are one click away from the dashboard
