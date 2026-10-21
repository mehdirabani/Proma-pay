# V1.3.1 Contract Print And Template Version Report

1. Root cause of oversized typography: V1.3.0 used 10.5pt body text and millimetre spacing.
2. Root cause of extra pages: large type, 1.45 line-height, tall header/table/signature reserves compounded vertically.
3. Final body font: 6px, weight 400.
4. Final heading font: 7px, weight 700.
5. Final important-clause font: 7px, weight 700.
6. Final line height: body/important 1.22; heading 1.2; table 1.15.
7. Final paragraph spacing: 1px.
8. Header compactness: 8.5px number, centered 30x11mm logo, 8px title.
9. Table compactness: 5.5px text, 6px headings, 1px 2px cells.
10. Signature compactness: 6mm top gap and 15mm minimum height.
11. Important parser: balanced inline/full markers after escaping and sanitization.
12. Editor help: syntax help and cursor-aware insert button added.
13. A4 preview: uses the production renderer, CSS, profile, and margins.
14. Diagnostics: page count, boundaries, measured component heights, and warnings.
15. Delete behavior: physical deletion only for verified unreferenced non-current versions.
16. Protected rules: active, published-current, referenced, and finalized versions cannot be deleted.
17. Archive behavior: retained, hidden by default, restored only as a new draft.
18. Permissions: view/create/edit/publish/archive/delete/purge and print view/manage keys verified server-side.
19. Audit logs: profile, parser, validation, archive, delete, and restore events include actor/reason/IP.
20. Migrations: archive actor/time and audit IP added idempotently; legacy compact settings migrated.
21. Files modified: profile, renderer, service, controllers, settings views, preview, CSS/JS, layout, release metadata.
22. Files created: migration, audit, five contract documents, release notes, report, and V1.3.1 tests.
23. Tests executed: PHP 8.2 lint, static renderer/security/profile tests, live browser computed-style checks, 240-row table flow, Chrome/Edge PDF, and archive validation.
24. Tests passed: 6px body, 7px/700 heading, 5.5px table, one-page sample, three-page stress table, repeated `thead`, row break protection, and zero browser console errors.
25. Failed tests: zero at release packaging time.
26. Chrome print result: one A4 page at 209.89 x 297.01mm with browser scale 100%.
27. Edge print result: one A4 page at 209.89 x 297.01mm with browser scale 100%.
28. Save-as-PDF result: one page; rendered PNG inspection found no clipping, overlap, missing text, or unwanted headers/footers.
29. Example contract result: the representative legal template, item/installment tables, clauses, and signatures fit one A4 page.
30. Known limitation: exceptionally long legal text or large installment tables correctly continue to later pages rather than being artificially scaled.
