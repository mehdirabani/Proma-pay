# Evaluation Integrity Report

## V1.1 baseline defect reproduced
- Rows: **1005**
- Exact unique task texts: **158**
- Normalized unique task texts: **158**
- Duplicate rows beyond exact unique tasks: **847**

The V1.1 1005/1005 result is retained only as historical baseline evidence; it is not used for V1.2 Production Readiness.

## V1.2 corpus
- Total: **1610**
- Normalized unique: **1610**
- Concept groups: **64**
- Long-form/multiline heuristic coverage: **1150/1610 (71.43%)**

## Split isolation
- Development ↔ Validation: exact/normalized overlap **0**, concept overlap **0**, near-duplicate pairs ≥0.965 **0**.
- Development ↔ Hidden: exact/normalized overlap **0**, concept overlap **0**, near-duplicate pairs ≥0.965 **0**.
- Validation ↔ Hidden: exact/normalized overlap **0**, concept overlap **0**, near-duplicate pairs ≥0.965 **0**.

The hidden expected labels are evaluator data; runtime routing code does not read them.
