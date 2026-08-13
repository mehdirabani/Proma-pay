# ثبت باگ UI و فیلتر — نسخه 1.5.3

| شناسه | شدت | مسیر | علت ریشه‌ای | اصلاح/فایل‌ها | آزمون/بازآزمایی | وضعیت |
|---|---|---|---|---|---|---|
| UI-CUSTOMER-HISTORY-001 | بالا | `portal/history` | خرید، قرارداد و پرداخت در کارت‌های جدا و با ارتفاع بی‌دلیل بودند | `PortalController`، `views/portal/history.php`، `panels.css` | مرورگر desktop/mobile | رفع‌شده |
| UI-INSTALLMENT-EMPTY-001 | بالا | `installments` | جدول خالی همیشه رندر می‌شد | `views/installments/index.php` | تب فعال بدون داده | رفع‌شده |
| UI-INSTALLMENT-FILTER-001 | بالا | `installments` | فیلتر بلند و فاقد تب منسجم | Controller/Model/View/CSS اقساط | تب‌ها و URL | رفع‌شده |
| UI-PAGINATION-001 | بالا | `installments` | حلقهٔ مستقیم همهٔ صفحات | `views/installments/index.php` | بیش از ۵ صفحه | رفع‌شده |
| FILTER-PAGE-RESET-001 | بحرانی | `installments`،`overdue` | شمارهٔ صفحه بعد از تغییر فیلتر ثابت می‌ماند | `assets/js/app.js` + نرمال‌سازی server | AJAX و URL | رفع‌شده |
| FILTER-OVERDUE-SORT-001 | بحرانی | `overdue` | مرتب‌سازی ردیف قسط به جای قرارداد | `OverdueAggregationService.php` | دادهٔ یک قرارداد/سه قسط | رفع‌شده |
| FILTER-OVERDUE-COUNT-001 | بحرانی | `overdue` | count و pagination با aggregate همسو نبودند | سرویس aggregation | count/page مشترک | رفع‌شده |
| QUERY-OVERDUE-AGGREGATE-001 | بحرانی | `overdue` | قرارداد تکراری، فیلتر پراکنده | `OverdueFilterCriteria.php` و سرویس | database integration | رفع‌شده |
| UI-OVERDUE-ACTIONS-001 | متوسط | `overdue` | تراکم دکمه‌ها در هر ردیف | view/CSS صف سررسید | desktop/mobile | رفع‌شده |
| UI-RESPONSIVE-001 | بالا | صفحه‌های اصلاح‌شده | table-first در موبایل و کنترل‌های خارج از viewport | CSS مشترک و card mobile | 360،390،768،1366 | در بازآزمایی انتشار |
