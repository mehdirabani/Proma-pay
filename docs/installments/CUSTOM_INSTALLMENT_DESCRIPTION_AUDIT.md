# ممیزی توضیح قسط دلخواه V1.3.9

## مسیر داده

1. فرم قسط دلخواه `customer_description`، `internal_note` و visibility را ارسال می‌کند.
2. `InstallmentsController::store()` توضیح مشتری را اجباری و تاریخ/قرارداد را معتبر می‌کند.
3. `Installment::createCustom()` توضیح مشتری و یادداشت داخلی را در ستون‌های مستقل ذخیره می‌کند.
4. صفحه قرارداد و فهرست اقساط، توضیح را برای مدیریت نشان می‌دهند.
5. پنل مشتری فقط توضیح customer-visible را نشان می‌دهد.
6. booklet تنها توضیح customer-visible را به‌صورت فشرده چاپ می‌کند.

## حریم خصوصی

- `internal_note` فقط در جزئیات قرارداد و فقط در اختیار مدیریت مجاز نمایش داده می‌شود.
- مقدار متن با `e()` escape می‌شود.
- customer A باید فقط اقساط قراردادهای خودش را از query سروری دریافت کند؛ UI به‌تنهایی مبنای authorization نیست.

## وضعیت آزمون

- ذخیره و ویرایش مدل: NOT EXECUTED
- عدم نمایش internal note در customer portal: NOT EXECUTED
- خروجی booklet: NOT EXECUTED
