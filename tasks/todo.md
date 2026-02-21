# TODO - SP2-003 Nominatim adapter

## Plan
- [completed] Implement Nominatim geocoder adapter behind `Geocoder`.
- [completed] Add policy-safe request controls (User-Agent + 1 req/s throttling + cache).
- [completed] Wire service configuration/env parameters to use Nominatim in app runtime.
- [completed] Add unit tests for response mapping and transient error handling.
- [completed] Run quality gates and update backlog/docs.
