# طرح پایگاه داده

## قراردادها

تمام جداول افزونه با `plugin_inventory_` آغاز می‌شوند. پول با `DECIMAL(20,0)` و ریال صحیح نگهداری می‌شود؛ هیچ ستون پولی از نوع FLOAT/DOUBLE تعریف نشده است. شناسه‌های کاربر به `users.id` نصب فعلی اشاره دارند. اسناد موجودی و مالی از `ON DELETE RESTRICT` استفاده می‌کنند؛ حذف کاربر در صورت نیاز فقط شناسهٔ سازنده را NULL می‌کند.

Migration پایه `migrations/2026_10_13_inventory_partners_core.sql` است. نسخهٔ ۱.۱.۰ migration افزایشی `migrations/2026_10_13_inventory_partners_v110.sql` دارد؛ ستون‌ها، کلیدها و constraintهای تازه پیش از افزودن در `information_schema` بررسی می‌شوند تا اجرای نیمه‌تمام قابل ادامه باشد. migration جدید باید بعد از schema پایه اجرا شود. پشتیبانی migration ارتقایی روی دیتابیس نصب و کامل‌بودن fixture آن باید در تست انتشار بررسی شود.

## جداول و نقش‌ها

| جدول | نقش |
|---|---|
| `plugin_inventory_settings` | تنظیمات افزونه و انبار پیش‌فرض |
| `plugin_inventory_warehouses` | انبارها و مدیر هر انبار |
| `plugin_inventory_suppliers` | تأمین‌کنندگان |
| `plugin_inventory_partners` | پروفایل شرکا و سهم پیش‌فرض برای عملیات آینده |
| `plugin_inventory_products` | مشخصات کالا و قیمت‌های مرجع جاری |
| `plugin_inventory_brands`, `plugin_inventory_categories` | فهرست مرکزی نام یکتا برای جست‌وجو و ارجاع کالا |
| `plugin_inventory_purchase_headers`, `plugin_inventory_purchase_lines` | سربرگ خرید و ردیف تک‌کالایی آن |
| `plugin_inventory_stock_lots` | محموله، موجودی، بهای واحد و باقیماندهٔ بهای تمام‌شده |
| `plugin_inventory_lot_partner_shares` | snapshot سهم سرمایه و سود در محموله |
| `plugin_inventory_serial_items` | شناسهٔ سریالی یکتای هر واحد |
| `plugin_inventory_stock_movements` | گردش‌های ورود، خروج، انتقال، تعدیل و برگشت |
| `plugin_inventory_stock_adjustments` | سند تعدیل با علت و کلید تکرارناپذیری |
| `plugin_inventory_sales`, `plugin_inventory_sale_lines` | سربرگ فروش و ردیف کالا |
| `plugin_inventory_sale_partner_shares` | snapshot سهم و ماندهٔ سرمایه/سود هر شریک در فروش |
| `plugin_inventory_sale_serial_items` | اتصال سریال فروخته‌شده به ردیف فروش |
| `plugin_inventory_payment_allocations` | اتصال پرداخت Proma Pay و سند برگشت تخصیص |
| `plugin_inventory_returns`, `plugin_inventory_return_lines` | مرجوعی کامل و جزئیات بازگردانی کالا |
| `plugin_inventory_journals`, `plugin_inventory_journal_lines` | اسناد و ردیف‌های دفترکل دوطرفه |
| `plugin_inventory_capital_contributions` | آوردهٔ مستقل شرکا |
| `plugin_inventory_settlement_requests` | درخواست، تأیید، پرداخت و برگشت تسویه |
| `plugin_inventory_transfers`, `plugin_inventory_transfer_lines`, `plugin_inventory_transfer_serial_items` | انتقال رزروشده بین انبارها |
| `plugin_inventory_price_history` | تاریخچهٔ نرخ قبل/بعد و علت تغییر |
| `plugin_inventory_expenses` | اسناد هزینهٔ عمومی با نوع، تاریخ، مبلغ و مرجع پرداخت |
| `plugin_inventory_expense_partner_allocations` | snapshot سهم و مبلغ صحیح هر شریک در هر هزینه |
| `plugin_inventory_audit_logs` | ثبت عملیات حساس و snapshot تغییر |

## محدودیت‌ها و شاخص‌ها

- یکتایی SKU، بارکد، کد شریک، شمارهٔ محموله، شمارهٔ سند و کلیدهای idempotency با unique index اعمال می‌شود.
- هر قرارداد حداکثر یک فروش افزونه دارد؛ پرداخت Proma Pay حداکثر یک تخصیص فعال دارد.
- هر شریک در هر محموله و هر ردیف فروش یک snapshot یکتا دارد.
- SKU محصول براساس الگوی ذخیره‌شده ساخته می‌شود؛ `idempotency_key` یکتای درج محصول از ساخت تکراری در retry جلوگیری می‌کند.
- برند و دسته با کلید خارجی نگهداری می‌شوند و نام‌های قبلی در migration به فهرست مرکزی متصل می‌شوند.
- هر هزینهٔ ثبت‌شده در همان تراکنش به سهم‌های ۱۰۰٪ شرکا تخصیص می‌یابد؛ `DECIMAL(20,0)` ریال و کلید یکتای شریک/هزینه از تکرار جلوگیری می‌کند.
- بازهٔ گزارش روی `sales(status, sold_at, id)` و `expenses(status, expense_date, id)` فیلتر می‌شود؛ مبالغ ماهانه با توابع تاریخ روی ستون فیلترشده wrap نمی‌شوند.
- سریال در کل افزونه یکتا است و در فروش هم‌زمان با قفل ردیف و وضعیت بررسی می‌شود.
- جدول‌های سندی به منبع با foreign key محدود متصل‌اند و پاک‌سازی آبشاری برای ثبت مالی استفاده نشده است.
- مقدار موجودی جاری در lot ذخیره می‌شود و باید با جمع گردش‌ها قابل تطبیق باشد؛ در این نسخه گزارش مغایرت مستقل و job بازسازی خودکار ارائه نشده است.

## وابستگی به core

جداول افزونه به `users(id)` و در منطق فروش قراردادی به `contracts`, `payments`, `installments` و `users` سامانه متصل‌اند. افزونه جدول core را تغییر نمی‌دهد. نوع دقیق ستون‌های پولی و قرارداد hookها با source فعلی بررسی شده‌اند، اما migration در یک پایگاه دادهٔ جداگانه نصب نشده است.



