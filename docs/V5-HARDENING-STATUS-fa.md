# گزارش وضعیت Hardening نسخه V5

تاریخ بررسی: ۸ اکتبر ۲۰۲۶

## وضعیت انتشار

**RELEASE STATUS: BLOCKED**

نسخهٔ 5.0.0 آمادهٔ انتشار نیست. اصلاحات زیر در سورس محلی انجام و تست‌های مستقل آن‌ها اجرا شده‌اند؛ کد جدید هنوز روی Production نصب نشده و Restore، Upgrade، Rollback و تراکنش مالی واقعی روی Production اجرا نشده‌اند.

## Root causeها و اصلاح‌ها

| شناسه | شواهد و علت | اصلاح یا وضعیت |
|---|---|---|
| RC-001 | درخواست HTTP دامنه پیش از اصلاح، صفحه را بدون TLS تحویل می‌داد. | گزینهٔ DirectAdmin با عنوان `Force SSL with https redirect` ذخیره شد. پنل بعد از ذخیره «تنظیمات تغییر کرد» نشان داد و checkbox در بازخوانی فعال بود. درخواست GET به HTTP اکنون با `301` به همان مسیر HTTPS می‌رود. |
| RC-002 | لاگ Production نشان می‌دهد Restore بکاپ `13:54` هنگام کپی `uploads/.gitkeep` شکست خورد؛ لاگ بعدی ثبت می‌کند که دیتابیس و فایل‌های قبلی از بکاپ ایمنی بازگشته‌اند. خود لاگ علت سطح سیستم‌عامل را مشخص نمی‌کند. | کپی `zip://...` با خواندن مستقیم `ZipArchive::getStream()` و نوشتن قطعه‌ای جایگزین شد. تست محلی فایل خالی و باینری را بازیابی کرد و شکست مقصد غیرقابل‌نوشتن را گزارش داد. هنوز روی هاست تأیید نشده است. |
| RC-003 | `BackupService::restore()` فایل‌های آپلودی بزرگ‌تر از ۶۴ MiB را بدون توجه به تنظیمات PHP رد می‌کرد. `.user.ini` محلی `upload_max_filesize=160M` و `post_max_size=170M` دارد. بکاپ Production که در لاگ Restore آن دیده شد ۴۵ KB بود؛ پس سقف ۶۴ MiB علت آن رخداد مشخص نبود. | سقف Upload از `upload_max_filesize` و `post_max_size` محاسبه می‌شود؛ عبور از `post_max_size` پیام قابل اقدام می‌دهد. SQL از ZIP و فایل dump ردیف‌به‌ردیف و statement‌به‌statement روی دیسک پردازش می‌شود؛ سقف کلی ۸ GiB و سقف هر دستور متناسب با `memory_limit` است. |
| RC-004 | ZIPهای جدید فاقد manifest و checksum بودند. | ZIP جدید شامل `manifest.json` و `checksums.json` است: نسخهٔ قالب، نسخهٔ اپ، fingerprint ساختار، مشخصات دیتابیس و checksum فایل‌ها. پیش از Restore، اندازه و SHA-256 فایل‌ها کنترل می‌شود. ZIPهای V4 فاقد manifest همچنان پذیرفته می‌شوند. |
| RC-005 | Preflight updater با `ZipArchive::getFromName($entry, $maxBytes + 1)` حتی برای فایل کوچک، سقف بزرگی از حافظه می‌خواست؛ اجرای PHP 8.5.7 با `memory_limit=128M` در خواندن README بستهٔ Update به OOM خورد. | خواندن، اندازه‌گیری، checksum و نصب فایل ZIP اکنون با stream و قطعه‌های ۱ MiB انجام می‌شود؛ فایل موقت در همان پوشه نوشته و بعد از تطبیق checksum جایگزین می‌شود. آزمون Update با ZIP چندمگابایتی و مقصد دارای فایل قبلی پاس شد. |
| RC-006 | پارامترهای دارای type که مقدار پیش‌فرض `null` داشتند ولی nullable نبودند از PHP 8.4 deprecated هستند؛ خطا در runtime PHP 8.5.7 و آزمون مالی MySQL مشاهده شد. Error handler برنامه `E_DEPRECATED` را به `ErrorException` و ثبت خطای HTTP 500 تبدیل می‌کند. | همهٔ اعلان‌های مستقیم پیدا‌شده در کد runtime به nullable صریح تغییر کردند. `ReflectionMethod/Property::setAccessible()` نیز از تست‌ها حذف شد چون در PHP 8.5 deprecated است و از PHP 8.1 دیگر اثری ندارد. آزمون مالی پس از اصلاح بدون این خطاها پاس شد. |
| RC-007 | DDLهای MySQL با commit ضمنی اجرا می‌شوند؛ `rollBack()` در catch، تغییر schema را برنمی‌گرداند. مسیر قبلی updater در این حالت فقط بکاپ را نگه می‌داشت و دستیابی به نسخه قبلی DB را به اپراتور واگذار می‌کرد. | بعد از خطای migration، updater حالا schema/data را از فایل `database.sql` بکاپ قبل از Update بازمی‌گرداند و بعد فایل‌های قبلی را نصب می‌کند. اگر برگشت DB یا فایل کامل نشود، snapshotها نگه داشته می‌شوند و مسیرشان در خطا می‌آید. تست دیتابیس helper قبل از تغییر importer به stream پاس شد؛ آزمون کامل و فعلی `install()` اجرا نشده و باید با importer جدید دوباره تکرار شود. |
| RC-008 | `databaseDump()` همهٔ ردیف‌های هر جدول را با `fetchAll()` و کل dump را در یک رشته نگه می‌داشت؛ ZIP restore هم `database.sql` را با `getFromName()` کامل در حافظه بار می‌کرد. این رفتار با `memory_limit=128M` برای بکاپ‌های بزرگ قابل اتکا نبود. | dump حالا در فایل موقت نوشته می‌شود و هر جدول با PDO unbuffered و `fetch()` ردیف‌به‌ردیف خوانده می‌شود. SQL داخل ZIP با `getStream()` استخراج، اعتبارسنجی و اجرا می‌شود. در QA محلی فایل dump ۲۰٬۹۷۷٬۷۳۱ بایتی با PHP 8.5.7 و `memory_limit=128M` ساخته و اعتبارسنجی شد؛ اوج تخصیص حافظهٔ PHP گزارش‌شده ۸ MiB بود. این مشاهده آزمون read-only است و import/restore جدید را به‌تنهایی تأیید نمی‌کند. |
| RC-009 | تست عملی PHP 8.5.7 ثبت کرد که `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY` deprecated است و error handler برنامه آن را لاگ می‌کند. | اتصال PDO، installer و dump مسیر `Pdo\Mysql::ATTR_USE_BUFFERED_QUERY` را در PHP جدید به‌کار می‌برند و fallback قدیمی فقط برای runtimeهای قبل از 8.5 باقی مانده است. |

