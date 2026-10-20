# گزارش سازگاری

تاریخ بررسی: ۲۰۲۶-۱۰-۱۳

## محیط و یافته‌ها

- این نصب Proma Pay از MVC و Plugin Manager اختصاصی استفاده می‌کند، نه WordPress.
- manifest افزونه حداقل core را `6.0.2` و حداقل PHP را `8.1` اعلام می‌کند.
- lint و آزمون‌های مستقل با PHP `8.3.33` موفق بودند.
- migration و serviceهای منتخب با MySQL Community `8.4.3` روی datadir ایزوله و schema fixture موفق اجرا شدند.
- ساختار `users.id` در schema نصب `BIGINT UNSIGNED` است؛ شمارهٔ تماس مشتری از ستون `users.mobile` خوانده می‌شود.
- هدف طراحی مستندشده MySQL 5.7 یا MariaDB 10.3 با InnoDB و utf8mb4 است؛ این دو نسخه در این اجرا آزمایش نشدند.

## اتصال‌های Proma Pay

هسته در زمان بررسی route قراردادها و جدول‌های `contracts`, `payments`, `installments` را داشت. مبلغ اصل قرارداد در تومان است و وضعیت پرداخت، اصلاح و تخصیص اصل در جدول پرداخت قرار دارد. افزونه جدول‌های core را تغییر نمی‌دهد. hookهای موجود و مورد استفاده `payment.completed` و `contract.cancelled` هستند.

در core بررسی‌شده hook عمومی اصلاح/ابطال پرداخت پیدا نشد. بعد از چنین اصلاحی، اپراتور مجاز باید فروش را دستی تطبیق کند.

## نتیجه و دامنهٔ اطمینان

قرارداد manifest، PHP syntax و تعدادی مسیر service/schema روی MySQL 8.4 fixture شواهد دارند. Fixture هسته حداقلی است و جایگزین dump کامل Proma Pay نیست. سازگاری نصب/اجرا با نسخهٔ میزبان، MySQL 5.7/MariaDB، Plugin Manager واقعی، health check پس از نصب، hook delivery و رفتار صفحات در مرورگر end-to-end اثبات نشده است. وضعیت عرضه Production تأییدنشده است.

## گام‌های تأیید staging

1. نسخهٔ دقیق core و نسخه‌های PHP/MySQL میزبان را ثبت کنید.
2. backup بگیرید و بسته را روی staging نصب کنید.
3. health check، انبار MAIN و FK/indexها را کنترل کنید.
4. routeها و API را با نقش‌های مدیر و محدود آزمایش کنید.
5. eventهای پرداخت و لغو را از هسته اجرا و اصلاح پرداخت را دستی reconcile کنید.
6. مسیرهای مالی را اجرا و ledger/stock را با پرس‌وجوی مستقل تطبیق دهید.
7. UI را در عرض‌های 1440، 1280، 768 و 390 پیکسل ثبت تصویر و بررسی کنید.
