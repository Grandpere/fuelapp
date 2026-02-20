# SP3-003 - Async pipeline orchestration with Messenger

## Context
Import must not block request-response cycle.

## Scope
- Dispatch import processing message after upload.
- Implement handler orchestration steps.
- Configure retries/dead-letter for failures.

## Out of scope
- Provider-specific OCR details.

## Acceptance criteria
- API response is immediate after upload.
- Processing is fully async and status-driven.

## Dependencies
- SP3-001, SP3-002.

## Status
- todo
