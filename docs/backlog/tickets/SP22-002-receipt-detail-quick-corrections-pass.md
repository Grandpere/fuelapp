# SP22-002 - Receipt detail quick corrections pass

## Why
The receipt detail page already exposes good navigation, but common correction loops still require a few avoidable clicks when data is missing or obviously needs adjustment.

## Expected outcome
- `/ui/receipts/{id}` exposes a compact quick-corrections area for the most common fixes.
- Missing receipt context is called out directly with the right correction shortcut.
- Nearby entities already shown on the page expose direct edit actions when a supported front flow already exists.

## Notes
- Front-office only for now.
- No admin parity needed at this stage.
