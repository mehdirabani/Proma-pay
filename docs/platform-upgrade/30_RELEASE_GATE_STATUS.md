# Release gate status — 2026-10-03

## وضعیت فعلی Candidate V2.0.4

دو بستهٔ Core و Update کاندید در `dist/candidates/v2.0.4/` ساخته شدند. بررسی `tools/verify-candidate-artifacts.php` با نتیجهٔ `CANDIDATE_ARTIFACTS_V204_OK_QA_NOT_RUN` بسته‌شدن ZIP، تطبیق SHA256 manifestها، نبود مسیر/فایل عملیاتی ممنوع، وجود runtime assets و PHP lint فایل‌های استخراج‌شده را تأیید کرد. این بررسی QA نرم‌افزار، اجرای migration، نصب تازه یا ارتقای واقعی نیست. هر دو manifest صریحاً `candidate_only=true`، `release_gate_status=NOT_RUN` و `production_availability_certified=false` دارند.

این کاندید از baseline آرشیو `PromaPay-v2.0.3.zip` ساخته شده است. Gate کامل روی source فعلی اجرا نشده است؛ بنابراین عنوان PASS ثبت‌شده در گزارش تاریخی زیر به اجرای قبلی تعلق دارد و وضعیت Candidate V2.0.4 را تأیید نمی‌کند.

جزئیات مسیر، اندازه و hash بسته‌ها در `32_CANDIDATE_ARTIFACTS.md` ثبت شده‌اند.

## گزارش تاریخی اجرای QA پیشین

**Local QA gate passed; stable release is not certified.** The current working tree produced `RELEASE_GATE_LOCAL_QA_OK_NOT_PRODUCTION_CERTIFIED` in `storage/logs/platform-release-gate-qa-20261003-rerun.log` at 14:33 local time.

The run used PHP 8.2.12, MariaDB 10.4.32 on loopback port 33387, and the disposable `proma_qa_v204` database. It linted 478 PHP files and ran 85 static, unit, database, and HTTP test scripts. No warning, fatal error, SQLSTATE error, or failure marker appeared in the passing log. A preceding QA run failed only because the temporary CLI PHP configuration had ZIP disabled for child processes; that configuration was corrected before the passing run. The older `storage/logs/platform-release-gate.log` and the first QA run do not certify this code.

Focused checks executed after the latest updater changes:

| Check | Result |
| --- | --- |
| PHP lint: `helpers/ScriptUpdateService.php`, `tests/update_preflight.php`, `tests/update_file_rollback.php` | Pass |
| `tests/update_preflight.php` | `UPDATE_PREFLIGHT_OK` |
| `tests/update_archive_limits.php` (oversized manifest and bounded ZIP reads) | `UPDATE_ARCHIVE_LIMITS_OK` |
| `tests/update_file_rollback.php` (post-preflight file failure after replacing an existing file and creating a new one) | `UPDATE_FILE_ROLLBACK_OK` |
| `tests/update_migration_recovery.php` (simulated database failure after file installation; files and recovery snapshot retained) | `UPDATE_MIGRATION_RECOVERY_OK` |
| `tests/update_v144.php` | `UPDATE_V144_OK` |
| `tests/static_v135.php` | `STATIC_V135_OK` |

All four new updater checks are registered in `tools/release-gate.php` and passed in the local QA run. They require the PHP ZIP extension. Fresh installation from the final archive, base-to-target update, database recovery, browser/device checks, production-size data, and the separate production availability gate remain open. Automatic file rollback is in-process and pre-migration only; MariaDB DDL can auto-commit and requires a tested fail-forward or restore procedure.

After the gate, two additional focused QA checks passed separately: `INTEGRATION_OUTBOX_DELIVERY_OK` and `INTEGRATION_CUSTOMER_TREND_BATCH_OK customers=24`. They are not part of the 85-script release-gate count. The temporary loopback PHP and MariaDB processes used for this run were shut down afterward.

## ادامهٔ ممیزی — تست‌های جدید و وضعیت فعلی

گزارش ۸۵ تست بالا مربوط به اجرای ثبت‌شدهٔ قبلی است. در ادامهٔ ممیزی مشخص شد تست‌های جدید V2.0.4 و چند تست افزونه در آن اجرای release gate نبوده‌اند. این تست‌ها در `tools/release-gate.php` ثبت شدند؛ DB integration فقط با opt-in تخریبی و DSN دقیق پایگاه disposable `proma_qa_v204`، و HTTP integration فقط روی localhost/loopback مجاز است. Gate بسط‌یافته هنوز روی DB اجرا نشده است.

تست‌های ایستای متمرکز پس از اصلاح CSS پاس شدند: `STATIC_V145_OK`, `STATIC_V204_PAYMENT_SCOPE_AND_SIGNATURE_OK`, `STATIC_V204_MOBILE_CUSTOMER_INSTALLMENTS_OK`, `PROMA_ACCOUNTING_V1216_OK` و تست‌های PromaRepair. PHP lint فایل‌های تغییرکرده و `git diff --check` نیز پاس شدند.

اجرای فعلی `tools/verify-release.php` روی خروجی موجود v2.0.3 شکست خورد، چون `dist/core/SHA256SUMS.txt` به `PromaAccounting-v1.2.14.zip` اشاره می‌کند اما آن آرشیو در مسیر مورد انتظار وجود ندارد. این شکست به معنی شکست کد بستهٔ فعلی نیست؛ نشان می‌دهد خروجی موجود قدیمی/ناهمگام با سورس کاری است. بستهٔ نسخهٔ جاری سورس هنوز ساخته و آزمون نصب/ارتقا نشده است.

در همان ادامه، تست‌های V2.0.4 یک حذف CSS را نشان دادند: viewهای مشتری/اقساط از کلاس‌های چندقسطی، کارت قسط و کارت مدرک استفاده می‌کردند اما قواعد CSS از فایل حذف شده بودند. قواعد و تنظیم موبایل از نسخهٔ ثبت‌شده در HEAD بازیابی شد و تست‌های ایستا پس از اصلاح پاس شدند.
