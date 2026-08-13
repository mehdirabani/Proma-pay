# ممیزی خطای ذخیره تنظیمات بله

نسخه بررسی‌شده: `1.1.1-rc.2`

## مسیر بازتولید

`POST plugin/sign-connect/bale/save` از فرم صفحه
`plugin/sign-connect/settings/bale`.

## یافته قطعی از کد

- عملیات save به API بله درخواست نمی‌فرستد؛ فراخوانی `BaleSafirProvider::send`
  فقط در route صریح test انجام می‌شود.
- save قبلی transaction نداشت؛ در نتیجه خطای audit پس از INSERT می‌توانست پاسخ
  خطا ایجاد کند، در حالی که ردیف تنظیمات قبلاً ذخیره شده بود.
- وضعیت اولیه `draft` بود، نه وضعیت دقیق `configured_not_verified`.
- validation عنوان، Bot ID و secret یک پیام عمومی مشترک داشت.
- رمزگذاری secret به `PROMA_APP_KEY` وابسته است؛ نبود یا کوتاه‌بودن کلید محیطی
  باعث exception امن اما عمومی می‌شود.
- فرم فقط پیام کلی controller را نمایش می‌داد و علت field-level مشخص نبود.
- Bot ID فقط ارقام فارسی را normalize می‌کرد و ارقام عربی، فاصله و کنترل‌کاراکتر
  به‌صورت کامل مدیریت نمی‌شدند.

## اثر داده

به‌دلیل نبود transaction، failure در audit می‌توانست configuration را ایجاد کند
اما UI خطا نشان دهد. API key خام در دیتابیس ذخیره نمی‌شود و قبل از INSERT با
AES-256-GCM رمز می‌شود. هیچ provider call در save عادی وجود ندارد.

## اصلاح

- transaction واحد برای validation، encryption، configuration و audit؛
- status اولیه `configured_not_verified`؛
- خطاهای مستقل عنوان، Bot ID، secret و duplicate؛
- catalog فارسی قابلیت‌ها؛
- test send مستقل با شماره و متن آزمایشی؛
- activation فقط بعد از آخرین test موفق.

## آزمون لازم

ذخیره جدید، ویرایش بدون تغییر secret، rollback در خطای audit، duplicate title،
Bot ID فارسی/عربی، APP_KEY نامعتبر، عدم provider call در save و lifecycle
save → test → activate.

