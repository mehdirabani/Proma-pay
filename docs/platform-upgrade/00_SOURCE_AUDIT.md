# Proma Pay source audit — 2026-10-03

## Baseline and evidence

- Workspace: `F:/proma-pay`; branch: `codex/release-v1.4.5`; HEAD: `ec7a4608f0bb93f3c70b214f4f2ee38b8838b761`.
- Working application version: `2.0.3`; configured database version: `2026.09.22`; configured plugin API: `1.1`; release channel: `stable` (`config/version.php`). HEAD records plugin API `1.0`, so the working tree is ahead of that commit.
- Available CLI PHP: `8.2.12` (`C:/xampp/php/php.exe`). Its ZIP extension is installed but disabled by default; local ZIP checks use `-d extension=zip`. A read-only connection through the application's current database configuration returned `PDOException`, so the live installation remains **unverified**.
- A separate, disposable QA instance was reachable on port `33387`: MariaDB `10.4.32`, database `proma_qa_v204`, 91 tables. Its `migrations` table records successful 2026-10-01 payment scope and 2026-10-02 gateway reconciliation migrations. This is QA evidence, not evidence about the live installation. No customer data was inspected.
- The working tree already contained substantial uncommitted edits and deleted `html/RTL/assets`. The layouts and installer still reference those runtime assets. The 2,695 asset files were restored from HEAD; deleted template/demo pages and unrelated user edits were left alone.
- There is no `skill/SKILL.md` router. On 2026-10-03 the directory was rescanned: `application-security` v1.2.0 and `database-mysql-architect` v2.0.0 were newly present alongside `php-forge`, `frontend-forge`, and `ui-ux-pro-max-skill-main`. Relevant guidance read: both new `SKILL.md` files, the PHP/path-traversal/patch-verification security modules, the MySQL migration/concurrency/safety modules, `php-forge` fintech/database modules, and `docs/codex/00_CODEX_START_HERE.md` / `02_NON_NEGOTIABLE_RULES.md`. The new skills' bundled benchmark claims are about those skills, not Proma Pay; their scanners/runtimes were not run against this application.

## Current architecture

| Area | Source evidence | Current state / gap |
| --- | --- | --- |
| Entry, routing, auth | `index.php`, `bootstrap.php`, `core/Router.php`, `core/Auth.php`, `core/Csrf.php` | Lightweight MVC, session auth, dynamic core controller routing and plugin route dispatch. Authorization is distributed across controllers and model helpers. |
| Customers, contracts, installments | `controllers/CustomersController.php`, `ContractsController.php`, `InstallmentsController.php`; matching models | Existing workflows, including contract and installment lifecycle logic. Scope checks need route-by-route verification before new endpoints. |
| Payment and debt | `models/Payment.php`, `models/PaymentRequest.php`, `helpers/PaymentAllocationService.php`, `PaymentGroupService.php`, `InstallmentSettlementService.php`, `SettlementQuoteService.php` | Explicit grouped allocations and settlement quotes exist. Integer-toman invariants and retry behavior require live DB regression evidence for this working tree. |
| Legal, guarantors, documents | `models/LegalCase.php`, `ContractGuarantorService.php`, `ContractDocument.php`, `IdentityDocument.php`, `FileRecord.php` | Existing features; no claim of full 360 views or document intelligence. |
| Accounting | `plugins/PromaAccounting` | Separate plugin with ledger, analytics and migrations. It has preexisting uncommitted edits. |
| Notifications and outbox | `models/Notification.php`, `models/SystemOutbox.php`, `controllers/CronController.php` | Outbox table and bounded worker exist. This change adds a database-wide worker lock and uses the notification dedupe key. Plugin consumers still need idempotent handling of the supplied `outbox_event_id` to cover crash/replay between their own side effect and outbox completion. |
| Events, jobs and cron | `models/Event.php`, `core/PluginHooks.php`, `core/CronLock.php`, `cron/calendar_reminders.php` | Plugin hooks are queued through outbox in several business paths; cron route has a local file lock. There is no general domain-event envelope with UUID/correlation/version across the core. |
| Operations and reporting | `controllers/OverdueController.php`, `OperatorController.php`, `LegalController.php`, `DashboardController.php`; accounting plugin | Existing screens and reports. Dedicated collections playbooks, rule automation, branch isolation, public API and webhook platform are not established by this audit. |
| Frontend | `views/`, `assets/`, `service-worker.js`, `electron/` | Persian RTL app with mobile/PWA and Electron assets. Current UI is not uniformly Tailwind-based; forcing a framework replacement would conflict with the current architecture. |
| Telemetry and health | `core/RequestTelemetry.php`, `controllers/HealthController.php`, `SystemHealthController.php` | Sampled request and SQL telemetry exists. A local QA request baseline and customer-list before/after result are now in `docs/performance/` and `25_PERFORMANCE_RESULTS.md`; browser vitals and production-size data remain unmeasured. |
| Installation and migration | `install.php`, `database/proma-pay-install.sql`, `database/migrations/`, `helpers/ScriptUpdateService.php` | Fresh install uses canonical SQL. Update packages list SQL migrations; plugin migrations are tracked separately. This change validates package files/migrations before backup, serializes installs with a file lock, and restores previous application file bytes on an in-process failure **before migrations start**. After migration execution begins, it retains installed files and snapshots for coordinated recovery because MariaDB DDL can auto-commit. Process termination can interrupt either path. A fresh-install/upgrade/recovery rehearsal remains required. The configured DB date predates the 2026-10-01 and 2026-10-02 migration files. |
| Release and tests | `tools/release-gate.php`, `tools/build-compat-update.php`, `tests/`, `dist/` | Existing release gates and old packages exist. Current release certification requires real DB/HTTP evidence, fresh install, upgrade and rollback tests. Those gates are open for this worktree. |

