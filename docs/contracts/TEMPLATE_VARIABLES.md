# راهنمای متغیرهای قالب

فهرست متغیرها از `ContractDocument::variableDescriptions()` و
`ContractTemplateService::variableCatalog()` می‌آید. همان رجیستری برای ستون
درج متغیر، صفحه راهنما، اعتبارسنجی و پیش‌نمایش استفاده می‌شود.

نمونه‌ها:

- نام کامل مشتری: `{{customer_full_name}}`
- شماره قرارداد: `{{contract_number}}`
- بخش ضامن‌ها: `{{guarantors_section}}`
- جدول اقساط و تضمین‌ها: `{{installments_guarantees_table}}`
- بخش امضاها: `{{signature_section}}`

متغیر ناشناخته هنگام ذخیره/انتشار در نتیجه اعتبارسنجی گزارش می‌شود و باید پیش
از انتشار توسط مدیر بررسی شود.
