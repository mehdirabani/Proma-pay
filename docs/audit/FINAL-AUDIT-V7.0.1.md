# Proma Pay V7.0.1 Candidate — Audit Note

Date: 2026-10-14 (Asia/Tehran)

## Scope reviewed

- Release identity and application/asset version synchronization.
- Differential update compatibility with the exact V7.0.0 Core archive.
- Update manifest requirements used by the in-application updater.
- Preservation of database configuration, plugins, storage, and uploaded data.

## Findings addressed

1. **V7.0.0 installations could not see a newer V7 patch.** The Core version is now `7.0.1`, and the candidate update manifest sets V7.0.0 as its minimum version and exact baseline. The package can be uploaded to the in-app Updates page and is recognized as an upgrade.
2. **Cached UI assets could remain on the prior version after upgrade.** The asset cache version now matches V7.0.1 so the browser requests current styles and scripts.
3. **Upgrade payload must not replace operational state.** The update is generated from the exact V7.0.0 release manifest and contains only changed runtime files; no migration is included. The eight historical V5 plugin-set metadata files already present in that baseline remain in the Core inventory because the updater cannot delete files.

## Release evidence and limitations

- Core version: `7.0.1`; exact differential baseline: `7.0.0`; plugin API: `1.1`; release channel: `candidate`.
- Candidate package structure, file checksums, manifest, and baseline identity: checked by the packaging verifier.
- Application behavior, browser/UI flows, database upgrade execution, and external providers: **not run**.
- Production availability: **not certified**.

This note covers the V7.0.1 update package and version metadata. The V7.0.0 feature scope is documented in [V7.0.0 release notes](../releases/V7.0.0.md) and [V7.0.0 audit](FINAL-AUDIT-V7.0.0.md).
