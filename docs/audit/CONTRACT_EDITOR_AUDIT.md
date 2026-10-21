# ممیزی ویرایشگر قرارداد

## اجزای فعلی

- ایجاد و ویرایش: `views/contracts/index.php`
- اعتبارسنجی و ذخیره: `ContractsController`, `Contract::createWithInstallments`, `Contract::updateContract`
- پیش‌نمایش مالی سبک: `contracts/preview`
- سند نهایی: `ContractDocument`
- چاپ: `views/contracts/print.php`

## ریسک‌های شناسایی‌شده

1. فرم ایجاد/ویرایش بزرگ است و چند حوزهٔ مستقل (مشتری، ضامن، کالا، ضمانت و اقساط) را هم‌زمان نمایش می‌دهد.
2. سند قرارداد و form editor دو flow مستقل‌اند؛ ناوبری بخش‌ها و حفظ تغییرات ذخیره‌نشده باید صریح شود.
3. ضامن دستی و ضامن موجود دو persistence path داشتند؛ read-model سند اکنون آن‌ها را یکی می‌کند.

## معیار بازطراحی

- ساختار مرحله‌ای یا navigation section، بدون تغییر APIهای ذخیره‌سازی موجود.
- preview مالی فقط با درخواست debounce‌شده و بدون بارگذاری pluginها.
- نمایش ضامن‌ها به صورت chip/card با نام، شناسهٔ masked و عملیات حذف از قرارداد.
- هشدار خروج در صورت تغییر ذخیره‌نشده.
- preview چاپ از همان `ContractDocument` و print profile استفاده کند.
