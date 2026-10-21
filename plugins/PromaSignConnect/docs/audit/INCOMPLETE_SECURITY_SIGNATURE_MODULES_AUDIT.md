# ممیزی ماژول‌های ناقص امنیت و امضا

در نسخه `1.1.1-rc.2` شش route زیر همگی به branch نهایی یک view مشترک می‌رسیدند
و هیچ POST route یا service اختصاصی نداشتند:

| Route | عنوان | وضعیت پیشین | ریسک |
|---|---|---|---|
| `settings/mfa` | احراز هویت دومرحله‌ای | پیام RC | High |
| `settings/otp-security` | محدودیت‌ها و امنیت OTP | پیام RC | High |
| `settings/signers` | امضاکنندگان | پیام RC | High |
| `settings/hashes` | هش‌ها و یکپارچگی | پیام RC | Critical |
| `settings/document-versions` | نسخه‌های اسناد | پیام RC | High |
| `settings/evidence-retention` | سیاست نگهداری شواهد | پیام RC | Critical |

علت ریشه‌ای، تعریف navigation پیش از طراحی schema و service بود. تنها GET عمومی
`DashboardController@section` وجود داشت و branch نهایی view پیام غیرفعال نشان
می‌داد. مجوز GET نیز عمومی‌تر از مجوز تغییر تنظیمات بود.

اصلاح موردنیاز: migration versioned، schema whitelist، POST route با CSRF و
permission، service transactional، audit، PRG، فرم فارسی و اتصال policy به
OtpService، SignatureHashRegistry و سرویس‌های سند/نگهداری.

