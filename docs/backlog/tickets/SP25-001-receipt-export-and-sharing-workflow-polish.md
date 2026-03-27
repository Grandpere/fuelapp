# SP25-001 - Receipt export and sharing workflow polish

## Why
Receipt exports are useful operationally, but the XLSX path already showed memory fragility and the generated files are still harder to identify than they should be once they leave the app.

## Expected outcome
- XLSX exports are more resilient under normal front-office usage.
- Exported filenames reflect the current scope well enough to be share-friendly.
- Receipt and analytics screens make it explicit that exports keep the active filters.

## Notes
- Front-office only for now.
- No admin parity needed at this stage.
