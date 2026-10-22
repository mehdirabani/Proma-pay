# Release artifact layout

Release and update files are grouped by purpose while keeping the paths used by the application and release tools stable.

```text
dist/
├── candidates/v<version>[-<build-id>/]  Candidate inputs and QA builds
├── checksums/                           Shared checksum catalog
├── core/                                Stable core installer archives
├── installation-backups/                Preserved installation snapshots
├── plugins/                             Stable plugin packages consumed by release tools
│   └── pre-release/<plugin>/            Non-stable plugin builds
├── releases/v<version>/                 Candidate delivery bundles and matching .sha256 files
├── staging/                             Temporary release staging copies
└── updates/                             Stable in-app update packages and manifests
```

`core/` and `updates/` remain flat because build, verification, and updater code use those canonical paths. Candidate delivery bundles are written to `releases/v<version>/`; the immutable bundle filename and its adjacent SHA-256 sidecar retain the candidate build identifier. Old bundles stay in their version folders for traceability.

Stable plugin archives stay directly in `plugins/` for the release builder and verifier. PromaPartnershipLedger alpha builds are stored under `plugins/pre-release/PromaPartnershipLedger/` so they are not included as stable release inputs. Installation backups, staging files, and candidate QA evidence are retained.
