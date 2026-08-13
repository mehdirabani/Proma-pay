# ممیزی UI و placeholderهای Proma Sign & Connect

## یافته‌ها

- یک پیام RC مشترک در `views/settings-center.php` برای routeهای فاقد branch بود.
- قابلیت‌های بله با کلید انگلیسی اصلی نمایش داده می‌شدند.
- Event Catalog حساسیت را با کلید انگلیسی نشان می‌داد.
- Notification Rules دارای جدول عریض و بدون خلاصه، toolbar و mobile cards بود.
- navigation در موبایل همه گزینه‌ها را به‌صورت فهرست بلند نگه می‌داشت.
- technical key رویدادها در چند صفحه وزن بصری زیادی داشت.

## تصمیم

هر route بدون backend باید یا عملیاتی شود یا از navigation تولیدی حذف گردد.
برای این نسخه، شش route امنیت/امضا به policy backend نسخه‌دار متصل می‌شوند؛
رویدادها و قوانین با عنوان و حساسیت فارسی، خلاصه و layout واکنش‌گرا بازطراحی
می‌شوند و متن RC از UI حذف می‌شود.

