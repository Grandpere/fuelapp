# Station Picker And Import Matching Design

## Goal
Reduce duplicate stations by letting users explicitly select an existing station during manual receipt creation and import review, while keeping the current free-form fallback when no existing station fits.

## Problem
Today both receipt creation and import finalization create or reuse stations only through exact identity matching on `name + street + postal code + city`.
That means:
- slightly different OCR or manual wording creates duplicate stations,
- users cannot easily attach a receipt to an already-known station when the text is close but not exact,
- manual receipt entry and import review solve the same problem twice with no shared selection UX.

## Scope
This feature covers:
- `/ui/receipts/new`
- `/ui/imports/{id}` when the import is in `needs_review`
- a shared station candidate search/read model used by both flows
- handler support for `selected station` first, then fallback to current identity-based create/reuse behavior

This feature does not yet cover:
- favorites
- admin tooling
- automatic silent linking from public fuel stations to internal stations
- generic autocomplete across the whole app

## Product Decisions

### Canonical object
`Station` remains the canonical business object for receipts.
The new picker helps users attach receipts to an existing `Station` instead of trying to favorite or manage public station records directly.

### Selection policy
The UI should never silently replace the extracted or typed station with another one.
Users can:
- choose an existing station candidate explicitly, or
- keep editing the free-form station fields.

### Fallback policy
If no station is selected, the system keeps the current behavior:
- exact identity lookup first,
- create a new station if no exact match exists.

This keeps the feature safe to roll out incrementally.

## User Experience

### Manual receipt creation
In the `Station` section of `/ui/receipts/new`:
- add a station search input for existing stations,
- show a short candidate list with station name + address,
- allow selecting one candidate,
- once selected, keep its id in a hidden field and prefill the editable station fields,
- allow clearing the selection to go back to free-form entry.

The existing station text fields remain visible in v1.
This avoids a fragile JS-only autocomplete dependency and keeps the form understandable when no candidate matches.

### Import review
In `/ui/imports/{id}` for `needs_review`:
- add the same station search/selection block above or alongside the station fields,
- prefill the search from OCR station fragments when possible,
- show best candidates using the parsed/extracted station text,
- let the user confirm one existing station or keep manual corrections.

The receipt lines flow stays unchanged.

### Candidate quality
Candidate matching should be practical, not magical.
Search should consider:
- station name,
- street name,
- postal code,
- city.

Results should prioritize:
- exact postal code matches,
- exact city matches,
- strong textual similarity on name/address,
- recent/known stations only if that is cheap to expose from current data.

We do not need fuzzy scoring science in v1; we need stable, understandable suggestions.

## Architecture

### Shared read model
Introduce a dedicated application reader for station candidates, separate from `StationRepository::findByIdentity()`.
Its role is:
- accept a free-text query plus optional structured hints (`name`, `street`, `postalCode`, `city`),
- return a small ordered list of station candidates for the current user-visible station set.

This is read-only and UI-facing.
It should not mutate station data or decide canonical links by itself.

### Write path changes
Extend the receipt creation/import finalization write path to accept an optional selected `stationId`.
Processing order becomes:
1. if `stationId` is present, load that station and use it,
2. otherwise keep current exact identity lookup,
3. otherwise create a new station.

This keeps one canonical receipt creation path and avoids duplicating station attach rules between manual and import flows.

### Import alignment
Import finalization should call the same receipt creation path with the same optional selected `stationId`.
That gives us one shared station-attachment rule across both flows.

## Security And Ownership
- Selected `stationId` must resolve only through the current user-readable station perimeter.
- Import review must not bypass ownership checks.
- A forged `stationId` should behave like an invalid selection and fail safely.
- CSRF rules stay unchanged because both flows already post through protected forms.

## Error Handling
- If the selected `stationId` is invalid or no longer readable, return a form error instead of silently falling back to another station.
- If the user selected a station and also edited text fields, the selected station wins for attachment; the text fields are treated as visual context only.
- If no station is selected, validation stays close to today’s behavior.

## Testing Strategy

### Unit
- receipt creation handler uses selected `stationId` when present,
- falls back to identity lookup/create when absent,
- rejects unreadable or missing selected station.

### Integration
- station candidate reader returns ordered results from name/address/postal/city search,
- results are scoped to readable stations.

### Functional
- manual receipt form can select an existing station and create the receipt without duplicating it,
- import review can select an existing station and finalize against it,
- free-form fallback still works when nothing is selected.

## Deferred Follow-Ups
Once this exists, the next natural topics become:
- favorites on canonical internal stations,
- explicit internal-station to public-fuel-station linking when confidence is high or user-confirmed,
- broader search/autocomplete reuse in other station-aware screens.

## Recommended Delivery Slice
Implement in this order:
1. shared candidate reader,
2. manual receipt form + handler support,
3. import review reuse,
4. tests and polish.

This gives us usable value early while keeping both flows aligned.
