# راهنمای آزمون‌های Proma Pay

این مخزن شامل آزمون‌های ثابت، آزمون‌های smoke افزونه و آزمون‌های integration وابسته به MySQL است. آزمون‌های نام‌گذاری‌شده با نسخه‌های قدیمی، snapshots سازگاری‌اند؛ بخش زیر روش جاری اجرا را مشخص می‌کند.

## آزمون‌های سریع بدون دیتابیس

با PHP 8.3 در PowerShell، آزمون‌های static را اجرا کنید:

~~~powershell
Get-ChildItem tests/static_*.php | ForEach-Object {
  & php $_.FullName
  if ($LASTEXITCODE -ne 0) { throw "Failed: $($_.Name)" }
}
~~~

smokeهای Partnership Ledger:

~~~powershell
php plugins/PromaPartnershipLedger/tests/domain-smoke.php
php plugins/PromaPartnershipLedger/tests/contract-smoke.php
~~~

بررسی MySQL افزونه فقط با schema آزمایشی دارای پیشوند pip_test_ یا test_ مجاز است. mysql-integration-smoke.php پیش از هر نوشتن نام schema را کنترل می‌کند:

~~~powershell
$env:PROMA_DB_DSN = 'mysql:host=127.0.0.1;port=3306;dbname=pip_test_partnership;charset=utf8mb4'
$env:PROMA_DB_USER = 'root'
$env:PROMA_DB_PASSWORD = ''
php plugins/PromaPartnershipLedger/tests/mysql-integration-smoke.php
~~~

تست‌های destructive سامانه از PROMA_TEST_DB_DSN، PROMA_TEST_DB_USER و PROMA_TEST_DB_PASSWORD استفاده می‌کنند. این متغیرها باید به دیتابیس موقت و ایزوله اشاره کنند. router آزمایشی qa_server_router_v150.php خطای 503 می‌دهد اگر DSN ایزوله تنظیم نشده باشد و دیگر به پورت ثابت یا دیتابیس پیش‌فرض برنامه وصل نمی‌شود. seed سابقه مشتری، seed نقش‌ها و راه‌اندازی حسابداری را خودش اجرا می‌کند.

## Worker صف مرکزی

برای اجرای یک batch محدود از صف outbox:

~~~powershell
php cron/outbox.php
~~~

در سرور، آن را با زمان‌بند سیستم هر دقیقه اجرا کنید. نمونهٔ Linux:

~~~text
* * * * * /usr/bin/php /path/to/proma/cron/outbox.php >> /path/to/proma/storage/logs/outbox-worker.log 2>&1
~~~

اجرای worker hookها و اعلان‌های منتظر را فعال می‌کند؛ برای تست، فقط از دیتابیس آزمایشی استفاده کنید.

## تست‌های نسخه‌ای قدیمی

دستورهای پایین‌تر در این فایل سابقهٔ آزمون نسخه‌های V1.2.6 تا V1.3.x هستند؛ برای وضعیت همین build، از docs/qa/FULL_SYSTEM_AUDIT_2026-10-22.md و plugins/PromaPartnershipLedger/docs/TEST_REPORT.md استفاده کنید.

`static_v126.php` تست بدون دیتابیس برای version source، manifest افزونه، محاسبه دقیق مبلغ، پاک‌سازی payload رویداد، پایداری پیشنهاد آواتار و وجود migrationها است.

تست را از ریشه پروژه اجرا کنید:

```text
php tests/static_v126.php
```

تست رابط کاربری نسخه جاری:

```text
php tests/static_v129.php
```

تست موتور قالب و چاپ قرارداد نسخه `V1.3.0`:

```text
php tests/static_v130.php
```

تست چاپ فشرده، بند مهم، نگه‌داری نسخه‌ها و منوی گروهی افزونه‌ها در `V1.3.1`:

```text
php tests/static_v131.php
```

تست انتشار رابط مدرن حسابداری، دارایی‌های route-scoped، Chart.js محلی و جداسازی ZIP پلاگین در `V1.3.2`:

```text
php plugins/PromaAccounting/tests/v120.php
php tests/static_v132.php
```

تست چرخه بروزرسانی ZIP پلاگین، حذف فیزیکی امن و پاک‌سازی لاگ‌های بکاپ در `V1.3.3`:

```text
php tests/static_v133.php
```

تست‌های درگاه زرین‌پال و رجیستری درگاه نسخه `V1.3.4`:

```text
php tests/static_v134.php
php plugins/PromaZarinpal/tests/static.php
php plugins/PromaZarinpal/tests/Unit/AmountConverterTest.php
php plugins/PromaZarinpal/tests/Unit/ZarinpalClientTest.php
php plugins/PromaZarinpal/tests/Security/SecretCipherTest.php
php plugins/PromaZarinpal/tests/Security/StaticSecurityTest.php
php plugins/PromaZarinpal/tests/Sandbox/SandboxProtocolTest.php
php plugins/PromaZarinpal/tests/Integration/OfficialProtocolTest.php
```

`tests/integration_zarinpal_v134.php` روی MySQL/MariaDB موقت اجرا می‌شود و DSN را از `PROMA_TEST_DB_DSN`، `PROMA_TEST_DB_USER` و `PROMA_TEST_DB_PASSWORD` می‌خواند.

تست رجیستری مرکزی فایل در `V1.3.8` نیز روی دیتابیس آزمایشی اجرا می‌شود و همان متغیرهای محیطی را می‌خواند:

```text
php tests/integration_v138_file_registry.php
```

تست رگرسیون پرداخت دستی و ایزولاسیون اعلان/پلاگین در `V1.3.5`:

```text
php tests/static_v135.php
```

تست‌های رگرسیون ورود، آواتار، تأیید مشخصات، ایجاد یکتای قرارداد و پرداخت دستی نسخه `V1.4.0`:

```text
php tests/static_v140.php
php tests/integration_v140_core_workflows.php
```

تست integration به دیتابیس ایزوله نیاز دارد و متغیرهای `PROMA_TEST_DB_DSN`، `PROMA_TEST_DB_USER` و `PROMA_TEST_DB_PASSWORD` را می‌خواند.
