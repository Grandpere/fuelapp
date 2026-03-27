# SP26-002 - Receipt list and detail bulk-flow polish

## Why
- Receipt flows are already rich, but repeated corrections still bounce between the list and detail screens more than they should.
- Users should be able to edit common receipt fields directly from the list and keep moving through a filtered batch from the detail screen.

## Scope
- add direct edit shortcuts on the receipt list rows
- keep filtered-list context visible and navigable from the receipt detail screen
- reuse the existing edit metadata and edit lines flows with safe `return_to` links
- strengthen functional coverage around these repeated receipt workflows

## Out of scope
- a new dedicated bulk edit screen
- changes to receipt write rules or domain validation
- admin/back-office parity

## Validation
- the receipt list exposes direct edit links without forcing a detail-page detour first
- a receipt opened from a filtered list can navigate to the previous/next receipt in that same list context
- the detail screen still keeps the original back-to-list context intact
