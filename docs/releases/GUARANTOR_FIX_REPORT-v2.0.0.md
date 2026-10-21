# Guarantor Regression Report - V2.0.0

V2.0.0 does not introduce a dedicated guarantor data-model change. The release focuses on the V2 UI shell, customer banner management, packaging integrity, and release compatibility.

## Guardrails Kept

- The installer and updater exclude operational customer uploads, legal evidence, and private configuration files.
- Existing contract, customer, financial, and guarantor-related runtime files are included through the normal Core archive inventory unless excluded as operational data.
- The update package is differential from the verified V1.5.14 archive and carries expected previous hashes for changed files.

## Verification

- Existing static and release checks continue to run through the local release gate.
- Generated archives are verified by `tools/verify-release.php` for manifest integrity, forbidden paths, required template assets, checksums, and extracted PHP lint.

## Result

No guarantor-specific schema or behavior change is claimed in V2.0.0. Existing guarantor behavior is preserved by packaging the current Core runtime and by keeping the established installer/update exclusion rules.
