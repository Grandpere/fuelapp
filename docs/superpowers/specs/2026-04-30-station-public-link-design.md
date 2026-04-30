# SP40-003 - Station/Public Source Durable Link

## Goal

Persist a durable link from an internal `Station` to the public fuel station source selected by the user, so later product features can rely on an explicit source of truth instead of re-matching heuristics.

This follows `SP40-001` and `SP40-002`, where users can already:
- select an existing internal station,
- select a public station suggestion,
- create or reuse an internal `Station` from that public suggestion.

Today, the app keeps the internal station but forgets which public source was chosen.

## Product Decision

Use a simple one-to-one link on `Station`.

- Add `publicSourceId` on `Station`
- Store the chosen `PublicFuelStation.sourceId` when a public suggestion is used
- Keep `Station` as the canonical business object linked to receipts
- Do not add a separate link table in this version

## Why This Shape

This is the smallest correct model for the current product:
- one internal station should map to one public source in the normal case,
- if legacy duplicates exist, they can be cleaned manually,
- the user already confirmed that multiple valid public links for one station are not an expected product case.

It gives us:
- stable future enrichment from the public cache,
- easier favorites later,
- explicit diagnostics instead of repeated best-effort matching.

It avoids:
- over-designing a many-to-many link table too early,
- keeping important user choice only in transient form flow state.

## Scope

### In scope

- add nullable `publicSourceId` to the `Station` domain + persistence
- when a user selects a public suggestion during:
  - manual receipt creation
  - import review finalization
  store the selected public source on the internal `Station`
- if the target internal station already exists and has no public source, attach it
- if it already has the same public source, keep it unchanged
- if it already has a different public source, fail safely with a clear rule instead of silently overwriting
- add tests for creation, reuse, and conflict handling

### Out of scope

- sync back from public source into station fields
- admin UI for linked/unlinked stations
- bulk relinking tools
- multi-source station linking
- historical audit of link changes beyond normal entity updates

## Admin / Back-office Evaluation

Admin exposure is useful later for:
- support diagnostics,
- legacy cleanup,
- conflict inspection,
- relinking recovery if source data changes.

It is not necessary now because:
- the link is created only through explicit user choice,
- we do not yet have operational workflows depending on mass support of links.

Decision for this ticket:
- defer admin/back-office coverage

Impact of deferring:
- no product blockage,
- only less visibility for support if a legacy conflict appears.

## Domain Rules

### Canonical rule

`Station` remains the only station entity attached to receipts.

`PublicFuelStation` remains:
- a searchable cache,
- a suggestion source,
- and now also a durable external reference stored on `Station`.

### Link write rules

When a public suggestion is selected:

1. Resolve the selected `PublicFuelStation` by `sourceId`
2. Resolve or create the internal `Station` exactly as today
3. Apply link rules:
   - if `station.publicSourceId` is `null`, set it
   - if `station.publicSourceId === selectedSourceId`, do nothing
   - if `station.publicSourceId !== selectedSourceId`, reject with a clear domain/application error

### Why reject conflicts

Silent overwrite would hide legacy/data quality issues.

A conflict likely means one of:
- a legacy duplicate,
- a wrong historical station merge,
- a wrong manual selection.

Rejecting is safer and keeps the model trustworthy.

## Data Model

### Station

Add nullable string field:
- `publicSourceId: ?string`

Constraints:
- nullable
- unique when not null

Rationale:
- one public source should not be attached to multiple internal stations once the model is cleaned
- uniqueness makes accidental duplicate linking visible quickly

## Application Flow Changes

### Receipt creation

Current flow:
- public suggestion selected
- internal station reused or created
- receipt saved

New step:
- after resolving the internal station, persist `publicSourceId`

### Import finalize

Same behavior as receipt creation.

The import path should reuse the same linking rule, not duplicate business logic.

## Errors

Add a clear application error for link conflicts, for example:
- `Selected public station conflicts with the existing linked public source for this station.`

Expected behavior:
- no `500`
- user returns to form/review with flash or validation-style error

## Migration

Add DB migration for:
- `stations.public_source_id` nullable
- unique index on `public_source_id`

No backfill required in this ticket.

Existing stations remain unlinked until a user explicitly creates/reuses them through the public suggestion flow or a future migration/support tool links them.

## Testing Strategy

### Unit

- selecting public suggestion on a newly created internal station stores `publicSourceId`
- selecting public suggestion on an existing matching station without link sets `publicSourceId`
- selecting same public suggestion again is idempotent
- selecting a different public suggestion for a station already linked elsewhere raises the expected error

### Integration

- Doctrine persistence round-trip for `Station.publicSourceId`
- unique constraint behavior on duplicate public source assignment if relevant at repository level

### Functional

- manual receipt creation stores the link
- import finalize stores the link
- conflict path returns user-facing error instead of crashing

## Risks

### Legacy duplicate stations

Risk:
- an old internal station may already represent the same real place differently

Mitigation:
- keep explicit conflict behavior
- do not auto-merge or overwrite in this ticket

### Source drift

Risk:
- public source payload may evolve later

Mitigation:
- only store stable `sourceId` now
- postpone enrichment rules to a dedicated follow-up

## Follow-ups Enabled By This

- station favorites with cleaner canonical data
- admin/support page showing linked vs unlinked stations
- optional enrichment sync from public source to station
- diagnostics when a public source disappears or changes materially