## ماتریس محیط

| مورد | Local | Production |
|---|---|---|
| OS | Windows 11 | مستقیماً تأیید نشده |
| PHP | 8.3.33 برای Apache؛ آزمون CLI با PHP 8.5.7 | 8.5.7 در صفحهٔ سلامت |
| PHP upload | `.user.ini`: 160M | مقدار مؤثر سرور در این بررسی خوانده نشد |
| PHP POST | `.user.ini`: 170M | مقدار مؤثر سرور در این بررسی خوانده نشد |
| PHP memory | `.user.ini`: 256M | مقدار مؤثر سرور در این بررسی خوانده نشد |
| وب‌سرور | Apache 2.4.68 | صفحهٔ سلامت Apache نشان می‌دهد؛ پاسخ HTTP از nginx عبور می‌کند |
| دیتابیس | MySQL 8.4.3 | MariaDB 10.11.18 |
| افزونه‌ها | ۵ پوشهٔ افزونه در سورس محلی | پنل Production: صفر افزونهٔ ثبت‌شده از صفر |

تفاوت نسخه و موتور دیتابیس ثبت شده است؛ آزمون مهاجرت بین MySQL و MariaDB هنوز انجام نشده است.

## آزمون‌های اجراشده

۱۳ تست مستقل با PHP CLI 8.5.7، `memory_limit=128M` و افزونه‌های `zip` و `mbstring` پاس شدند:

- Backup manifest/checksum، پذیرش بکاپ قدیمی، سقف Upload و تشخیص عبور از `post_max_size`
- اعتبارسنجی SQL، صحت مقادیر Restore، کپی stream فایل خالی/باینری و گزارش خطای کپی
- خطای ثبت لاگ Backup
- Preflight بستهٔ Update، بازگشت فایل Update و نگهداری Snapshot در شکست Migration
- محدودیت اندازهٔ آرشیو Update
- بررسی ایستای scope/signature پرداخت و audit امنیتی PHP
- parser جریان‌محور SQL با quote/comment مرزی و statement بزرگ‌تر از ۱ MiB

