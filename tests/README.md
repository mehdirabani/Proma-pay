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

آزمون بایگانی امن v7.0.4 به یک MySQL آزمایشی جدا با نام `proma_qa_customer_archive_<10-hex>` نیاز دارد که از schema نصب قدیمی v7.0.4 ساخته شده باشد. آزمون migration، FKهای واقعی، بایگانی و بازیابی، رقابت هم‌زمان با ساخت قرارداد و شکست AuditLog را اجرا می‌کند؛ `PROMA_TEST_ALLOW_DESTRUCTIVE=1` باید فقط برای همین دیتابیس موقت تنظیم شود:

```powershell
$env:PROMA_TEST_DB_DSN = 'mysql:host=127.0.0.1;port=3306;dbname=proma_qa_customer_archive_0123456789;charset=utf8mb4'
$env:PROMA_TEST_DB_USER = 'root'
$env:PROMA_TEST_DB_PASSWORD = ''
$env:PROMA_TEST_ALLOW_DESTRUCTIVE = '1'
php tests/integration_customer_archive_v704.php
```

## مرکز تطبیق مالی v7.0.4

تست ثابت مرز فقط‌خواندنی، محدودهٔ پوشش و گردش کار را بررسی می‌کند:

```powershell
php tests/static_financial_reconciliation_v704.php
```

آزمون پذیرش MySQL به schema آزمایشی تازه و اختصاصی با نام `proma_qa_fin_recon_<10-hex>` نیاز دارد. این آزمون migrationهای افزایشی، قواعد T01 تا T24، کنترل نقش‌ها، snapshot و fingerprint، rollback خطای audit، idempotency، اجرای batch ناقص و رقابت با پرداخت رسمی را بررسی می‌کند. harness فقط وقتی متغیرهای DB و `PROMA_TEST_ALLOW_DESTRUCTIVE=1` تنظیم باشند اجرا می‌شود؛ DSN را هرگز به دیتابیس برنامه یا دیتابیس مشترک QA ندهید:

```powershell
$env:PROMA_TEST_DB_DSN = 'mysql:host=127.0.0.1;port=3306;dbname=proma_qa_fin_recon_0123456789;charset=utf8mb4'
$env:PROMA_TEST_DB_USER = 'root'
$env:PROMA_TEST_DB_PASSWORD = ''
$env:PROMA_TEST_ALLOW_DESTRUCTIVE = '1'
php tests/integration_financial_reconciliation_v704.php
```

گزارش قواعد، رویدادهای قابل تطبیق، تعریف شکاف تاریخی و گردش اصلاح را در `docs/finance/FINANCIAL_RECONCILIATION_CENTER.md` ببینید.

## میزکار عملیات وصول v7.0.4

تست ثابت مرزهای مالکیت، یکپارچگی مسیرهای تماس، صفحه‌بندی Timeline، تعریف KPIها و حفظ تاریخچهٔ وعده را کنترل می‌کند:

```powershell
php tests/static_collection_operations_v704.php
```

آزمون پذیرش T01 تا T28 روی دیتابیس MySQL ایزوله اجرا می‌شود. ابتدا یک schema کامل v7.0.4 نصب‌شده بسازید؛ دیتابیس باید نام `proma_qa_collection_ops_<10-hex>` داشته باشد. تست migration، صف قرارداد، مالکیت، پیگیری، وعده، تخصیص پرداخت، KPI، ۱۰۰۰ قرارداد، رقابت تکمیل وظیفه با تسویه و شکست AuditLog را بررسی می‌کند. فقط روی همین schema موقت اجرا شود:

```powershell
$env:PROMA_TEST_DB_DSN = 'mysql:host=127.0.0.1;port=3306;dbname=proma_qa_collection_ops_0123456789;charset=utf8mb4'
$env:PROMA_TEST_DB_USER = 'root'
$env:PROMA_TEST_DB_PASSWORD = ''
$env:PROMA_TEST_ALLOW_DESTRUCTIVE = '1'
php tests/integration_collection_operations_v704.php
```

شرح ممیزی، فرمول‌ها و قواعد دادهٔ ناقص در `docs/finance/COLLECTION_OPERATIONS_WORKSPACE.md` است.

## تحلیل ریسک پرتفوی و جریان ورودی برنامه‌ریزی‌شده v7.0.4

آزمون ثابت تعریف KPI، مرزهای فقط‌خواندنی، فیلترها و مسیرهای Drill-down را بررسی می‌کند:

```powershell
php tests/static_portfolio_risk_analytics_v704.php
```

آزمون integration را فقط روی schema ایزولهٔ MySQL با نام `proma_qa_portfolio_<10-hex>` و نسخهٔ نصب‌شدهٔ v7.0.4 اجرا کنید. سناریوهای منبع شامل پرداخت گروهی، اصلاح‌شده، برگشتی، پیش‌پرداخت، هزینه حقوقی، قسط لغوشده، cohort، aging، forecast و تطبیق جزئیات با KPI است:

```powershell
$env:PROMA_TEST_DB_DSN = 'mysql:host=127.0.0.1;port=3306;dbname=proma_qa_portfolio_0123456789;charset=utf8mb4'
$env:PROMA_TEST_DB_USER = 'root'
$env:PROMA_TEST_DB_PASSWORD = ''
$env:PROMA_TEST_ALLOW_DESTRUCTIVE = '1'
php tests/integration_portfolio_risk_analytics_v704.php
```

شرح Source of Truth، فرمول شاخص‌ها، محدودیت پوشش تاریخی و وضعیت آزمون‌ها در `docs/finance/PORTFOLIO_RISK_ANALYTICS.md` است.

## اصلاحیه قرارداد و محافظت برنامه اقساط v7.0.4

آزمون ثابت، قفل `syncInstallmentSchedule()`، مسیر مستقیم Model و حذف منطقی را کنترل می‌کند:

```powershell
php tests/static_contract_amendment_v704.php
```

آزمون‌های MySQL را فقط روی نصب کامل و تازه v7.0.4 با schema ایزولهٔ `proma_qa_contract_amendment_<10-hex>` اجرا کنید. سناریوی مدیریت اقساط نسخه‌ای، تأییدکنندهٔ مستقل، اعمال یک‌بارهٔ برنامه، ابطال منطقی و rollback هزینه حقوقی را بررسی می‌کند. آزمون با نام تاریخی v139 مسیرهای مستقیم Contract/Installment، فراخوانی مستقیم متد محافظت‌شده با reflection، لغو دسته‌جمعی، رد افزودن/ویرایش/حذف قسط سفارشی و حفظ قسط سفارشی موجود هنگام اصلاحیهٔ تأییدشده را می‌سنجد. هر دو تست را می‌توان روی همین schema ایزوله اجرا کرد:

```powershell
$env:PROMA_TEST_DB_DSN = 'mysql:host=127.0.0.1;port=13307;dbname=proma_qa_contract_amendment_0123456789;charset=utf8mb4'
$env:PROMA_TEST_DB_USER = 'root'
$env:PROMA_TEST_DB_PASSWORD = ''
$env:PROMA_TEST_ALLOW_DESTRUCTIVE = '1'
php tests/integration_contract_installment_management_v157.php
php tests/integration_v139_custom_installments.php
```

از دیتابیس برنامه یا schema مشترک QA استفاده نکنید. پیش‌نویس الحاقیهٔ تولیدشده پس از اعمال نسخه مالی، به هر دو نسخه مالی و اصلاحیه متصل است و تا ثبت رضایت طرف قرارداد منتشر یا نهایی نمی‌شود.

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
