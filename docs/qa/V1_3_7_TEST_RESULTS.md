# نتایج آزمون V1.3.7

| آزمون | وضعیت | شواهد |
| --- | --- | --- |
| PHP lint همه فایل‌ها | PASSED | همه فایل‌های PHP پروژه با `C:\\xampp\\php\\php.exe -l` |
| آزمون static V1.3.7 | PASSED | `tests/static_v137.php` |
| گیت static هسته و افزونه‌ها | PASSED | `tools/release-gate.php` پس از افزودن آزمون‌های Accounting و Zarinpal |
| split migration ترمیمی | PASSED | 72 statement توسط `ScriptUpdateService::splitSql()` |
| syntax جاوااسکریپت | PASSED | `node --check` برای `assets/js/app.js` و `plugins/PromaAccounting/assets/js/accounting.js` |
| static emergency pages | PASSED | صفحه‌های 400، 403، 404، 429، 502 و 504 دارای HTML و RTL معتبر هستند |
| `/health/live` | PASSED | پاسخ JSON با HTTP 200 در PHP built-in server |
| `/health/ready` بدون تنظیم دیتابیس | PASSED | پاسخ JSON با HTTP 503 |
| launcher `installer.php` در حالت نصب‌نشده | PASSED | HTTP 302 به `install.php` |
| جلوگیری از ZIP با نسخه اشتباه | PASSED | سازنده release تا قبل از ارتقای version source، نامزد V1.3.7 را با نام قبلی تولید نمی‌کند |
| لغو قرارداد با اصلاحیه مالی | PASSED | پرداخت موفق اصلاح شد، قرارداد `cancelled`، اقساط `cancelled` و `previous_status=active` ثبت شدند |
| حذف مجاز/غیرمجاز قرارداد | PASSED | حذف قرارداد دارای پرداخت رد شد؛ قرارداد آزمایشی بدون وابستگی archive و حذف شد |
| قفل عملیات قرارداد لغوشده | PASSED | تغییر متن، بازگردانی گروهی قسط و کنترل‌های UI مربوط به ویرایش/پرداخت رد یا پنهان شدند |
| نصب تازه از `install.php` | PASSED | MariaDB `10.4.32` مستقل؛ 44 جدول، مدیر نخست و 56 تنظیم اولیه ایجاد شدند |
| بروزرسانی از V1.3.6 | PASSED | snapshot commit `05fb9fd` نصب شد و `proma-update_v1-3-7.zip` آن را همراه backup پیش از ارتقا به `1.3.7` رساند |
| آزمون افزونه‌ها | PASSED | static، unit، integration mock، sandbox و امنیت Accounting و Zarinpal در release gate اجرا شدند |
| UI دسکتاپ و موبایل | PASSED | ورود مدیر، dashboard، فهرست و جزئیات قرارداد در عرض 1280 و 390 بدون overflow افقی بررسی شدند |
| ویرایشگر متن قرارداد | PASSED | Quill، toolbar و متن HTML قالب در صفحه واقعی قرارداد بارگذاری شدند؛ تگ HTML خام در متن نمایشی دیده نشد |
| مرور کامل نقش‌های مشتری، اپراتور و وکیل | NOT EXECUTED | این نوبت روی مسیرهای مدیر و قرارداد تمرکز داشت |
| بسته انتشار V1.3.7 | PASSED | archive کامل، update، manifest و دو plugin archive باز و بررسی شدند |
| ساخت ZIP محلی | PASSED | build با `ZipArchive` فعال اجرا شد و checksum archiveها تولید شد |

محدودهٔ آزمون‌های اجراشده و موارد اجرا نشده صریحاً در همین جدول ثبت شده‌اند.
