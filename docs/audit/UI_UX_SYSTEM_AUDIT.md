# ممیزی UI/UX سامانه Proma Pay

تاریخ: ۱۴۰۵/۰۶/۱۵  
نسخهٔ مبنا: 1.5.8

## معماری مشاهده‌شده

| لایه | وضعیت فعلی | تصمیم مهاجرت |
| --- | --- | --- |
| پوسته | `views/layouts/app.php` با topbar، sidebar و breadcrumb | نگه‌داری یک shell؛ کاهش آیتم‌های پرتکرار و استفاده از الگوی مشترک page header |
| توکن‌ها | `assets/css/design-system/tokens.css` | توسعهٔ توکن‌های semantic و spacing؛ بدون CSS بزرگ override |
| فرم‌ها | `assets/css/components/forms.css` و قواعد پراکنده | استانداردسازی control height، label، خطا و stateها در همان لایهٔ component |
| responsive | `assets/css/components/responsive.css` و CSS قالب | آزمون breakpoints مشخص؛ برطرف‌سازی overflow در familyهای صفحه |
| قرارداد | `views/contracts/index.php`, `show.php`, `print.php` | اولویت نخست: رابطه ضامن، workspace قرارداد و چاپ |
| مالی / حقوقی | `views/contracts/show.php`, `views/legal/*` | کارت‌های مالی از سرویس canonical؛ عدم محاسبه در JS |
| پنل مشتری | `views/dashboard/customer.php`, `views/portal/*` | چگالی بهتر و مسیرهای قرارداد/قسط به‌عنوان عملیات اصلی |

## یافته‌های اولویت‌دار

1. بخش‌های قرارداد، ویرایشگر تنظیمات قرارداد و چاپ سه surface جدا هستند؛ باید داده و pattern مشترک داشته باشند.
2. ضامن موجود از `contract_guarantors` در document renderer حذف شده بود؛ اصلاح آن در commit `bcf7c1e` انجام و migration آن افزوده شد.
3. CSS از پیش به لایه‌های `tokens`, `components`, `app` و CSS قالب تقسیم شده است؛ راه درست تکمیل همین ساختار است، نه ایجاد فایل override یکپارچه.
4. کارت‌ها و فرم‌ها در بعضی viewها مستقیم از کلاس‌های قالب استفاده می‌کنند؛ مهاجرت باید family-by-family انجام شود تا منطق مالی و نقش‌ها تغییر نکنند.
5. چاپ قرارداد CSS اختصاصی دارد و نباید با CSS پنل مدیریت مخلوط شود.

## ترتیب اجرا

1. داده و چاپ قرارداد/ضامن‌ها؛
2. foundations و componentهای مشترک؛
3. workspace قرارداد و جزئیات/پرداخت؛
4. مشتری، اقساط، پرداخت و وصول؛
5. حقوقی، گزارش، تنظیمات و ابزارهای ثانویه؛
6. آزمون RTL، دسترس‌پذیری و responsive برای هر family پیش از انتشار.
