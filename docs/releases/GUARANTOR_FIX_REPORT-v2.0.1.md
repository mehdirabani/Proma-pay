# Guarantor compatibility — 2.0.1

No guarantor model, document calculation or authorization changes in this release. Existing guarantor regression checks remain in the release gate.

The integration fixture previously derived multiple identities from one digit string. Replacing non-digits in a hash with the same digit increased collisions across repeated runs, and modifying its last character could also collide within a run. The test now allocates separate numeric identities and checks the isolated QA users table before creation. No production duplicate-identity safeguard was weakened. The final integration run passed existing-guarantor printing, mixed existing/new guarantor rendering, preview/print agreement and immutable contract snapshot checks.
