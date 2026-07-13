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

تست‌های نصب تازه، migration، نصب افزونه و workflowهای مالی باید روی یک MySQL/MariaDB سالم و دیتابیس موقت اجرا شوند. در محیط توسعه فعلی MariaDB سیستم crash می‌کند و این تست‌ها تا رفع مشکل سرویس دیتابیس معتبر نیستند.
