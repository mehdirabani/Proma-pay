# ممیزی پیش از اصلاح موتور تسویه اقساط

## نتیجهٔ ممیزی

موتور ایجاد قرارداد و مدل‌های فعلی حفظ می‌شوند، اما مسیرهای نمایش، پرداخت
تکی، گروهی، درگاه و reconciliation از یک state مرکزی استفاده نمی‌کنند. بنابراین
راه‌حل، یک موتور موازی نیست: state، quote و allocation مرکزی به همین مدل‌های
`installments`، `payments`، `payment_groups` و `payment_allocations` متصل می‌شوند.

## مسیرهای محاسبهٔ موجود

| مسیر | وضعیت پیش از اصلاح | ریسک |
| --- | --- | --- |
| `FinanceHelper::preview/paymentPreview` | مبنای پرداخت تکی است، اما ماندهٔ پرداخت جزئی شامل جریمه را نادرست محاسبه می‌کند. | Critical |
| `Installment::withPreview` | برای هر قسط پرداخت‌ها را جدا می‌خواند و هنگام نمایش ممکن است در DB بنویسد. | High |
| `Payment::record/applyToInstallment` | تراکنش تکی دارد ولی به `remaining_after_payment` قدیمی تکیه می‌کند. | Critical |
| `PaymentGroupService` | parent و allocation اولیه دارد ولی به اقساط انتخاب‌نشده سرریز می‌کند. | Critical |
| `ContractFinancialSummaryService` | نمایش summary را با `float` محاسبه می‌کند. | High |
| `InstallmentReconciliationService` | فقط اصل و جمع پرداخت را مقایسه می‌کند. | High |

## خط مبنای سازگاری

- سابقهٔ قدیمی پرداخت حفظ می‌شود. اگر breakdown جدید نداشته باشد، state با
  `remaining_after_payment` ثبت‌شده و سپس مبلغ مؤثر بازسازی می‌شود.
- پرداخت‌های جدید breakdown صریح اصل، جریمهٔ عادی، جریمهٔ حقوقی و پاداش را
  ذخیره می‌کنند تا بازسازی آینده بدون حدس انجام شود.
- quoteها پنج دقیقه (با setting مرکزی قابل تغییر) اعتبار دارند و در commit
  دوباره زیر قفل اعتبارسنجی می‌شوند.
