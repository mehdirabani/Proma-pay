# V1.3.0 Contract Print And Template Version Audit

## Baseline

- Body text: `10.5pt`, line-height `1.45`.
- Internal headings: `1.04em`, weight `800`, line-height `1.35`.
- Paragraph spacing: `1.5mm`.
- Section spacing: `2.5mm` above and `1.5mm` below.
- Table text: `9.5pt`, line-height `1.3`.
- Table cells: `1.2mm 1.8mm` padding.
- Page margins: `6mm 10mm 10mm 10mm`.
- Logo: up to `38mm x 15mm`; the header also adds millimetre padding and divider spacing.
- Signature: `12mm` top gap and `22mm` minimum box height.
- A4 output: native A4 at 100%; no print scaling is used.
- Footer: optional, but adds another `6mm` of margin and padding when enabled.

## Renderer And Parser

`ContractTemplateRenderer` supports plain blocks, lists, the headings `ماده/تبصره/بند`, and a restricted HTML allow-list. It sanitizes inline styles, scripts, event handlers, links, media, and form controls. V1.3.0 has no Markdown-like important-clause support: `**` is printed literally in both plain and structured HTML sources.

The real print view and settings preview both load `assets/css/components/contract-print.css` and `ContractPrintProfile`, but the profile stores body and table sizes in points and most vertical spacing in millimetres. The editor controls content only; typography is controlled by the print stylesheet. This is why editor formatting expectations can differ from the resulting document.

## Version Storage And References

`contract_templates.current_version_id` identifies the active version. `contract_template_versions` stores immutable source, format, hash, status, actor, publish date, and supersede date. V1.3.0 has no archive/delete actor fields and no safe delete operation.

Generated documents reference `template_version_id` in `generated_contract_documents`. Historical/finalized document versions reference it in `contract_document_versions`; finalized rows are legally protected. The existing versions screen does not calculate or display those reference counts.

## Root Causes

The second page is primarily caused by a body font approximately 14 CSS pixels (`10.5pt`), `1.45` line-height, millimetre paragraph/section spacing, a tall header, padded tables, and a `12mm + 22mm` signature reserve. These values compound vertically even when the legal text is moderate.

Typography changes do not affect output when they are saved as editor-only rich text expectations, while the dedicated print CSS overrides them. Conversely, legacy inline styles can compete with the profile unless they are sanitized and the print selectors are scoped precisely.

## Implementation Plan

1. Introduce the official `قرارداد فشرده یک‌صفحه‌ای` profile with 6px body, 7px headings/important clauses, and the requested A4 measurements.
2. Add a safe balanced `**` parser for plain text and sanitized structured HTML.
3. Use the same renderer/profile for live diagnostics and actual printing.
4. Add migration-backed archive metadata and reference-aware version actions.
5. Add permissions, CSRF-protected controllers, reasons, confirmations, and audit events.
6. Group every installed plugin's pages under one sidebar submenu.
7. Verify static checks, PHP syntax, renderer security, A4 PDF output, release archives, and update manifest.

## Affected Areas

`ContractPrintProfile`, `ContractTemplateRenderer`, `ContractTemplateService`, contract settings controllers/views, print/preview views, contract print CSS, plugin menu registration/layout, migrations, tests, release documentation, and release packaging.
