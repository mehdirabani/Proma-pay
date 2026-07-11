# چرخه عمر افزونه

چرخه‌ی پشتیبانی‌شده:

`discovered -> installed -> active -> inactive -> active`

در خطای migration یا provider، وضعیت `failed` می‌شود و خطا در رجیستری ذخیره می‌گردد. `uninstalled` به‌صورت پیش‌فرض فقط اتصال اجرایی را غیرفعال می‌کند و migration history را حذف نمی‌کند. قبل از purge باید backup، permission مدیر ارشد و عبارت دقیق «حذف کامل اطلاعات افزونه» وجود داشته باشد.
