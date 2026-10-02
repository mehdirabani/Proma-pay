# Evaluation Model — v1.2

Development, Validation and Hidden are split by semantic concept group. Release gates reject exact/normalized/concept overlap and strict near-duplicate cross-split pairs. Runtime is frozen before Hidden. Hidden is evaluated once and does not feed fixes back into v1.2. A separate 200-task blind red-team is executed after freeze. External Agent A/B and tokenizer measurements are distinct and remain unverified when adapters are absent.
