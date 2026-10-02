# FinTech Evaluation Report

## Measured
- Development critical FinTech recall: **100.0%**
- Validation critical FinTech recall: **100.0%**
- Hidden critical FinTech recall: **100.0%**
- Hidden FinTech category: **36/36 PASS**

## Post-freeze blind red-team
FinTech domain: **63/72 = 87.5% recall**.

Failures cluster around noisy descriptions of balance races, late/out-of-order events, partial refunds, reconciliation, credit limits and Finglish retry/double-debit wording. High-risk finance remains capped at supervised editing (A2).
