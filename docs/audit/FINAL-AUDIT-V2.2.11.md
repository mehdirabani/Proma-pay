# گزارش نهایی ممیزی Proma Pay V2.2.11

تاریخ: ۱۴۰۵/۰۷/۱۴ — مسیر منبع: `F:\proma-pay`  
وضعیت انتشار: **Candidate؛ آزمون یکپارچه‌سازی و شواهد تولید کامل نیست.**

## نسخه‌ها

| جزء | نسخهٔ بسته‌بندی‌شده | وضعیت |
|---|---:|---|
| Core | 2.2.11 | Candidate |
| PromaAccounting | 1.2.23 | Candidate |
| PromaRepair | 1.2.4 | Candidate |
| PromaAffiliate | 1.0.5 | Candidate |
| PromaSignConnect | 1.2.3 | Candidate؛ سازگاری Core 2.2.11 آزموده نشده |
| PromaZarinpal | 1.0.3 | بدون تغییر |

## اصلاحات ممیزی‌شده

- routeهای مرده و مجوزهای تکراری/یتیم Accounting از manifest حذف شدند. migrationهای قدیمی و سوابق مالی حذف نشده‌اند.
- ماندهٔ حسابداری و مقادیر مالی تعمیرات با عدد صحیح دقیق پردازش می‌شوند. ورودی مبلغ منفی، اعشاری یا خارج از دامنه رد می‌شود؛ مقدار تاریخیِ کسری تومان به data preflight نیاز دارد.
- نمایش راز دستگاه تعمیرات فقط پس از بازکردن امن session انجام می‌شود، یک‌بارمصرف است و رویداد ممیزی دارد.
- triggerهای Affiliate برای ثبت مشتری، ثبت قرارداد و اولین پرداخت جدا شدند؛ Accounting وابستگی صریح است و گزارش تطبیق دفترکل اضافه شد. این مسیرهای DB در محیط QA اجرا نشده‌اند.
- route و تنظیم ورود OTP ناقص SignConnect حذف شدند؛ OTP برای امضا و تأیید عملیات حساس باقی است. اسناد تاریخی 1.2.1/1.2.2 اکنون صریحاً تاریخی‌اند. `tested_core_max=1.5.5` عمداً تغییر نکرده، چون آزمون سازگاری Coreهای جدید اجرا نشده است.
- migrationهای تعمیرات و کیف پول Affiliate که `ADD COLUMN/INDEX` مستقیم داشتند شرط‌دار شدند تا اجرای مجدد بعد از شکست میانی دوباره به خطای «از قبل وجود دارد» نخورد. دو migration هسته‌ای بسته نیز شرط‌های `information_schema` دارند.
- نسخهٔ برنامه، manifest، پکیج و service worker روی 2.2.11 همگام‌اند. کانال انتشار `candidate` است.
- Release Gate برای 732 فایل PHP در محدودهٔ برنامه از tokenizer نحوی استفاده می‌کند و dependency treeهای `vendor`/`node_modules` را از lint برنامه کنار می‌گذارد. route integrity برای 5 manifest و 127 route بررسی شد.

## نتیجهٔ آزمون‌ها

- همهٔ آزمون‌های ایستا و واحد فهرست‌شده در `tools/release-gate.php --local` گذشتند؛ از جمله بررسی route/permission، Repair money/session، سازگاری Affiliate، SignConnect و retry-safety migration. آزمون هم‌زمانی session نیز گذشت.
- `tools/verify-candidate-artifacts.php` با موفقیت Core، Update، Full و ZIPهای افزونه را از نظر manifest، هش فایل‌ها، مسیرها و داده‌های ممنوع بررسی کرد. فایل‌های `.env`، storage/receipt، cache و پوشه‌های test/docs/runtime-development لاراول در بسته نیستند.
- **آزمون‌های دیتابیس/HTTP اجرا نشدند.** `PROMA_TEST_DB_DSN`، `PROMA_QA_BASE_URL` و `PROMA_TEST_ALLOW_DESTRUCTIVE=1` تنظیم نشده‌اند و سرور محلی QA در دسترس نیست. بنابراین اجرای migration واقعی، نصب پاک، ارتقای 2.2.10→2.2.11، تراکنش/رقابت Affiliate و آزمون HTTP تأیید نشده‌اند.
- `tools/availability-gate.php --stable` به‌علت نبود `PROMA_PRODUCTION_AVAILABILITY_EVIDENCE` مسدود شد. هیچ ادعای Stable یا گواهی تولید ثبت نمی‌شود.

## بسته‌ها

آپدیت تفاضلی برای Core `2.2.10` ساخته شده است. فایل‌های ZIP و SHA-256 آن‌ها در `SHA256SUMS.txt` کنار همین گزارش قرار دارد. بستهٔ تحویل یکپارچه، Core، Update، Full Core+plugins و ZIP مستقل هر افزونه را در بر می‌گیرد.

Source revision برابر `31c11c8c8173863cbbad8baf424ab1691318ce0d` است؛ working tree هنگام ساخت کاندید پاک نبوده و manifest تعداد/هش وضعیت منبع را ثبت می‌کند. بسته‌ها از فایل‌های همان working tree ساخته شده‌اند و باید تا تکمیل QA ایزوله و آزمون سازگاری SignConnect فقط Candidate تلقی شوند.
