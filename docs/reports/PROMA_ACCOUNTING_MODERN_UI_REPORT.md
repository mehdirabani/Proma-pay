# PROMA ACCOUNTING MODERN UI REPORT

## 1. Visual audit

ممیزی پیش از اجرا در `docs/debug/PROMA_ACCOUNTING_MODERN_UI_AUDIT.md` ثبت شد و منبع canonical افزونه `plugins/PromaAccounting/` تشخیص داده شد.

## 2. Main UI problems

کارت‌های تخت، فاصله‌های نامنظم، فرم‌های طولانی، جدول موبایل ناکارآمد، نبود حالت تاریک کامل، CSS سراسری و Chart.js خارجی مشکلات اصلی بودند.

## 3. Design-system integration

همه صفحات از shell، sidebar، top navigation، breadcrumb، دکمه‌ها و فونت محلی هسته استفاده می‌کنند و shell جدیدی ساخته نشد.

## 4. Color system

رنگ‌های رسمی `#6A1B9A`، `#4A148C`، `#8E24AA` و `#B388FF` اعمال شدند؛ سبز و قرمز فقط برای اثر مالی و وضعیت استفاده می‌شوند.

## 5. Typography

سلسله‌مراتب فشرده 11 تا 28 پیکسل با فونت محلی هسته و اعداد مالی 16 تا 22 پیکسل استفاده شد.

## 6. Page spacing

padding صفحه 24px، کارت 22px و gap بخش 24px است؛ در موبایل padding به 12 تا 15px کاهش می‌یابد.

## 7. Dashboard redesign

داشبورد شامل سربرگ فشرده، شش KPI، روند پرداخت/دریافت، عملیات سریع و آخرین تراکنش‌هاست.

## 8. KPI redesign

کارت سفید 16px با سایه بسیار ملایم، آیکن کوچک و مقدار خوانا جایگزین کارت‌های تخت قبلی شد.

## 9. Chart redesign

Chart.js `4.4.7` محلی با محورهای RTL، اعداد فارسی، tooltip تومان و رنگ‌های مالی کنترل‌شده پیاده‌سازی شد.

## 10. User-account redesign

جستجو، فیلتر نقش/مانده/وضعیت، مرتب‌سازی، جدول دسکتاپ، avatar و کارت موبایل اضافه شد.

## 11. Ledger redesign

خلاصه شش‌گانه، فرم سند هم‌تراز، پیش‌نمایش قبل/بعد و نمایش صریح `+/-` برای همه تراکنش‌ها ارائه شد.

## 12. Commission redesign

مبنای محاسبه، مبلغ خام، مبلغ نهایی، وضعیت فارسی و عملیات مالی در دسکتاپ و موبایل سازمان‌دهی شدند.

## 13. Commission-rule redesign

فرم ایجاد قانون و کارت‌های واکنش‌گرا با اولویت، محدودیت، زمان ثبت و وضعیت تأیید جایگزین جدول شلوغ شدند.

## 14. Settings redesign

خلاصه تنظیمات فعال، ناوبری بخشی، کارت‌های عمومی/محدودیت/گردکردن/ثبت و یک action ذخیره ثابت ایجاد شد.

## 15. Form redesign

ورودی، select و input group ارتفاع 44px، focus ring مشترک، label منظم و پسوند تومان/درصد هم‌تراز دارند.

## 16. Checkbox and switch redesign

checkbox متنی از input جدا شد و switchهای قابل کلیک با حداقل هدف لمسی، توضیح و `aria-checked` ساخته شدند.

## 17. Modal redesign

مودال native dialog با header فشرده، دکمه بستن، جزئیات شخص/عملیات/مبلغ/اثر و علت اجباری برای برگشت ساخته شد.

## 18. Table redesign

header خوانا، row separator نرم، hover، vertical alignment و actionهای فشرده در جدول مشترک اعمال شدند.

## 19. Empty states

وضعیت خالی کوچک با آیکن محلی، متن کوتاه و حداکثر یک اقدام برای هر صفحه اضافه شد.

## 20. Loading states

skeleton، کلاس loading دکمه و قفل ارسال تکراری به JavaScript افزونه اضافه شد.

## 21. Error states

خطاهای قابل نمایش از alertهای فارسی هسته استفاده می‌کنند؛ stack trace، SQL و مسیر سرور وارد UI نشده است.

## 22. Mobile redesign

در عرض کمتر از 768px جدول‌ها به کارت تبدیل، فرم‌ها تک‌ستونه و actionها تمام‌عرض می‌شوند؛ در 390px سرریز صفر ثبت شد.

## 23. Dark-mode support

پس‌زمینه، کارت، متن، border، input، جدول، dialog، badge، empty state و نمودار از وضعیت theme هسته پیروی می‌کنند.

## 24. Accessibility

label، focus visible، aria برای icon button، dialog قابل بستن با Escape، reduced motion و معنی متنی کنار رنگ رعایت شدند.

## 25. Reusable components

`views/components/ui.php` شامل PageHeader، UserCell، Status، MoneyEffect، Balance، EmptyState و FinancialDialog است.

## 26. CSS architecture

`accounting.css` و `accounting-responsive.css` کاملاً زیر `.proma-accounting` scope شده‌اند و selector عمومی جدید ندارند.

## 27. JavaScript architecture

فرمت مبلغ، switch، validation، dirty warning، preview، dialog، loading، settings navigation، help search و chart در یک فایل محلی قرار دارند.

## 28. Files modified

مدیر پلاگین، layout اصلی، manifest، controller/repository خواندنی، همه viewهای افزونه، CSS، JavaScript، fixture و اسناد انتشار تغییر کردند.

## 29. Files created

CSS واکنش‌گرا، component مشترک، تست `v120`، Chart.js محلی، ممیزی، گزارش، release note و تصاویر قبل/بعد ایجاد شدند.

## 30. Existing-data verification

هیچ migration جدیدی ساخته نشد و دو migration قبلی بدون تغییر باقی ماندند؛ write path و فرمول‌های مالی تغییر نکردند.

## 31. Tests executed

PHP lint، Node syntax، static، v110، v120، static_v132، fixture rendering، diff check، بررسی ZIP و آزمون مرورگر اجرا شدند.

## 32. Tests passed

۱۰ صفحه در 1440 و 390 پیکسل، dashboard در dark mode، نمودار، آیکن‌ها، کارت موبایل و dialog مالی با کنسول خالی عبور کردند.

## 33. Failed tests

دو ایراد QA پیدا و رفع شد: تنظیم قدیمی `xAxes/yAxes` و دو نام آیکن ناسازگار Feather. پس از اصلاح هیچ تست ناموفق باقی نماند.

## 34. Screenshots before and after

- قبل: `docs/screenshots/PROMA_ACCOUNTING_V1_1_0_BEFORE.png`
- بعد: `docs/screenshots/PROMA_ACCOUNTING_V1_2_0_AFTER.png`

## 35. Plugin version

نسخه افزونه `1.2.0` و حداقل هسته `1.3.2` است.

## 36. Plugin ZIP output

خروجی مستقل: `dist/plugins/PromaAccounting-1.2.0.zip`؛ پوشه `plugins/` در ZIP هسته وجود ندارد.

## 37. Known limitations

fixture مرورگر بدون دیتابیس واقعی است؛ workflowهای مالی دیتابیس در این انتشار تغییر نکرده‌اند و آزمون نصب کامل همچنان به MySQL/MariaDB موقت سالم نیاز دارد.
