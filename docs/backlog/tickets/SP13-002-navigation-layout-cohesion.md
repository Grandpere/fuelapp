# SP13-002 - Navigation and layout cohesion pass

## Context
The core flows work well, but several front/admin pages still feel like separate iterations of the product because headers, actions, filters, and table layouts are not fully aligned.

## Scope
- Add/extend shared utility classes for page headers, action groups, filter rows, table sizing, and status pills.
- Remove high-visibility inline style fragments from key front/admin templates.
- Align related navigation/action affordances between receipts, imports, vehicles, maintenance, and core admin lists.

## Out of scope
- New business behavior.
- Full visual redesign or component library migration.

## Acceptance criteria
- Key front/admin list/detail pages use the same layout/action language.
- Tables/filters/actions no longer rely on repeated inline style fragments for standard patterns.
- Navigation between related sections feels clearer and more consistent.

## Technical notes
- Prefer additive utility classes in shared layouts over page-specific CSS duplication.
- Keep the pass incremental and focused on the most visible screens.

## Dependencies
- Existing base layout, admin layout, and Sprint 13 analytics changes.

## Status
- in_progress
