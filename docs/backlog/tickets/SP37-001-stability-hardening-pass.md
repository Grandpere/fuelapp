# SP37-001 - Stability hardening pass

## Summary
Remove the most recurring technical fragilities that still slow validation and support work.

## Delivered scope
- switch XLSX receipt exports to temp-file `BinaryFileResponse` delivery instead of a custom echo-loop stream
- harden the XLSX functional contract around a bounded ZIP prefix read instead of full buffering
- capture the delivery rule in project memory so future streamed-binary work stays stable
