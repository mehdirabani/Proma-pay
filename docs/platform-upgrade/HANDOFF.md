# Proma Pay Upgrade Handoff

## وضعیت نسخه و مخزن

- Current Core Candidate Version: `2.0.4` (`config/version.php`)
- Candidate Schema Version: `2026.10.02` (دارای migrationهای ۱ و ۲ اکتبر؛ هنوز روی QA DB اجرا نشده)
- Candidate target: `2.0.4`; فقط بسته‌های Candidate ساخته شده‌اند، نه انتشار پایدار.
- Current Branch: `codex/release-v1.4.5`
- Current Commit: `ec7a460`
- Working tree: تغییرات محلی گسترده وجود دارند؛ آن‌ها را حفظ کن و پیش از کار تازه `git status --short` را ثبت کن.

## آخرین milestone تکمیل‌شده

ممیزی Recovery انجام شد؛ coverage دروازهٔ QA برای featureهای جاری V204/Plugin افزایش یافت؛ قواعد حذف‌شدهٔ موبایل و کارت‌های پرداخت/سند در CSS بازیابی شدند؛ نسخه برای بسته‌بندی کاندید به V2.0.4 هماهنگ شد؛ دو ZIP در `dist/candidates/archive/v2/v2.0.4/` ساخته و از نظر ساختار، manifest، checksum، فایل‌های ممنوع و PHP lint بررسی شدند. Release Gate کامل اجرا نشده است.

## اکنون در حال کار

راه‌اندازی محیط QA disposable `proma_qa_v204` و اجرای Release Gate کامل، سپس نصب تازه/ارتقای واقعی از V2.0.3 و آزمون restore. ZIPهای ساخته‌شده فقط Candidate هستند و برای Production تأیید نشده‌اند.

## قابلیت‌های تکمیل‌شده با شواهد محدود

- پرداخت درگاه: نگه‌داری رسید تأییدشده برای تطبیق، جلوگیری از checkout تکراری هنگام review و quote درخواستی؛ شواهد در commitهای اخیر و migration/testهای V204.
- updater: preflight، حدود آرشیو، قفل نصب، بازگردانی فایل پیش از migration و نگه‌داری snapshot در خطای migration؛ گزارش QA قبلی این تست‌ها را ثبت کرده است.
- outbox/customer trend: worker lock، dedupe اعلان و batch روند؛ دو integration قبلی طبق `30_RELEASE_GATE_STATUS.md` پاس شده‌اند.
- CSS موبایل و gate ثبت تست: تغییرات این نوبت با تست‌های ایستا متمرکز پاس شدند.

## قابلیت‌های ناقص

Event envelope/automation/collections/API/webhooks/branch isolation، پوشش کامل financial E2E/concurrency، browser/accessibility، دادهٔ production-size، و آزمون نصب/ارتقا/rollback بستهٔ نسخهٔ فعلی.

## مشکلات شناخته‌شده

- `tools/verify-release.php` روی آرشیو موجود v2.0.3 شکست می‌خورد: `dist/core/SHA256SUMS.txt` به `PromaAccounting-v1.2.14.zip` اشاره می‌کند که وجود ندارد. Core/Update آرشیوهای v2.0.3 قدیمی‌تر از سورس جاری‌اند.
- Candidate فعلی افزونهٔ اختیاری ندارد (قرارداد Core هم آن را جدا نگه می‌دارد). اگر بستهٔ جداگانهٔ Accounting ساخته شد، سورس `1.2.17` با آرشیو immutable هم‌نسخه تعارض دارد و ابتدا باید نسخهٔ Plugin افزایش یابد.
- آرشیوهای رسمی موجود v2.0.3 قدیمی‌تر از سورس جاری‌اند؛ بسته‌های تازه زیر `dist/candidates/archive/v2/v2.0.4/` جدا شده‌اند تا immutable releaseهای رسمی بازنویسی نشوند.
- ۲۱۴ حذف tracked، به‌خصوص نمونه‌های `html/RTL/template/`، از قبل در working tree بوده‌اند؛ بدون اثبات نیاز، برنگردان یا حذف‌های بیشتری اعمال نکن.
- Release Gate جدید DB integrationها را فقط با DSN پایگاه `proma_qa_v204` و `PROMA_TEST_ALLOW_DESTRUCTIVE=1` اجرا می‌کند؛ HTTP integrationها هم فقط روی localhost/loopback اجرا می‌شوند. هیچ DB یا host دیگری را به این متغیرها وصل نکن.

