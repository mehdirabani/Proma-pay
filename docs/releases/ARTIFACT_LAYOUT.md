# Release artifact layout

Release and update files are grouped by purpose while keeping the paths used by the application and release tools stable.

```text
dist/
├── candidates/
│   ├── v<active-version>[-<build-id>/]  Active candidate inputs and QA builds
│   └── archive/v<major>/v<version>[-<snapshot-id>]/  Historical candidate builds grouped by major version
├── checksums/                           Shared checksum catalog
├── core/                                Stable core installer archives
├── installation-backups/                Preserved installation snapshots
├── plugins/                             Stable plugin packages consumed by release tools
│   └── pre-release/<plugin>/            Non-stable plugin builds
├── releases/
│   ├── v<active-version>/                Active candidate delivery bundle and matching .sha256
│   └── archive/v<version>/              Previous candidate delivery bundles grouped by version
├── staging/                             Temporary release staging copies
└── updates/                             Stable in-app update packages and manifests
```

`core/` and `updates/` remain flat because build, verification, and updater code use those canonical paths. Candidate delivery bundles are written to `releases/v<version>/`; the immutable bundle filename and its adjacent SHA-256 sidecar retain the candidate build identifier. Historical candidates are grouped under `candidates/archive/v<major>/` and previous delivery bundles under `releases/archive/v<version>/`, while new builds use their direct version paths.

Stable plugin archives stay directly in `plugins/` for the release builder and verifier. Pre-release plugin builds are stored under `plugins/pre-release/<plugin>/` so they are not included as stable release inputs. Installation backups, staging files, and candidate QA evidence are retained.
