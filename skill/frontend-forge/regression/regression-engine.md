# Regression Engine

Protected baselines may include:
- architecture/public API
- visual snapshots
- behavior tests
- performance budgets
- accessibility checks
- bundle/resource budgets

Decision:
1. compare current vs baseline
2. classify intended vs unintended delta
3. block unintended blocker regression
4. repair or revert
5. update baseline only after explicit validation

No baseline update may be used to hide a failure.
