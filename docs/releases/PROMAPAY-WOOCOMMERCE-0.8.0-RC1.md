# Proma Pay for WooCommerce 0.8.0-rc.1

این یک prerelease برای آزمایش staging است، نه انتشار عملیاتی.
بسته: `dist/plugins/pre-release/proma-pay-woocommerce/PromaPay-WooCommerce-v0.8.0-rc.1.zip`

## موارد افزوده

- اتصال چندگانهٔ WooCommerce به سرویس‌های test سامانه مرکزی.
- quote سمت سرور و snapshot یک‌بارمصرف policy برای checkout.
- gateway پرداخت اقساطی در Checkout کلاسیک و Blocks، نمایش برآورد محصول و child order مستقل برای بیعانه.
- پشتیبانی CRUD با HPOS و legacy storage.
- پنل گزارش tenant-scoped، event outbox برای افزونه‌ها و گزارش وضعیت API/ضمانت/تسویه.

## بررسی‌شده

- تست‌های static/unit هسته و افزونه.
- integration API روی MySQL disposable، شامل رقابت دو درخواست هم‌زمان و idempotency.
- WordPress 7.1.3 / WooCommerce 11.2.1 / PHP 8.3.33 با درگاه ساختگی و دو حالت order storage.
- release gate محلی static/unit روی پوشهٔ واقعی پروژه.

## محدودیت‌های انتشار

- فقط `pmp_test_` پذیرفته می‌شود.
- مرورگر/Store API کامل، درگاه بانکی زنده، refund واقعی و callback تولیدی اجرا نشده‌اند.
- صیاد واقعی، قراردادهای حقوقی، تأمین واقعی منابع، مالکیت مطالبات، مسئولیت نکول و مجوز پرداخت نهایی بررسی/تصویب نشده‌اند.
- مهاجرت و اتصال downstream شش افزونه رویداد امن دارند؛ ثبت مالی، جایزه، انبار یا امضای خودکار فعال نیست.
- این بسته Production Ready نیست و نباید روی فروشگاه live نصب شود.
