# SP26-001 - Dashboard drill-down polish

## Why
- The front dashboard already highlights urgent work, but it still behaves more like a compact summary than a true launchpad.
- Users should be able to jump from the dashboard into the right import, receipt, maintenance, or fleet screen with fewer intermediate clicks.

## Scope
- enrich dashboard cards with clearer next-step hints
- add more contextual actions on recent receipts and import snapshot rows
- add a small "drill down by area" section that points straight at the right hub
- keep the dashboard lightweight and reuse existing route flows with safe `return_to` paths

## Out of scope
- a new dashboard-specific workflow engine
- admin/back-office parity
- redesigning the main visual language of the dashboard

## Validation
- the dashboard exposes clearer next-step actions from urgent items, recent receipts, and recent imports
- users can jump straight from the dashboard into edit/follow-up flows without losing context
- the dashboard still works as a compact overview when the account is mostly empty
