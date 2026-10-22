# Candidate artifacts V2.0.4 — 2026-10-03

این خروجی‌ها برای بازبینی بسته‌بندی ساخته شده‌اند؛ Release Gate کامل اجرا نشده و برای Production یا توزیع عمومی تأیید نشده‌اند. Core و Update manifest هر دو `candidate_only=true`, `release_gate_status=NOT_RUN` و `production_availability_certified=false` دارند.

| نوع | مسیر | اندازه | SHA-256 |
|---|---|---:|---|
| نصب کامل Core | `dist/candidates/archive/v2/v2.0.4/core/PromaPay-v2.0.4.zip` | 86,857,452 bytes | `dd7c65fd08000fb55cb84193e7394861ec5888e7175eef04298d7a2d6d95dda8` |
| آپدیت تفاضلی | `dist/candidates/archive/v2/v2.0.4/updates/PromaPay-Update-v2.0.4.zip` | 363,519 bytes | `7faa54458b3aaba54a6a4f1f32ecfc93e3a588a7b3ae55dc66af3d24cbda3677` |

## Baseline و محتوا

- Baseline آپدیت: `PromaPay-v2.0.3.zip`؛ `minimum_version` برابر `2.0.3` است.
- Core شامل ۲٬۹۶۵ فایل runtime است و افزونه‌های اختیاری/دادهٔ عملیاتی را در بر ندارد.
- Update شامل ۵۰ فایل تغییرکرده و دو migration است: `2026_10_01_group_payment_allocation_scope.sql` و `2026_10_02_gateway_reconciliation_queue.sql`.
- sidecar manifest: `dist/candidates/archive/v2/v2.0.4/updates/PromaPay-Update-v2.0.4-manifest.json`.
- checksumها: `dist/candidates/archive/v2/v2.0.4/SHA256SUMS.txt`.

## اعتبارسنجی انجام‌شده

`php -l scripts/build_candidate_artifacts.php` و `php -l tools/verify-candidate-artifacts.php` بدون خطای Syntax بودند. اجرای `php -d extension=zip tools/verify-candidate-artifacts.php` نتیجهٔ `CANDIDATE_ARTIFACTS_V204_OK_QA_NOT_RUN` داد. این verifier ساختار و manifestها، baseline و checksumها، مسیرهای ZIP، فایل‌های عملیاتی ممنوع، assets اصلی و PHP lint فایل‌های استخراج‌شده را بررسی می‌کند.

این نتیجه جایگزین اجرای release gate، آزمون دیتابیس/migration، نصب تازه، ارتقای واقعی از 2.0.3، rollback یا browser QA نیست.
