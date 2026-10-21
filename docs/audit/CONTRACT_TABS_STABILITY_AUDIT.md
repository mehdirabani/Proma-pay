# Contract tabs stability audit — v1.5.10

## Cause

The previous script collected tab panels with a global document selector. A
second tab component or a re-initialized page fragment could therefore be
affected by the contract tab event handler.

## Correction

Initialization is idempotent per tab list, panel lookup is scoped to the
workspace parent/root, and every tab instance receives deterministic IDs,
`role=tablist`, `role=tab`, `role=tabpanel`, aria links and keyboard control.
Hash changes are validated against the permitted local tab list before use.
