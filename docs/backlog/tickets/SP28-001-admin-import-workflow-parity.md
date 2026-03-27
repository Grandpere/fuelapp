# SP28-001 - Admin import workflow parity

## Why
- The front-office import flow now gives clear next actions, safer linked-record handling, and less guesswork on terminal states.
- The admin import flow still behaves more like a raw inspection screen, which slows support and recovery work exactly where operators need speed.

## Scope
- add list-level admin import shortcuts that reflect the real next action for each status
- expose quick status pivots and a small follow-up block on the admin imports list
- suppress dead links on the admin import detail page when the linked receipt or original import no longer exists
- keep the detail page oriented toward triage and support instead of mirroring every front-office affordance

## Out of scope
- admin receipt workflow parity
- admin maintenance parity
- redesigning the whole admin visual language

## Validation
- admin imports feels closer to a work queue than a raw dump
- processed and duplicate imports only expose links that still resolve
- support can jump faster from the admin list to review, failure inspection, or the linked receipt when it still exists
