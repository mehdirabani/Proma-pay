# Bug Register

| شناسه | شدت | وضعیت | شرح |
| --- | --- | --- | --- |
| QA-136-001 | Medium | Open | مدل‌های غیرمالی قدیمی هنوز در request عادی DDL اجرا می‌کنند. دامنه مالی هسته در V1.3.6 اصلاح شده است. |
| QA-136-002 | Blocked | Open | تست install/update واقعی تا زمان تعریف `PROMA_TEST_DB_DSN` اجرا نمی‌شود. |
| QA-136-003 | Blocked | Open | تست visual routeهای نیازمند داده نمونه و session تا زمان تأمین fixture ایزوله اجرا نمی‌شود. |
| PAY-140-001 | Critical | Fixed | امضای `PaymentRequest::begin` با `Model::begin` ناسازگار بود و در PHP 8 هنگام پرداخت جزئیات قرارداد fatal ایجاد می‌کرد؛ API به `beginRequest` تغییر کرد. |
| CONTRACT-140-002 | High | Fixed | ارسال مجدد فرم می‌توانست دو قرارداد بسازد؛ کلید idempotency سمت سرور و قفل ارسال مرورگر اضافه شد. |
| PROFILE-140-003 | Medium | Fixed | آواتار همراه درخواست تأیید مشخصات صف می‌شد؛ مسیر مستقل و فوری آواتار اضافه شد. |
| SECURITY-140-004 | High | Fixed | رمز می‌توانست داخل payload درخواست اصلاح مشخصات بماند؛ allowlist اعمال و داده‌های قدیمی پاک‌سازی شد. |
| AUTH-140-005 | High | Fixed | ورود مشتری محدودسازی تلاش نداشت؛ throttle چنددامنه‌ای با پیام عمومی اضافه شد. |
| DB-140-006 | High | Fixed | ترتیب اجرای migration چاپ می‌توانست پیش از ایجاد جدول پایه شکست بخورد؛ migration به پیش‌نیاز idempotent مجهز شد. |
| UI-140-007 | Low | Fixed | انتخاب‌گر قراردادها با کارت تداخل بصری داشت؛ رابط و handlerهای وابسته حذف شدند. |
| INSTALL-140-008 | Critical | Fixed | فایل نصب تازه ستون `contract_document_versions.template_version_id` را نداشت و اولین تولید قرارداد شکست می‌خورد؛ schema نصب و migration ترمیم شد. |
