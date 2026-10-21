# V1.4.1 Test Matrix

| Suite | Scope | Required result |
| --- | --- | --- |
| PHP lint | Core, tests and changed plugins | 0 syntax failures |
| Static/unit regression | V1.2.6 through V1.4.1, Accounting, Zarinpal | All pass |
| Fresh database integration | Install schema plus V1.4.1 blockers | All pass |
| Legacy database integration | File registry, print, installments and V1.4.0 workflows | All pass |
| HTTP role smoke | Admin, operator, lawyer and customer | Healthy pages and correct 403 responses |
| Browser desktop | 1440 x 900 | No fatal output or document overflow |
| Browser mobile | 390 x 844 | No document overflow; usable role workflows |
| Plugin lifecycle | Accounting install/activate/health/deactivate | Consistent states and migrations |
| Archive verification | Core, update and Accounting ZIP | Safe paths, correct roots, hashes and extracted PHP lint |
| Fresh install/update package | Extracted release artifacts | Required before stable publication |
