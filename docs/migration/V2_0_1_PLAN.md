# v2.0.1 migration plan — staged after v2.0.0

1. Finish and verify v2.0.0 customer-banner/redesign changes and publish its exact-baseline update.
2. Inventory active Bootstrap CSS/JS consumers and capture rendered-page request/payload baseline.
3. Select current compatible Tailwind build tooling; preserve PHP/Vanilla JS and use compiled local CSS.
4. Migrate shared components and shell, then all active views/plugins with semantic review.
5. Replace Bootstrap JS behaviors, verifying keyboard/focus/modal/dropdown/collapse state.
6. Remove active Bootstrap loading only after components are verified; retain uncertain files for review.
7. Audit cache/security boundaries; implement scoped cache management with admin/CSRF/audit protection.
8. Run financial, authorization, upload, responsive and upgrade/rollback regressions.
9. Measure before/after using the same fixtures/environment; document gaps rather than invent results.
10. Bump to 2.0.1 and build separate full/differential archives from verified 2.0.0.

Release order was explicitly changed by the user. Do not mix incomplete v2.0.1 modernization into the v2.0.0 installer.
