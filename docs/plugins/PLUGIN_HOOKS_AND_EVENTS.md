# Hook و Event افزونه

ثبت listener با `PluginHooks::listen($event, $callable, $priority)` انجام می‌شود. انتشار event از `PluginManager::fire()` عبور می‌کند و payload قبل از ارسال کلیدهای حساس را redaction می‌کند.

نام‌های رزروشده‌ی دامنه:

`user.created`, `user.updated`, `contract.created`, `contract.updated`, `contract.cancelled`, `contract.completed`, `contract.deleted`, `contract.seller_changed`, `installment.created`, `installment.updated`, `installment.cancelled`, `installment.bulk_updated`, `payment.created`, `payment.completed`, `payment.corrected`, `payment.group.completed`, `payment.group.corrected`.

برای تغییرات مالی، listener بحرانی باید داخل تراکنش والد اجرا شود. اعلان‌ها و کارهای غیرحساس باید پس از commit اجرا شوند. payload نباید password، session، secret یا API key داشته باشد.
