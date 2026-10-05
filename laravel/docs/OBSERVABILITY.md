# مشاهده‌پذیری و عملیات

## حاضر

middleware `AssignRequestId` یک شناسه برای ردیابی درخواست فراهم می‌کند و audit event عملیات حساس پایه را نگه می‌دارد. Laravel logging config و database queue/cache در محیط نمونه موجود است؛ backend production انتخاب نشده است.

## تکمیل لازم

- structured logs با `request_id`, route, status, latency و شناسهٔ داخلی؛ بدون Authorization, password, PII, request body مالی کامل یا secret.
- metrics: نرخ/تاخیر پاسخ، 4xx/5xx، lock wait/deadlock، queue depth/age/retry، DB pool، login failure، KYC backlog و mismatch reconciliation.
- trace برای مرز API/DB/queue/provider پس از انتخاب exporter و سیاست sampling.
- هشدار روی خطاهای پرداخت و اختلاف ledger، نه فقط availability؛ آستانه و on-call owner باید تعیین شود.
- health check liveness/readiness جدا؛ health عمومی نباید جزئیات version/secret/DB را افشا کند.
- retention/دسترسی log و audit، backup metrics و دورهٔ مانور incident/restore.

هیچ dashboard یا alert production در این repository پیکربندی نشده است. `audit_events` جایگزین log امنیتی مرکزی یا دفترکل حسابداری immutable نیست.
