# Generalization Report — PHPForge X v1.2

## Status
**Partially proven; not Production Ready.**

## Evidence
- New evaluation corpus: **1,610 / 1,610 normalized-unique tasks**, across **64 semantic concept groups**.
- Concept-group split isolation: **0 concept overlap** between Development, Validation, and Hidden.
- Hidden Holdout was run **once after runtime freeze**: **262/262 PASS**.
- Hidden module recall: **100.0%**; module precision: **91.59%**.
- Hidden Persian/mixed: **68/68 PASS**.
- Hidden OOD: **25/25 PASS**.
- Hidden Negation: **17/17 PASS**.

## Independent post-freeze challenge
The separate 200-task blind red-team scored **173/200 = 86.5%**. This set was not used to tune the frozen runtime.

Failures: Security **14**, FinTech **9**, Database **4**. The dominant failure pattern was distractor sensitivity: **21** failures contained the unrelated happy-path testing distractor and **20** contained README/framework noise.

## Conclusion
V1.2 demonstrates much stronger leak-free holdout behavior than V1.1, but the blind red-team proves that unseen noisy phrasing can still divert routing toward Testing/Performance/API. Therefore the answer to “will arbitrary unseen wording remain reliable?” is **not yet proven strongly enough for Production Ready**.
