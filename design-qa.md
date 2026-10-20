# V7.0.2 customer experience visual QA

## Comparison record

- Source visual truth: the user-provided dashboard screenshot at `C:\Users\pgmme\AppData\Local\Temp\codex-clipboard-bc379239-152b-4b0b-b645-856417f1fb13.png` and purchase-history screenshot at `C:\Users\pgmme\AppData\Local\Temp\codex-clipboard-041e0dbb-f901-4266-813e-42923847e2d1.png`.
- Source dimensions: 1920 × 1080 CSS pixels for both screenshots.
- Intended comparison viewports: desktop at 1920 × 1080; responsive rules also cover tablet and mobile widths.
- Implementation capture: not persisted. The available Codex in-app browser reached `proma.test` but redirected to the sign-in page; no authenticated customer session was available, so no rendered customer screenshot was taken.
- Scope: `dashboard/customer` and `portal/history`, Persian RTL, light theme.

## Implementation changes

- Dashboard balance text is white only on a saturated violet balance surface. Greeting text and next-due details use dark ink on pale surfaces. The former three-child hero layout, which left an empty white block, is now a clear two-panel grid with balance and actions grouped together.
- Purchase history now has a high-contrast title band, four aligned summary cards, an accessible search and status filter, full-width purchase cards, installment progress, contract actions, pagination, and distinct empty versus no-match states.
- Purchase data is read from the customer's recorded contracts and contract items. No placeholder purchases are added.
- The layout collapses to two-column/mobile summary and stacked purchase cards at narrower widths. Focus rings and labels are present for filter controls.

## QA findings

- **P1 — Rendered contrast and spacing remain unverified in the signed-in customer session.** Design evidence is the supplied screenshot showing the white amount on white and excessive empty space. Implementation evidence is the new scoped CSS and template composition. The unauthenticated browser could not expose the final rendered customer page. The update is installed on the local `proma.test` web root; reload the signed-in customer session to confirm the final browser rendering.
- Static PHP syntax checks passed for the changed PHP templates and controller. No application, database, or browser-flow test suite was run.
- Icons use the existing Feather icon set. No new raster artwork or placeholder purchase records were introduced.

## Final result

**BLOCKED for visual sign-off** — the code and local site update are present, but a signed-in rendered customer screenshot is not available to confirm typography, spacing, focus states, and responsive behavior against the reference.
