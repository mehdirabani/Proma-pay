# گزارش آزمون V1.5.0

## دامنه

- رفع پرداخت ماندهٔ قسطی که پس از پرداخت جزئی به‌علت وضعیت قدیمی قرارداد/قسط مسدود می‌شد.
- quote و تخصیص قطعی تسویه.
- سیاست حقوقی نسخه‌دار، ایجاد داخلی پرونده و اسناد داخلی با تفکیک اصطلاحات رسمی.

## اجراهای واقعی

- `tests/installment_settlement_engine_v150.php`: موفق. سناریوی ۵٬۰۰۰٬۰۰۰ و پرداخت ۴٬۰۰۰٬۰۰۰ با تخصیص ۱٬۵۰۰٬۰۰۰ + ۱٬۵۰۰٬۰۰۰ + ۱٬۰۰۰٬۰۰۰ و سناریوی وضعیت نمایشی stale پاس شد.
- `tests/integration_installment_settlement_v150.php`: موفق روی MariaDB واقعی؛ quote، تخصیص، پرداخت تکراری، reversal و callback درگاه بررسی شد.
- `tests/integration_installment_settlement_concurrency_v150.php`: موفق؛ transaction دوم قفل ردیف قسط را دریافت نکرد.
- `tests/integration_legal_workflow_v150.php`: موفق روی MariaDB واقعی؛ سیاست ۲۰ روزه، snapshot تاریخی پس از تغییر به ۹۰ روز، جلوگیری از پرونده تکراری، سند داخلی و تایید ثبت بیرونی با شماره پیگیری آزمون شدند.
- نصب تمیز با `database/proma-pay-install.sql`: موفق؛ جدول‌های `legal_policy_versions`، `contract_legal_policy_snapshots`، `legal_case_requests` و `legal_documents` و ستون‌های جدید پرونده ایجاد شدند.

## محدودیت آگاهانه

وضعیت «ثبت بیرونی تاییدشده» صرفاً تایید انسانی و evidence واردشده در سامانه را ثبت می‌کند و اتصال خودکار به مراجع قضایی یا ادعای ارسال رسمی انجام نمی‌دهد.