## تست‌های شکست‌خورده، مسدود و اجرا‌نشده

- پیش از بازیابی CSS، دو تست ایستای موبایل شکست خوردند؛ پس از اصلاح، هر دو پاس شدند.
- Archive verification در نوبت فعلی **FAILED** شد (checksum/archive ناسازگار).
- DB integrationهای gate بسط‌یافته **BLOCKED** بودند چون QA DB و HTTP listener اجرا نبودند.
- Candidate ZIP verifier اکنون **PASS** است (`CANDIDATE_ARTIFACTS_V204_OK_QA_NOT_RUN`)، اما این فقط صحت بسته‌بندی را پوشش می‌دهد.
- Release Gate کامل، نصب تازه و ارتقای واقعی Candidate هنوز اجرا نشده‌اند.
- نصب تازه، ارتقای بستهٔ سورس فعلی، restore پایگاه، browser/accessibility، concurrency/load در مقیاس تولید اجرا نشده‌اند.
- QA gate قبلی ۸۵ تست را پاس کرده، اما تست‌های تازهٔ V204/Plugin در آن اجرا نمی‌شدند؛ آن نتیجه را به gate بسط‌یافته نسبت نده.

## تصمیم‌های معماری و migration

- به‌جای بازنویسی MVC فعلی، از service/model و migrationهای موجود ادامه بده.
- رویدادهای outbox ممکن است دوباره تحویل شوند؛ consumerها باید idempotent بمانند.
- MariaDB DDL ممکن است auto-commit شود؛ شکست پس از آغاز migration را rollback خودکار دیتابیس فرض نکن.
- آرشیوهای Release immutable هستند؛ برای تغییر محتوای نسخه، نسخهٔ جدید Core/Plugin تولید شود.
- مهارت‌ها در زیرپوشه‌های مستقل `skill/` قرار دارند؛ Router ریشه‌ای `skill/SKILL.md` وجود ندارد. مهارت UI/UX داخل `skill/ui-ux-pro-max-skill-main/` است.

## مرحلهٔ بعدی پیشنهادی

1. راه‌اندازی MariaDB disposable روی port `33387` با دیتابیس `proma_qa_v204` و HTTP QA محلی.
2. تنظیم متغیرهای `PROMA_TEST_DB_*`, `PROMA_QA_BASE_URL` و `PROMA_TEST_ALLOW_DESTRUCTIVE=1` فقط در shell همان QA session.
3. اجرای `tools/release-gate.php --local` با PHP 8.2 و فعال‌بودن ZIP extension برای runner و child processها.
4. رفع شکست‌ها، سپس آزمون تازه‌نصب/ارتقای V2.0.3/restore؛ در صورت پاس شدن، build رسمی و `tools/verify-release.php` اجرا شود.

جزئیات ZIP و SHA256 در `docs/platform-upgrade/32_CANDIDATE_ARTIFACTS.md` است. برای اجرای verifier کاندید: `php -d extension=zip tools/verify-candidate-artifacts.php`.

## فرمان ادامه

از PowerShell و فقط با credentialهای QA استفاده کن؛ مقدار password را در log یا این فایل ننویس:

```powershell
$env:PROMA_TEST_DB_DSN = 'mysql:host=127.0.0.1;port=33387;dbname=proma_qa_v204;charset=utf8mb4'
$env:PROMA_TEST_DB_USER = 'root'
$env:PROMA_TEST_DB_PASSWORD = '<QA-only password>'
$env:PROMA_QA_BASE_URL = 'http://127.0.0.1:8000'
$env:PROMA_TEST_ALLOW_DESTRUCTIVE = '1'
& 'C:\xampp\php\php.exe' 'tools/release-gate.php' '--local'
```

پیش‌نیاز: PHP 8.2 CLI باید برای child processها هم ZIP extension را بارگذاری کند؛ در غیر این صورت آزمون‌های ZIP را PASSED اعلام نکن.
