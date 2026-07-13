# Proma Accounting Modern UI Audit

Date: 2026-07-13

Audited source: `plugins/PromaAccounting`

Current plugin version: `1.1.0`

Target plugin version after QA: `1.2.0`

## Scope inspected

- Manifest, provider, routes, controller, repository and financial services
- Dashboard, accounts, user ledger, sales, commissions and commission rules
- Settings, setup wizard, help center and historical-sales backfill
- Existing CSS, JavaScript, fixtures and static tests
- Proma Pay application shell, sidebar, breadcrumb, alerts, buttons, forms, tables, typography and theme selectors
- Desktop, tablet, 430px, 390px, 375px and 360px layout risks

## Current visual problems

1. The plugin uses an older neutral/blue identity (`#6956e8`) instead of the requested Proma accounting palette (`#6A1B9A`).
2. Cards, panels, inputs, dialogs and choice blocks use mostly 7-8px radii, so they look flatter and older than the intended 14-16px financial workspace.
3. The page background is inherited and panels do not form a consistent workspace band; some pages feel empty while dense tables feel crowded.
4. Page headers are plain flex rows without a shared icon, contextual summary or consistent action hierarchy.
5. Dashboard has only four basic KPIs and no balanced financial summary, quick actions or recent financial context.
6. Account rows do not combine avatar, identity and role into one readable user cell. Mobile receives a squeezed table instead of account cards.
7. Ledger transaction effects use color and raw English entry codes; plus/minus signs and clear Persian direction labels are incomplete.
8. Commission statuses expose raw database values and actions crowd one cell with forms and inputs.
9. Commission rules are presented as a creation form plus a plain table, with no scannable rule cards or secondary metadata hierarchy.
10. Settings are split into cards but still form one long page without section navigation or a compact active-state summary rail.
11. Setup uses ten tiny labels in one row; intermediate widths make the progress indicator difficult to scan.
12. Help is a long document with a narrow sticky link list and no search or topic-card entry points.
13. Empty states are table sentences only; they lack a local icon, concise context and a useful next action.
14. Loading and duplicate-submit states are not represented.
15. Financial confirmation still uses `window.confirm()` for reversals, so the user cannot review person, amount or financial effect in a proper modal.

## Asset and architecture findings

- `accounting.css` is correctly rooted at `.proma-accounting`, and no unscoped `input`, `table`, `button` or `.card` rules were found.
- The active plugin CSS is hard-coded in `views/layouts/app.php` and loads globally on every authenticated page.
- `assets/js/accounting.js` is not loaded by the real application layout. Switch synchronization, ledger preview, dirty-form warning and confirmation interception therefore do not reliably run outside the fixture.
- The manifest declares plugin assets, but the core layout does not currently resolve those assets by active plugin route.
- Responsive rules are mixed into the main stylesheet instead of the requested `accounting-responsive.css` layer.
- Chart.js is available locally at `html/RTL/assets/js/chart/chartjs/chart.min.js`, while the application shell currently references a remote CDN build. Accounting must use the local copy only.
- Large view fragments are duplicated across pages: page header, KPI, user identity, status, money effect, empty state and section heading.

## Current design measurements

| Area | Current | Target |
| --- | --- | --- |
| Page padding | 24px desktop, 13px mobile | 24px desktop, 12-14px mobile |
| Card padding | 22px desktop, 15px mobile | 20-22px desktop, 14-16px mobile |
| Section gap | 22px | 24px desktop, 16px mobile |
| Panel radius | 8px | 16px |
| Control radius | 7px | 10px |
| Control height | 44px | 44px |
| Main shadow | mostly none | `0 8px 28px rgba(50,34,79,.06)` |
| Primary | `#6956e8` | `#6A1B9A` |
| Text | `#182230` | core text mapped with `#212121` fallback |
| Background | inherited | core app background with `#F5F5F5` fallback |

## Typography audit

- Local Yekan Bakh is already defined by Proma Pay and must remain the only font.
- Existing page title at 25px is acceptable; KPI amounts at 18px are restrained.
- Table metadata and help copy use 10-12px inconsistently and need one readable hierarchy.
- Raw English financial status and entry type values reduce readability in an RTL Persian product.

## Forms and controls

- Controls already share a 44px height, but labels/help blocks do not always reserve equal vertical space.
- Money suffix groups are structurally sound and can be retained with improved border, focus and localized formatting.
- Switches are accessible in principle but need a larger clickable row, clearer selected state and complete dark-mode tokens.
- Required marks are correctly placed beside labels in current accounting forms.
- Inline reversal forms create alignment and overflow problems in tables.

## Tables and responsive behavior

- Headers and cells are vertically aligned, but all cells use `white-space: nowrap`, forcing wide horizontal scroll.
- There is no dedicated mobile account, commission, sale or ledger card presentation.
- Action columns contain full text controls rather than a compact primary action plus secondary action menu/modal.
- Empty table content is visually indistinguishable from a normal row.
- Sticky table headers and controlled scroll regions are absent.

## Dark mode

- Basic surface, border, text and control overrides exist.
- Status backgrounds, notices, choice cards, table headers, empty states and modal backdrop/content are only partially themed.
- New charts must derive colors from CSS tokens and use dark-compatible grid and label colors.

## Accessibility

- Positive/negative values partially rely on color; explicit direction labels and plus/minus signs are required.
- Icon-only controls need `aria-label` and native tooltips.
- The native dialog lacks a dedicated close control and focus-management helper.
- Dynamic button loading and duplicate-submit prevention are absent.
- Touch targets in compact table actions can fall below 40px.

## Core components to reuse

- Proma Pay application shell, sidebar and top navigation
- Core breadcrumbs and flash alerts
- Local Yekan Bakh font
- Core `.btn` hierarchy and Feather icons
- Core CSRF, permissions, validation, transactions and audit mechanisms
- Local Chart.js distribution
- Core pagination renderer

## Accounting components to create

- Page header and section card
- KPI card and quick action
- User cell/avatar and money-effect value
- Status badge mapping
- Filter bar and responsive data cards
- Empty/loading/error state
- Financial confirmation dialog
- Settings section navigation
- Chart panel with empty fallback

## CSS conflict and leak assessment

- Existing selectors are scoped, so the primary leak is asset loading rather than selector scope.
- Bootstrap `.table`, `.btn` and `.text-muted` are used inside the plugin; accounting-specific classes must take precedence only below `.proma-accounting`.
- No new global selectors or generic `.card`, `.modal`, `input`, `select`, `table` or `button` rules are permitted.

## Data and financial safety boundary

- No migration is required for this redesign.
- No balance, commission, ledger, sales or category data will be updated.
- Commission calculation, posting, reversal, idempotency, CSRF, permissions and server validation remain authoritative.
- Repository additions may only read existing data for presentation.

## Affected files and implementation order

1. Add route-scoped plugin asset resolution to the existing app shell.
2. Add reusable view components and one design-token layer.
3. Split responsive rules into `accounting-responsive.css`.
4. Redesign dashboard, accounts, ledger, sales, commissions and rules.
5. Redesign settings, setup, help and backfill.
6. Extend JavaScript for local charts, localized money display, loading state, accessible dialog and responsive interactions.
7. Add fixture coverage for every page, light/dark themes and all required viewport widths.
8. Verify no financial service or migration changes, then increment the plugin to `1.2.0` and build the independent ZIP.
