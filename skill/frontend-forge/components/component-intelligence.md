# Component Intelligence

Every component records:
Responsibility, Inputs, Outputs, State, Dependencies, Variants, Consumers,
Accessibility, Performance Risks, Testing Strategy.

Extraction rule:
extract only when reuse, complexity, testability, or domain boundary justifies it.
Do not abstract solely because markup is visually similar.

Health signals:
- public API churn
- prop count
- state complexity
- render fan-out
- duplicate variants
- accessibility debt
- regression frequency
