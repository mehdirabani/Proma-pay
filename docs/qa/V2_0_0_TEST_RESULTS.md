# Proma Pay V2.0.0 Test Report

Date: 2026-09-22

Scope: Core V2.0.0 installer and differential update package from the local project source.

## Environment

- PHP CLI: 8.2.12
- Isolated MariaDB QA database: `proma_v200_qa`
- QA HTTP server: local PHP built-in server
- Update baseline: `dist/core/PromaPay-v1.5.14.zip`

## Completed Checks

- PHP lint was run by the release gate across the Core PHP files.
- Static release checks passed for version metadata, service worker cache version, settings asset version, and manifest version.
- V2 UI static regression checks passed for:
  - V2 role-aware shell class.
  - V2 CSS and JavaScript assets loaded from the shared layout.
  - Mobile navigation shell presence.
  - Customer dashboard managed banner rendering.
  - Portal banner model, controller, admin view, migration, and upload helper integration.
- Existing financial and UI regression checks from the current suite were run through the local release gate before packaging.
- QA user seeding was verified against the isolated MariaDB database after fixing the local test seed path for the Accounting plugin migrations.
- The release verifier checks each generated archive for safe paths, required runtime assets, forbidden operational files, manifest consistency, checksums, and extracted PHP lint.

## Package Verification Criteria

- Full Core installer must contain the existing template runtime assets under `html/RTL/assets`.
- Full Core installer must not contain `config/database.php`, `.env`, `installed.lock`, `storage/secure_uploads`, development docs, tests, tools, generated graph data, or local QA output.
- Differential update must contain only changed/new Core files, new migrations, update manifest, and update readme.
- Differential update must be tied to the verified V1.5.14 release manifest checksum.
- `SHA256SUMS.txt` must identify exactly the Core installer, V2.0.0 update package, and Proma Accounting plugin package.

## Result

V2.0.0 was packaged for delivery after local release verification. Production infrastructure health was not independently certified by this local build; the generated update package itself is manifest-verified and checksum-verified.
