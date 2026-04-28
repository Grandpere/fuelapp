# SP38-004 - Visited/public station matching

## Summary
Connect user visited stations to public stations where confidence is high enough.

## Delivered scope
- match by coordinates first, then address context when coordinates are unavailable
- show public fuel metadata around a user's known station when matched
- avoid automatic destructive merges; keep user data distinct from public data
