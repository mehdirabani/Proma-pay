# Contract Print Typography

Contract content and print typography are separate. `assets/css/components/contract-print.css` is the authoritative, scoped stylesheet. The profile emits CSS variables in explicit `px` and `mm` units.

The official compact profile locks ordinary text at a maximum of 6px and headings/important clauses at a maximum of 7px. Paragraph, heading, list, table, guarantor, and signature browser defaults are reset only inside the contract scope. Inline styles are removed by the renderer and rejected during template validation.

Large tables may continue on another page. `thead` repeats and individual rows avoid splitting; the complete table is never marked `break-inside: avoid`.

