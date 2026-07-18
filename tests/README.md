# تست‌های نسخه V1.2.6

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
