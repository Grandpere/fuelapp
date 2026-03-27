# SP28-002 - Admin receipt workflow shortcuts

## Why
- Admin receipts are one of the main support surfaces, but the current back-office flow still forces too many hops to inspect the linked vehicle, station, or originating import.
- Operators should be able to stay anchored in the admin receipt flow while still jumping quickly to the related records that explain or unblock a receipt.

## Scope
- add practical vehicle and station shortcuts on the admin receipts list
- enrich the admin receipt detail page with direct links to linked entities and related imports
- keep edit flows anchored to the originating admin receipt context with safe return paths
- avoid turning the receipt screens into a separate dashboard

## Out of scope
- new admin receipt filters
- admin analytics parity
- front-office receipt changes

## Validation
- admin receipts list exposes the most relevant linked context without opening every row
- admin receipt detail offers direct shortcuts to vehicle, station, and related imports when they exist
- editing an admin receipt returns to the expected list/detail context instead of dropping the operator somewhere generic
