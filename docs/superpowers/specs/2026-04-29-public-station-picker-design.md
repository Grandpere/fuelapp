# Public Station Suggestions For Receipt And Import Picker Design

## Goal
Extend the new station picker so users can choose a public fuel station suggestion when no internal `Station` is a good fit, while still ending with a canonical internal `Station` attached to the receipt.

## Problem
`SP40-001` solved duplicate reduction only inside the current user-visible internal station set.
That leaves a common gap:
- a real-world station exists in the public fuel cache,
- the user has not used it yet or OCR/manual wording does not map to an existing internal station,
- the picker therefore returns nothing useful even though we already know the station from the public dataset.

If we stop there, users still need to manually retype or manually validate a station we already have in our cache.

## Scope
This feature covers:
- `/ui/receipts/new`
- `/ui/imports/{id}` when the import is in `needs_review`
- extending the station suggestion read model to also return public fuel station candidates
- converting a chosen public station suggestion into a canonical internal `Station` during save/finalize

This feature does not yet cover:
- a persistent explicit `Station <-> PublicFuelStation` link model
- favorites
- admin tooling for public-station selection history
- background reconciliation jobs

## Product Decisions

### Canonical object stays `Station`
Receipts continue to point only to internal `Station` entities.
`PublicFuelStation` remains a suggestion source, not a business aggregate attached directly to receipts.

### Public suggestion behavior
When the user selects a public station suggestion:
- the UI records the selected suggestion explicitly,
- the actual internal `Station` is reused or created only on `Save receipt` / import finalize,
- the user does not have to manually retype the station fields.

### No durable public link in v1
We do not persist an explicit durable mapping between `Station` and `PublicFuelStation` yet.
Reason:
- the immediate product value is in reducing data entry and duplicates,
- the explicit mapping model adds schema and lifecycle decisions we do not yet need to deliver this value cleanly.

## User Experience

### Manual receipt creation
The `Station` block in `/ui/receipts/new` becomes a suggestion picker with three explicit states:
- `Existing station` selected
- `Public station suggestion` selected
- `Do not use a suggestion`

After `Find matches`, the UI can show two sections:
- `Existing stations`
- `Public station suggestions`

Each candidate is rendered as a radio card with a source badge:
- `Existing`
- `Public`

When a public suggestion is selected:
- show a small helper line such as `This will create or reuse an internal station when you save.`
- visually de-emphasize the manual station fields below so users understand they are on an alternate path.

If `Do not use a suggestion` is selected:
- the manual fields return to normal emphasis,
- the current free-form behavior remains available.

### Import review
`/ui/imports/{id}` mirrors the same model:
- existing stations first,
- public station suggestions second,
- explicit radio choice,
- helper text when a public station is selected.

OCR/extracted station fields still remain visible for auditability and manual correction.
The receipt lines flow remains unchanged.

### Suggestion ordering
When both internal and public suggestions exist:
- internal stations come first because they already match the user’s canonical history,
- public stations come after them,
- within each section, stronger postal/city/name/address matches rank higher.

## Architecture

### Unified suggestion reader
Introduce a UI-facing unified suggestion reader that aggregates:
- current internal `Station` suggestions
- public fuel station suggestions

Each suggestion row should carry:
- `sourceType` (`station` or `public`)
- `sourceId`
- display name
- street name
- postal code
- city
- optional coordinates

The existing internal station search reader can remain the internal source, but the UI/controller should consume a higher-level combined read model rather than manually stitching two independent readers in templates.

### Public suggestion search source
Public suggestions should query the local cached `PublicFuelStation` dataset, not external APIs.
Search criteria should follow the same practical hints as internal station search:
- free text
- name/address fragments
- postal code
- city

As with the internal station search, this must stay deterministic and lightweight.
We do not need fuzzy matching science; we need stable suggestions that obviously correspond to user intent.

### Save/finalize write behavior
Extend the write path to accept an optional selected suggestion tuple:
- `selectedSuggestionType`
- `selectedSuggestionId`

Processing order becomes:
1. if selected type is `station`, load the internal station and use it
2. if selected type is `public`, load the cached public station and convert it into an internal station by:
   - normalizing `name + streetName + postalCode + city`
   - looking for an existing internal `Station` with that exact identity
   - reusing it if found
   - otherwise creating a new internal `Station` with those values and public coordinates when available
3. if no suggestion is selected, keep the current free-form station behavior

This keeps a single canonical write path while allowing public suggestions to feed it.

### Conversion rules
Public-to-internal conversion in v1 should map only the fields that already belong to internal `Station`:
- name
- street name
- postal code
- city
- latitude micro-degrees
- longitude micro-degrees

We do not import fuels/services metadata into `Station`.
That data remains part of the public cache only.

## Security And Ownership
- Selecting a public suggestion must still end with a receipt attached through the normal authenticated write path.
- Public suggestions are safe to expose because the public station cache is already visible to authenticated users.
- A forged or stale public suggestion id must fail safely with a form error, not a 500.
- Internal station readability checks remain unchanged.

## Error Handling
- Invalid selected internal suggestion: form error, no fallback.
- Invalid selected public suggestion: form error, no fallback.
- Public suggestion disappeared between render and submit: form error with retry path.
- A matching internal station created concurrently: the save flow reuses it instead of duplicating.

## Testing Strategy

### Unit
- selected `station` suggestion uses existing station directly
- selected `public` suggestion reuses matching internal station when identity already exists
- selected `public` suggestion creates internal station when none exists
- invalid public suggestion id raises a safe validation error path

### Integration
- combined suggestion reader returns internal and public suggestions in section order
- public suggestion search respects structured query hints and practical result limits

### Functional
- `/ui/receipts/new` can choose a public suggestion and save a receipt attached to a reused/created internal station
- `/ui/imports/{id}` can choose a public suggestion and finalize successfully
- invalid public suggestion id returns to form with user-facing error
- existing station path and free-form fallback continue to work

## Risks And Trade-Offs
- Without a durable `Station <-> PublicFuelStation` link, we cannot later say with certainty which public source created a station. That is acceptable for this slice because the user-visible value is immediate and the canonical station identity is preserved.
- Suggestion UX becomes more complex with two sources. The UI must clearly label source type and selected behavior, otherwise users will not understand whether they are still expected to fill manual fields.
- Public search could become heavier than internal search. Keep a bounded query/result window and rely on the local cache only.

## Recommended Delivery Slice
1. add a combined suggestion read model with internal + public results
2. support selected suggestion type/id in receipt save flow
3. reuse the same behavior in import finalize
4. update receipt/import UI to clearly distinguish `Existing`, `Public`, and `Do not use a suggestion`
5. add tests and polish the selected-state UX
