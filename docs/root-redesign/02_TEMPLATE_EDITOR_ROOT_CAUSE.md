# علت ریشه‌ای ویرایشگر قالب قرارداد

## بازتولید از سورس

در `views/settings/contracts.php`، تب «ویرایش ساده» در markup قدیمی `active` بود و textarea حاوی HTML نیز هم‌زمان وجود داشت. در `views/layouts/app.php` متغیر `$needsRichEditor` فقط برای `contracts/show/*` و چند route دیگر، نه `settings/contracts/template`، Quill را بارگذاری می‌کرد. `initPromaRichEditors()` نیز هنگام نبودن `window.Quill` بدون گزارش بازمی‌گشت. نتیجه: HTML خام زیر تب فعالِ دیداری نمایش داده می‌شد.

## تغییر معماری

- `assets/js/contract-template-editor.js` به adapter مستقل صفحه تبدیل شد.
- حالت اولیه همیشه `source` است و تا آماده‌بودن واقعی Quill، دکمهٔ دیداری غیرفعال می‌ماند.
- Quill و adapter فقط در route `settings/contracts/template` بارگذاری می‌شوند.
- Chart.js فقط در مسیرهایی که نمودار دارند (`dashboard`، فهرست مشتریان/قراردادها و مسیرهای حسابداری) بارگذاری می‌شود؛ صفحه ویرایش قالب دیگر آن را دانلود نمی‌کند.
- `app.js` دیگر textarea مخصوص قالب را به‌عنوان rich editor عمومی bind نمی‌کند.
- source و visual قبل از نمایش/submit همگام می‌شوند و recovery محلی پس از تغییر نگهداری می‌شود.
- find/replace و جایگزینی متن از modal مشترک هسته استفاده می‌کنند؛ dialog بومی مرورگر در این جریان وجود ندارد.
- تبدیل plain text به HTML فقط پس از انتخاب و تأیید صریح «ویرایش ساده» رخ می‌دهد؛ منبع تبدیل، کاربر، زمان و هش نسخه در رخداد `template_format_converted` ثبت می‌شود.
- چرخه‌حیات adapter در `data-template-editor-lifecycle` قابل مشاهده است: `booting`، `source_ready`، `visual_ready`، `degraded_source_only`، `asset_error` و `saving`.
- انتشار HTML ساختاریافته در محیط فاقد افزونهٔ PHP DOM رد می‌شود؛ fallback مبتنی بر regex حذف شد تا امنیت ظاهری به‌جای sanitization واقعی جا زده نشود.

## رفتار degraded

اگر `window.Quill` در دسترس نباشد یا initialization خطا بدهد:

1. `source` تنها حالت فعال باقی می‌ماند.
2. متن textarea تغییر نمی‌کند و قابل ذخیره/پیش‌نمایش است.
3. هشدار هماهنگ با هسته نمایش داده می‌شود.
4. تلاش مجدد برای بارگذاری asset فقط با کلیک کاربر انجام می‌شود؛ loop یا polling وجود ندارد.

## موارد نیازمند اجرای واقعی

| بررسی | وضعیت |
| --- | --- |
| syntax JS، lint PHP و همهٔ `tests/static*.php` | PASSED |
| حذف `script`/event handler/URL ناامن و محدودسازی span جدول در sanitizer DOM | PASSED — `tests/contract_template_renderer_security.php` |
| ۲۰ بار جابه‌جایی source/visual با hash محتوا | NOT EXECUTED — Playwright CLI در محیط حاضر خروجی/جلسه قابل استفاده نداد؛ browser QA لازم است |
| خطای 404 فایل Quill و fallback | NOT EXECUTED — browser/network mock لازم است |
| CSP و console | NOT EXECUTED — browser QA لازم است |
| ذخیره، reload و پیش‌نمایش با MariaDB | BLOCKED — `PROMA_TEST_DB_DSN` موجود نیست |
