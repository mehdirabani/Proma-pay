# Compact A4 Print Profile

The official profile is `official_compact`, shown as «قرارداد فشرده یک‌صفحه‌ای».

| Setting | Value |
| --- | --- |
| Body | 6px / 400 / 1.22 |
| Internal heading | 7px / 700 / 1.2 |
| Important clause | 7px / 700 / 1.22 |
| Paragraph gap | 1px |
| Table | 5.5px / 1.15 |
| Table heading | 6px / 700 |
| Cell padding | 1px 2px |
| Page margin | 5mm 8mm 7mm 8mm |
| Logo maximum | 30mm x 11mm |
| Signature | 6mm top / 15mm minimum |

The profile is centralized in `ContractPrintProfile`. Generated bodies do not store typography. Existing documents therefore receive corrected typography when printed, without regeneration. Browser print scale remains 100% and the stylesheet does not use `zoom` or `transform: scale()`.

