# Architecture Audit

## PASSED in source scope

- مسیر مالی اصلی از float به integer Toman / fixed-rate منتقل شده است.
- runtime DDL از Contract، Installment، Payment، PaymentReceipt و ContractDocument حذف شده است.
- side effectهای قرارداد، قسط و رسید از commit مالی جدا شده‌اند.
- مسیر route افزونه isolate و پاسخ خطا مرکزی شده است.

## Residual risk

چند ماژول غیرمالی قدیمی هنوز `ensureSchema()` با DDL دارند (از جمله Chat، Ecommerce، Event، User و Settings). این مورد باید در انتشار بعدی به migration-only منتقل شود؛ تا زمان تست کامل، ریسک معماری شناخته‌شده است و در `BUG_REGISTER.md` ثبت شده است.
