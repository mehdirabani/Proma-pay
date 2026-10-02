# Security Evaluation Report

## Measured
- Development critical Security recall: **100.0%**
- Validation critical Security recall: **100.0%**
- Hidden critical Security recall: **100.0%**
- Hidden Security category: **56/56 PASS**

## Post-freeze blind red-team
Security domain: **82/96 = 85.42% recall**.

This gap is material. Failures include distractor-heavy descriptions of credential exposure, callback authentication, open redirect, XXE, mass-assignment/privileged-field mutation, object ownership, upload safety and Persian/Finglish descriptions. V1.2 therefore does **not** claim production-grade Security generalization.
