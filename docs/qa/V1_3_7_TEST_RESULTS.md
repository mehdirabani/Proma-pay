# نتایج آزمون V1.3.7-RC.1

| آزمون | وضعیت | شواهد |
| --- | --- | --- |
| PHP lint همه فایل‌ها | PASSED | 246 فایل PHP با `C:\\xampp\\php\\php.exe -l` |
| آزمون static V1.3.7 | PASSED | `tests/static_v137.php` |
| گیت static هسته و افزونه‌ها | PASSED | `tools/release-gate.php` پس از افزودن آزمون‌های Accounting و Zarinpal |
| split migration ترمیمی | PASSED | 72 statement توسط `ScriptUpdateService::splitSql()` |
| syntax جاوااسکریپت | PASSED | `node --check` برای `assets/js/app.js` و `plugins/PromaAccounting/assets/js/accounting.js` |
| static emergency pages | PASSED | صفحه‌های 400، 403، 404، 429، 502 و 504 دارای HTML و RTL معتبر هستند |
| `/health/live` | PASSED | پاسخ JSON با HTTP 200 در PHP built-in server |
| `/health/ready` بدون تنظیم دیتابیس | PASSED | پاسخ JSON با HTTP 503 |
| launcher `installer.php` در حالت نصب‌نشده | PASSED | HTTP 302 به `install.php` |
| جلوگیری از ZIP با نسخه اشتباه | PASSED | سازنده release تا قبل از ارتقای نسخه و گیت staging، نامزد V1.3.7 را با نام V1.3.6 تولید نمی‌کند |
| لغو قرارداد در دیتابیس staging | BLOCKED | دیتابیس staging مستقل در workspace موجود نیست |
| حذف مجاز/غیرمجاز قرارداد در دیتابیس staging | BLOCKED | دیتابیس staging مستقل در workspace موجود نیست |
| نصب تازه از `install.php` | BLOCKED | نیازمند MySQL/MariaDB جدا و مسیر نصب ایزوله |
| بروزرسانی از V1.3.6 | BLOCKED | نیازمند کپی دیتابیس V1.3.6 غیرعملیاتی |
| آزمون تمام افزونه‌ها | BLOCKED | نیازمند runtime plugin و دیتابیس staging |
| مرورگرهای desktop/tablet/mobile کامل | NOT EXECUTED | UI نهایی بدون دیتابیس و حساب آزمایشی قابل مرور end-to-end نیست |
| بسته انتشار V1.3.7 | BLOCKED | گیت سختگیرانه انتشار، آزمون‌های دیتابیس و نصب را الزامی می‌داند |
| ساخت ZIP محلی | BLOCKED | افزونه `ZipArchive` در PHP CLI محلی فعال نیست؛ هیچ ZIP جدیدی ساخته نشد |

هیچ ادعای «تمام آزمون‌ها پاس شد» برای این نسخه ثبت نشده است.
