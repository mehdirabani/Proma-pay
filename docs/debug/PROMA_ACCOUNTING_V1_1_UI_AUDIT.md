# ممیزی رابط Proma Accounting V1.1.0

## وضعیت نسخه 1.0.0

| موضوع | وضعیت قبلی |
|---|---|
| padding صفحه | منبع مستقل نداشت و محتوا به لبه نزدیک بود |
| padding کارت | از قالب عمومی و نامنظم ارث می‌برد |
| grid فرم | `form-grid three/four` بدون کنترل scoped |
| ارتفاع فیلد | بین input، select و checkbox متفاوت |
| label | خود label ظرف input بود و توضیح ساختاری نداشت |
| checkbox | کلاس عمومی `check`، بدون ساختار multiline و touch target |
| switch | وجود نداشت |
| موبایل | فقط KPI breakpoint داشت |
| dark mode | قانون اختصاصی وجود نداشت |
| راهنما | یک جمله عمومی و بدون مثال |
| گردکردن | رشته مبهم `rounding_rule` و بدون اجرای واحد |
| validation | حداقل/حداکثر و درصد کنترل کامل نداشت |

## علت‌ها

CSS پلاگین تنها چند selector فشرده داشت، فرم‌ها markup یکسان نداشتند، checkbox از کنترل متنی تفکیک نشده بود و محاسبه کمیسیون در `CommissionService` با preview مشترک نبود.

## اصلاحات

- متغیرهای scoped برای padding صفحه 24px، کارت 22px، gap بخش 22px و کنترل 44px
- فرم 12 ستونه با breakpoint تبلت و موبایل و `min-width: 0`
- input group یکپارچه برای تومان و درصد
- switch دسترس‌پذیر با keyboard، focus، touch و dark mode
- help مبتنی بر `details/summary` برای کلیک، tap و keyboard
- wrapper مشترک روی هر 10 view پلاگین
- سرویس محاسبه مشترک، validation سرور و snapshot محاسبات

فایل‌های اصلی تغییرکرده: `AccountingController.php`، `CommissionService.php`، همه viewها، `accounting.css` و `accounting.js`. فایل‌های جدید شامل `CommissionCalculationService.php`، migration، wizard، help، تست و مستندات هستند.
