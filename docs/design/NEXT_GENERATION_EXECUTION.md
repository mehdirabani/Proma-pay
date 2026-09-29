# Proma Pay next-generation UI: audited migration

Baseline: 2.0.0; audited 2026-09-29. This is an implementation ledger, not a claim that all pages have passed visual QA.

## 1. Project architecture audit

PHP server-rendered lightweight MVC: `core/Router.php`, `core/Controller.php`, `core/Auth.php`, controllers, models and 63 PHP view files. `views/layouts/app.php` renders authenticated roles; `auth.php` and `public.php` serve separate entry points. Financial calculations remain server-side. `package.json` currently builds Electron, not Tailwind. No Tailwind configuration is present. Preserve routes, CSRF fields, permissions, payment allocation and plugin assets.

## 2. UI/UX legacy audit

| Source | Classification | Reason / replacement condition |
|---|---|---|
| core, models, controllers | KEEP | Business rules and authorization |
| html/RTL/assets/css/vendors/bootstrap.rtl.min.css | KEEP during migration | Views still use responsive grid/utilities; remove after every consumer migrates |
| html/RTL/assets/css/style.css, responsive.css, color-1.css | REFACTOR | Template layout rules compete with product styles |
| assets/css/app.css | REFACTOR | Fonts and feature-specific rules mixed together |
| assets/css/design-system/tokens.css | REFACTOR | Existing semantic scale; unify aliases before deleting consumers |
| assets/css/components/v2-system.css | REFACTOR | Desktop-first cascade, duplicate shell decisions, undersized mobile controls |
| assets/js/app.js | KEEP / REFACTOR by feature | Canonical modal lifecycle, payment and editor hooks |
| v2-system.js modalSafetyNet | REMOVE after verification | Second modal closer bypasses the canonical lifecycle |
| customer banner model/controller | KEEP | Real, authorized server-managed content |
| optional plugins and uploads | KEEP | Outside presentation cleanup |
| unreferenced template assets | UNKNOWN | Packaging and plugin dependencies must be checked before removal |

## 3. Skill inventory

Root `skill/` includes frontend-forge, ui-ux-pro-max, design-system, ui-styling, design, brand, banner-design and slides. The `.claude/skills` and `cli/assets/skills` copies are duplicates, not separate dependencies.

| Skill | Purpose / relevant section | Benefit | Conflict | Priority |
|---|---|---|---|---|
| design-system | Three-layer tokens, component states | One consistent vocabulary | Slide instructions irrelevant | 1 |
| playwright (installed) | Browser interaction and viewport QA | Verify real routes and controls | No user-facing demo requested | 2 |
| ui-styling | Responsive component guidelines | Layout principles | React/Radix installation conflicts with PHP/Vanilla stack; not adopted | Reference only |
| frontend-forge / ui-ux-pro-max | Broader design workflows | Potential later review | Avoid duplicate orchestration | Deferred |
| brand/banner/slides | Graphics/presentations | No immediate code benefit | Outside current phase | Not selected |

## 4. Skill execution strategy

Read design-system and its token architecture reference, then use primitives → semantic roles → component aliases. Use the installed Playwright workflow for actual authenticated pages; no demo artifact. Existing architecture and user requirements outrank generic framework advice.

## 5. Page inventory

Authenticated view families: dashboard (admin/customer/operator/lawyer), customers (list/detail), contracts (list/detail/duplicates/print/booklet), installments, payments, overdue, operator, legal (list/detail/cost approvals), lawyer, review, profile-reviews, notifications, chat, calendar, profile, users, medals, file-manager, settings (general/contracts/preview/banners), imports (list/preview), plugins, system-health and AI. Public/auth: login/register/forgot/reset; errors 403/404/system; optional ecommerce has shop/product/catalog/orders/cart/checkout/payment/landing/installment-request. Shared partials: payment card and customer banners. Printing has separate layouts and must stay independent of the app shell.

## 6. Role and workflow map

Customer: outstanding amounts → contract → installments → payment → receipt/history. Admin: operational summary → exception queues → approve/reconcile → audit. Operator: overdue queue → contact → record result → follow-up date → next customer. Lawyer: assigned cases → timeline → expense/evidence/stage → review status. Keep server permission enforcement at each action.

## 7. Observed UX problems

Multiple global palettes and layout overrides; mobile targets below 44px; sidebar state owned by template scripts while V2 changes its meaning; mobile footer reserves bottom-nav space twice; modal close behavior duplicated in two scripts; table labels only assigned once, so dynamically replaced rows miss mobile labels; blanket table conversion ignores merged headings. Customer banners precede debt actions. These are source-confirmed issues; viewport validation is required to assess the resulting appearance.

## 8. New information architecture

Customer navigation: home, installments, contracts, notifications, account. Operations: dashboard, contracts/customers, overdue/payment queues, legal workspace, review queue, administration. Contextual actions stay with their record. Secondary metadata goes below primary task information, not beside the main form as an empty permanent column.

## 9. Mobile-first UX architecture

Default one-column workspace, readable financial values, 48px controls, role-specific bottom navigation with safe-area padding. Sheet body scrolls independently while header/close action remain reachable. Desktop adds a fixed RTL navigation rail and wider data workspace. Do not conceal overflow at the body to disguise broken children.

## 10. New design system

Reuse YekanBakh local fonts. Primitive palette: violet brand, neutral ink/surfaces; semantic success/warning/danger/info; keep state labels in text. Spacing uses the existing 4px scale. Body 14–16px, labels 13–14px, page title 24px, financial figures tabular. Flat primary buttons, subtle surfaces, 12–16px radii, visible focus, reduced-motion support. Component tokens alias semantic values; legacy aliases are temporary compatibility mappings.

## 11. Component architecture

Shared CSS in `assets/css/design-system` and existing component files; Vanilla interaction in existing `assets/js/v2-system.js`; server rendering stays in PHP partials. Keep a single modal controller in app.js. Preserve native form submission, hidden CSRF inputs and named submit buttons. Table enhancement must be idempotent and support dynamic rows without HTTP requests.

## 12. Responsive strategy

Validate 320, 360, 375, 390, 412, 430, 768, 820, 1024, 1280, 1440, 1536, 1920. Simple tables become labeled lists; complex merged-cell/visualization tables explicitly opt out pending feature redesign. No hiding critical actions. Desktop form widths depend on content, not arbitrary 50/50 columns.

## 13. Migration strategy

Foundation → navigation/shell → customer task hierarchy → shared forms/tables/dialogs → contracts/installments/payments → legal/operator → remaining administration → auth/errors → dependency cleanup. Each feature retains backend request contracts. Verify empty/error/permission states, not just populated screenshots.

## 14. Legacy removal strategy

Find references before removal, migrate consumers, test the affected route, then delete only the replaced implementation. Do not remove Bootstrap while PHP views or plugins still require it. Do not remove unknown assets or alter archive contents merely to hit a size target. Packaging must preserve fonts, styles and required local libraries.

## 15. Implementation roadmap and acceptance ledger

- [x] Inspect version, routes/layouts, view families, CSS and interaction ownership.
- [x] Record skill choices, dependencies and migration plan before implementation.
- [ ] Consolidate visual tokens and shared component behavior.
- [ ] Validate shell/navigation at mobile/tablet/desktop widths.
- [ ] Migrate all critical role workflows and verify states.
- [ ] Remove verified obsolete presentation dependencies.
- [ ] Run release regressions, package full/update archives, verify upgrade/rollback.

Completion requires evidence for all outstanding entries. A passing PHP lint is not visual approval.