## Immediate engineering decisions

1. Make the existing outbox safe against concurrent workers and duplicate notification delivery without changing the financial schema. Focused QA integration: **PASSED**.
2. Keep `2.0.3` as the working version until the actual feature set and release gates justify a target version.
3. Do not label the existing or a newly built archive as a stable production release while the live database, performance baseline, fresh install and upgrade tests are unverified.
4. Use the repository's existing services, migrations and release machinery. Record any conflicts with the master brief rather than replacing existing business rules by assumption.
5. Follow the new security skill's evidence rule: a path pattern or scanner signal alone is a candidate, not a confirmed vulnerability. Follow the database skill's migration checklist and use the disposable QA database for rehearsal; no production database writes were made.

## Verification still required

- Inspect complete QA schema drift and financial invariants; only the two latest migration records and outbox behavior were checked here.
- Extend the measured local QA before/after request baseline to a representative production-size seeded dataset, and capture browser metrics.
- Exercise fresh installation, base-to-target upgrade, recovery/rollback, cross-role permissions and concurrency before release packaging.

## تداوم ممیزی و تغییرات پس از QA قبلی — 2026-10-03

- در لحظهٔ ممیزی Branch برابر `codex/release-v1.4.5` و HEAD برابر `ec7a460` بود؛ `config/version.php` نسخهٔ Core را `2.0.3` و Schema را `2026.09.22` اعلام می‌کند. Working tree دارای ۲۸۲ مورد تغییر بود (۲۱۴ حذف، ۳۶ تغییر، ۳۲ مورد جدید). تغییرات محلی قبلی حفظ شدند.
- ساختار مهارت‌ها زیرپوشه‌ای است و `skill/SKILL.md` وجود ندارد. مسیرهای راهنمای استفاده‌شده: `skill/php-forge/`, `skill/database-mysql-architect/`, `skill/application-security/`, `skill/frontend-forge/` و `skill/ui-ux-pro-max-skill-main/.claude/skills/ui-ux-pro-max/`.
- در بررسی فعلی `tools/verify-release.php` برای آرشیوهای موجود **FAILED** شد؛ `dist/core/SHA256SUMS.txt` به آرشیو `PromaAccounting-v1.2.14.zip` اشاره دارد که در مسیر مورد انتظار نیست. آرشیوهای Accounting موجود در `dist/plugins/PromaAccounting/` نسخه‌های 1.2.15 تا 1.2.17 هستند. بنابراین Artifactهای فعلی مبنای بستهٔ کد کاری فعلی نیستند.
- `tools/release-gate.php` تست‌های V2.0.4 پرداخت، migration، reconciliation، سند مشتری، outbox، batch مشتری، نقش افزونه و تست‌های PromaRepair را اجرا نمی‌کرد. فهرست تست‌ها به‌روز شد؛ آزمون‌های DB به opt-in و DSN دقیق `proma_qa_v204` و HTTP testها به localhost/loopback محدود شدند.
- دو تست ایستای V2.0.4 شکست CSS رابط را نشان دادند: viewها از کلاس‌های چندقسطی، کارت مشتری و کارت اسناد استفاده می‌کردند ولی قواعدشان از `assets/css/components/v2-system.css` حذف شده بود. قواعد حذف‌شده از HEAD بازیابی شد و چیدمان سلول موبایل به مقدار تأییدشدهٔ قبلی برگشت. پس از اصلاح، تست‌های ایستا پاس شدند.
- `scripts/build_release.php` قبلاً آرشیو هم‌نسخه را فقط در مسیر ریشهٔ `dist/plugins/` جست‌وجو می‌کرد؛ آرشیو تاریخی Accounting در زیرپوشه بود. کنترل جلوگیری از بازسازی نسخهٔ یکسان برای آن مسیر نیز افزوده شد.
- تست‌های متمرکز فعلی: `STATIC_V145_OK`, `STATIC_V204_PAYMENT_SCOPE_AND_SIGNATURE_OK`, `STATIC_V204_MOBILE_CUSTOMER_INSTALLMENTS_OK`, `PROMA_ACCOUNTING_V1216_OK` و دو تست PromaRepair پاس شدند. Lint فایل‌های تغییرکرده و `git diff --check` نیز پاس شدند.
- listener دیتابیس/HTTP آزمایشی در زمان ادامهٔ کار فعال نبود؛ آزمون‌های DB نسخهٔ V2.0.4 در این نوبت اجرا نشدند. نتیجهٔ قبلی ۸۵ تست و outbox/customer batch همچنان شواهد snapshot قبلی است و gate بسط‌یافته هنوز دوباره اجرا نشده است.

### موارد باز

- Target Version هنوز رسمی نشده است؛ نام migration و تست‌ها V2.0.4 را محتمل می‌کنند، اما config روی `2.0.3` می‌ماند تا gate نسخهٔ هدف تعریف و پاس شود.
- پیش از بسته‌بندی آینده، نسخهٔ Accounting باید از آرشیو منتشرشدهٔ هم‌نسخه تفکیک شود؛ در حال حاضر manifest سورس `1.2.17` است و آرشیو `1.2.17` از قبل وجود دارد.
- نصب تازه، ارتقای واقعی، بازیابی پایگاه داده، browser/accessibility، دادهٔ production-size و Release Manifest نهایی هنوز تأیید نشده‌اند.