۶ آزمون Integration دیتابیس با PHP 8.5.7 و MySQL 8.4.3 پاس شدند: تخصیص مالی هنگام پرداخت هم‌زمان، مهاجرت settlement، مهاجرت `allocation_scope`، صف تطبیق پرداخت درگاه با مبلغ ساختگی، مهاجرت تعمیر بکاپ V4، و بازگردانی واقعی schema/data از ZIP ایمنی قبل از Update. آزمون‌های migration روی دیتابیس‌های موقت اجرا شدند؛ دیتابیس QA قدیمی `proma_qa_v204` فقط خوانده/برای تشخیص بررسی شد و تغییرش ندادم. آزمون مهاجرت بکاپ روی کپی دیتابیس QA اجرا شد و تعداد سطرهای users/contracts/payments/installments حفظ شد.

`php -l` با PHP 8.5.7 برای هر ۵۳۵ فایل PHP در مسیرهای کد و تست بدون خطا تمام شد. در اجرای اولیهٔ gateway روی دیتابیس QA قدیمی، ستون `users.customer_password_mode` وجود نداشت؛ این اختلاف baseline دیتابیس QA بود، نه شکست منطق gateway. اجرای مجدد روی schema تازهٔ installer پاس شد. `git diff --check` روی فایل‌های تغییر‌یافتهٔ این کار خطای whitespace نداد؛ Git فقط هشدار تبدیل line ending در Windows چاپ کرد.

## Gateهای باز و دلیل توقف

- Restore واقعی از فایل بکاپ از ابتدا تا انتها در محیط وب و Update/rollback فایل روی web SAPI هنوز اجرا نشده است. آزمون‌های Integration بالا در CLI روی MySQL 8.4.3 اجرا شدند؛ Production از MariaDB 10.11.18 استفاده می‌کند و آزمون سازگاری مهاجرت روی همان موتور/نسخه هنوز لازم است.
- import/restore کامل SQL جریان‌محور پس از آخرین تغییر هنوز با یک دیتابیس MySQL/MariaDB ایزوله دوباره end-to-end اجرا نشده است. قابلیت resumable برای ادامهٔ Restore پس از قطع درخواست نیز پیاده نشده است.
- اجرای کامل `ScriptUpdateService::install()` با migration شکست‌خورده هنوز در integration آزمایش نشده است: آزمون محلی پیش از رسیدن به migration و در مرحله ساخت بکاپ ایمنی متوقف شد؛ خود بکاپ از حدود ۲٫۳ GB فایل‌های storage تشکیل می‌شد. مسیر بازیابی دیتابیس به‌تنهایی تست شده است. UX کامل برای اجرای دستی rollback از پنل نیز تکمیل نشده است.
- Production Restore، Upgrade، Rollback و تراکنش مالی واقعی هنوز انجام نشده‌اند. صفحهٔ Production نشان می‌دهد آخرین Restore ناموفق بوده، ولی Safety backup برگشت را ثبت کرده است. هیچ‌کدام از کدهای توسعه در Production نصب نشده‌اند.
- DirectAdmin نشان داد مصرف دیسک حدود `1127.7 / 1536 MB` است؛ فضای باقیمانده حدود 408 MB است. تا محاسبهٔ اندازهٔ واقعی بکاپ و فضای Snapshot/Restore، تمرین Restore روی Production اجرا نمی‌شود.
- شمار افزونه‌های Production با سورس محلی هم‌خوان نیست. نصب افزونه‌های محلی در Production بدون بستهٔ Release و تست سازگاری انجام نشده است.

## تصمیم

ریدایرکت HTTPS در Production تأیید شده است. اصلاحات Backup/Restore فقط در workspace محلی هستند. تا رفع Gateهای بالا و اجرای آزمون‌های واقعی روی محیط Production، **Proma Pay 5.0.0 منتشر یا نهایی اعلام نمی‌شود**.

## تکمیل Candidate نسخه ۵.۰.۱ — ۹ اکتبر ۲۰۲۶

- نسخهٔ ۵.۰.۱ تفکیک هزینهٔ پرداخت‌شده توسط مجموعه به مرجع ثالث را از دریافت مشتری اضافه می‌کند و ماندهٔ مشتری را فقط با تخصیص دریافت واقعی کاهش می‌دهد.
- جزئیات و خروجی صف معوقات، اصل اقساط، جریمه عادی، جریمه حقوقی و هزینهٔ قابل وصول را جدا می‌کنند.
- گیت محلی نسخهٔ ۵.۰.۱ با ۷۸۳ فایل PHP lint و مجموعهٔ static/unit موفق شد.
- آزمون Integration، migration، rollback و پرداخت هم‌زمان روی دیتابیس QA اجرا نشد؛ پیکربندی DB موجود remote است و `PROMA_TEST_DB_DSN` تنظیم نیست.
- وضعیت همچنان **Candidate / RELEASE BLOCKED** است. نتایج دقیق در `docs/qa/V5_0_1_TEST_RESULTS.md` ثبت شده‌اند.
