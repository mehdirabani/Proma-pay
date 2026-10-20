# Proma Pay Upgrade Progress

آخرین به‌روزرسانی: 2026-10-03  
نسخهٔ Core کاندید: `2.0.4`  
نسخهٔ Schema کاندید: `2026.10.02`  
نسخهٔ هدف بسته‌بندی: `2.0.4`؛ دو ZIP کاندید ساخته و از نظر ساختار/هش بررسی شدند، اما Release Gate اجرا نشده و انتشار پایدار نیستند.  
Branch / Commit: `codex/release-v1.4.5` / `ec7a460`  
Working tree: دارای ۲۸۲ مورد تغییر محلی در شروع ممیزی.

معانی: `[x]` کامل با شواهد، `[~]` ناقص، `[ ]` شروع‌نشده، `[!]` شکست/نیازمند اصلاح، `[B]` مسدود.

## Foundation

- [~] ممیزی منبع: دامنه‌های اصلی و تغییرات محلی بررسی شدند؛ ممیزی تمام مسیرها تکمیل نشده است.
- [x] کشف مهارت‌ها: ساختار پوشه‌ای `skill/` بررسی شد؛ راهنماهای PHP، MySQL، Security، Frontend و UI/UX متناسب با تغییرها بارگذاری شدند.
- [~] Baseline کارایی: نتیجهٔ فهرست مشتریان در `25_PERFORMANCE_RESULTS.md` ثبت شده؛ سنجش مرورگر و حجم تولید باقی است.
- [~] Permissions و Audit: کنترل‌های موجودند؛ ممیزی کامل endpointها و branch isolation انجام نشده.
- [~] Events: هوک‌ها و رویدادهای مدل وجود دارند؛ envelope سراسری، correlation/version و ordering کامل نیست.
- [~] Transactional Outbox: worker، قفل و dedupe بخشی پیاده شده؛ idempotency همهٔ مصرف‌کنندگان و replay crash باقی است.
- [~] Workers: مسیر outbox/cron وجود دارد؛ معماری worker عمومی و مشاهده‌پذیری کامل نیست.
- [~] Versioning و Migration: منبع نسخه به `2.0.4` و Schema هدف به `2026.10.02` هماهنگ شد؛ اجرای migrationها روی دیتابیس QA هنوز انجام نشده.
- [~] Read Models و Performance Gate: batch customer trend و baseline محدود ثبت شده؛ gate بسط‌یافته هنوز اجرا نشده.

## Financial Core

- [~] Debt Calculator: محاسبات فعلی و policyهای قانونی موجودند؛ تطبیق همهٔ سناریوهای مالی باقی است.
- [~] Payment Allocation: تخصیص گروهی و scope پرداخت موجودند؛ integration V204 در این نوبت اجرا نشد.
- [~] Overpayment و Multi Installment: مسیرهای دستی و درگاه و تست V204 موجودند؛ اجرای DB فعلی مسدود است.
- [~] Settlement: quote و settlement engine موجودند؛ پوشش انتها‌به‌انتها و reconciliation کامل لازم است.
- [~] Reconciliation: صف رسید تأییدشدهٔ درگاه وجود دارد؛ تطبیق بانکی/حسابداری کامل احراز نشده.

## Customer / Contract

- [~] Customer 360 و Timeline: نماها و رویدادهای قبلی وجود دارند؛ coverage کامل timeline هنوز بررسی نشده.
- [~] Documents و Guarantor 360: registry، اسناد و snapshot ضامن موجودند؛ security regression و end-to-end کامل لازم است.
- [~] Reschedule، Waiver و Approval: بخشی از lifecycle/approvalهای موجود بررسی شده؛ workflow کامل احراز نشده.

## Collections

- [~] Promise to Pay: فیلد/تقویم و workflowهای پیگیری موجودند؛ چرخهٔ end-to-end وعده بررسی نشده.
- [ ] Collections Playbook و Workbench اختصاصی.
- [~] SLA و Risk: اطلاعات و گردش‌های حقوقی/overdue وجود دارند؛ SLA و scoring جامع کامل نیست.

## Operations

- [~] Dashboard، Calendar و بخشی از Tasks/Actions موجودند.
- [ ] Action Center و Command Palette جامع.
- [ ] Global Search و Activity Inbox جامع.
- [ ] Rules/Automation engine و Saved Views جامع.

## Intelligence

- [~] Analytics/گزارش‌های موجود محدود بررسی شده‌اند.
- [ ] Fraud/Anomaly Center و Duplicate Detection جامع.
- [ ] Document Intelligence و Data Quality Center.

## Platform

- [~] Notifications، outbox، reports و Excel قابلیت‌های موجودند؛ تکمیل امنیت و بارسنجی لازم است.
- [ ] Multi-Branch isolation، API عمومی، Webhooks و Simulation.
- [~] Observability و backup/recovery ابزارهای پایه دارند؛ سنجش production و restore کامل نیست.
- [~] Plugin API: تغییرات role/API در کد و تست‌ها هست؛ integration QA باز است.

## Release Engineering

- [~] QA gate: تست‌های V204 و plugin در gate ثبت شده‌اند؛ DB به QA دقیق/opt-in و HTTP به loopback محدود است؛ اجرای gate بسط‌یافته برای این سورس/کاندید انجام نشده.
- [!] Artifactهای موجود v2.0.3 توسط `tools/verify-release.php` تأیید نشدند؛ checksum به آرشیو Accounting گم‌شدهٔ 1.2.14 اشاره دارد.
- [~] Update/preflight/file rollback در تست‌های محلی قبلی پاس شده؛ آزمون migration/restore واقعی باز است.
- [~] Full Core و Update کاندید V2.0.4 ساخته و با `tools/verify-candidate-artifacts.php` بررسی شدند؛ manifest هر دو صریحاً `NOT_RUN`/غیرمجاز برای Production ثبت می‌کند.
- [ ] Fresh install، ارتقای V2.0.3 به کاندید، rollback و QA gate کامل.

## آزمون‌های تداوم

- [x] `STATIC_V145_OK`
- [x] `STATIC_V204_PAYMENT_SCOPE_AND_SIGNATURE_OK`
- [x] `STATIC_V204_MOBILE_CUSTOMER_INSTALLMENTS_OK`
- [x] `PROMA_ACCOUNTING_V1216_OK`
- [x] تست‌های static/domain PromaRepair
- [x] PHP lint فایل‌های تغییرکرده و `git diff --check`
- [x] بسته‌بندی و کنترل checksum/ساختار ZIPهای کاندید V2.0.4؛ PHP lint روی فایل‌های PHP استخراج‌شده از ZIPها.
- [B] تست‌های DB V204: سرویس QA روی 127.0.0.1:33387 فعال نبود.
- [!] `tools/verify-release.php`: شکست به‌دلیل checksum/archive ناسازگار در خروجی فعلی.
- [B] Release Gate کامل، نصب تازه و ارتقای واقعی Candidate: اجرا نشده/نیازمند محیط QA.

پیشرفت تقریبی Coverage: Overall 30%، Foundation 50%، Financial Core 50%، Customer/Contract 35%، Collections 25%، Operations 25%، Intelligence 10%، Platform 25%، Release 20%. این درصدها تقریبی و مبتنی بر featureهای بررسی‌شده‌اند؛ انتشار پایدار تأیید نشده است.
