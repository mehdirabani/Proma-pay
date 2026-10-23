# Release artifact layout

Release and update files are grouped by purpose while keeping the paths used by the application and release tools stable.

```text
dist/
├── candidates/
│   ├── v<active-version>/                Active candidate inputs and QA builds
│   └── archive/v<major>/v<version>[-<snapshot-id>]/  Historical candidate builds grouped by major version
├── checksums/
│   └── archive/
│       ├── v<major>/v<version>/         Historical checksum catalogs
│       └── multi-version/                Legacy catalogs spanning releases
├── core/                                Active stable core installers and reports
│   └── archive/v<major>/v<version>/     Historical Core installers and reports
├── installation-backups/                Preserved installation snapshots
├── plugins/                             Stable plugin packages consumed by release tools
│   └── pre-release/<plugin>/            Non-stable plugin builds
├── releases/
│   ├── v<active-version>/                Active candidate delivery bundle and matching .sha256
│   └── archive/v<version>/              Previous candidate delivery bundles grouped by version
├── staging/                             Temporary release staging copies
└── updates/                             Active in-app update packages and manifests
    └── archive/v<major>/v<version>/     Historical Update packages and manifests
```

New stable Core and Update packages continue to be built into the canonical `core/` and `updates/` folders. Historical packages and their version-specific reports/manifests are grouped under `archive/v<major>/v<version>/`; release tools resolve either active or archived paths.

Candidate delivery bundles are written to `releases/v<version>/`; the immutable bundle filename and its adjacent SHA-256 sidecar retain the candidate build identifier. Historical candidates are grouped under `candidates/archive/v<major>/` and previous delivery bundles under `releases/archive/v<version>/`, while new builds use their direct version paths.

Stable plugin archives stay directly in `plugins/` for the release builder and verifier. PromaPartnershipLedger alpha builds are stored under `plugins/pre-release/PromaPartnershipLedger/` so they are not included as stable release inputs. Installation backups, staging files, and candidate QA evidence are retained.
