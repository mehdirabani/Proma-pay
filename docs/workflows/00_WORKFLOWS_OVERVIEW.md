# 00 — Workflows Overview

مستند نمای کلی Workflowهای اصلی پروژه **Proma Pay**

---

## فهرست مطالب

- [00 — Workflows Overview](#00--workflows-overview)
  - [فهرست مطالب](#فهرست-مطالب)
  - [هدف این پوشه](#هدف-این-پوشه)
  - [تعریف Workflow](#تعریف-workflow)
  - [اصل مهم Workflowها](#اصل-مهم-workflowها)
  - [لیست Workflowهای اصلی](#لیست-workflowهای-اصلی)
  - [قوانین عمومی Workflow](#قوانین-عمومی-workflow)
  - [ساختار استاندارد هر Workflow](#ساختار-استاندارد-هر-workflow)
  - [وضعیت‌های Workflow](#وضعیتهای-workflow)
  - [ارتباط Workflow با Domainها](#ارتباط-workflow-با-domainها)
  - [Eventها در Workflow](#eventها-در-workflow)
  - [Notificationها در Workflow](#notificationها-در-workflow)
  - [Timeline در Workflow](#timeline-در-workflow)
  - [Logging و Audit در Workflow](#logging-و-audit-در-workflow)
    - [Log عادی](#log-عادی)
    - [Audit Log](#audit-log)
    - [Security Log](#security-log)
    - [Financial Log](#financial-log)
  - [قوانین UI در Workflow](#قوانین-ui-در-workflow)
  - [قوانین امنیتی Workflow](#قوانین-امنیتی-workflow)
  - [Transaction در Workflowها](#transaction-در-workflowها)
  - [Idempotency](#idempotency)
  - [خطاهای عمومی Workflow](#خطاهای-عمومی-workflow)
  - [تست‌های عمومی Workflow](#تستهای-عمومی-workflow)
  - [چک‌لیست طراحی Workflow](#چکلیست-طراحی-workflow)
  - [Definition of Done](#definition-of-done)
  - [پایان فایل](#پایان-فایل)

---

## هدف این پوشه

پوشه `docs/workflows` برای مستندسازی جریان‌های عملیاتی اصلی سیستم Proma Pay استفاده می‌شود.

Domainها مشخص می‌کنند هر بخش سیستم چه مسئولیتی دارد.  
اما Workflowها مشخص می‌کنند چند Domain چگونه با هم کار می‌کنند تا یک فرآیند واقعی انجام شود.

مثال:

پرداخت قسط فقط مربوط به Payments نیست.  
در این فرآیند این دامنه‌ها درگیر هستند:

- Customers
- Contracts
- Installments
- Payments
- Financial Calculations
- Notifications
- Chat
- Calendar
- Reports
- Files
- Logs

بنابراین Workflowها برای فهم رفتار واقعی سیستم ضروری هستند.

---

## تعریف Workflow

Workflow یعنی مسیر مرحله‌به‌مرحله انجام یک عملیات واقعی در سیستم.

هر Workflow باید مشخص کند:

- شروع فرآیند از کجاست.
- چه کسی عملیات را انجام می‌دهد.
- چه Domainهایی درگیر هستند.
- چه داده‌هایی خوانده یا نوشته می‌شوند.
- چه Validationهایی لازم است.
- چه Eventهایی Dispatch می‌شوند.
- چه Notificationهایی ارسال می‌شوند.
- چه Logهایی ثبت می‌شوند.
- چه خطاهایی ممکن است رخ دهد.
- نتیجه نهایی چیست.

---

## اصل مهم Workflowها

هر Workflow باید قابل پیاده‌سازی، قابل تست و قابل Trace باشد.

یعنی وقتی یک عملیات انجام شد، سیستم باید بتواند نشان دهد:

- چه کسی عملیات را انجام داده است.
- چه زمانی انجام شده است.
- چه داده‌هایی تغییر کرده‌اند.
- چه Eventهایی ایجاد شده‌اند.
- چه Notificationهایی ارسال شده‌اند.
- چه خطاهایی رخ داده‌اند.
- وضعیت نهایی چیست.

---

## لیست Workflowهای اصلی

فایل‌های پیشنهادی این پوشه:

| فایل | موضوع |
|---|---|
| `00_WORKFLOWS_OVERVIEW.md` | نمای کلی Workflowها |
| `01_CUSTOMER_ONBOARDING.md` | ثبت‌نام، تکمیل پروفایل و احراز هویت مشتری |
| `02_CONTRACT_CREATION.md` | ایجاد قرارداد، کالا، ضمانت و تولید اقساط |
| `03_INSTALLMENT_PAYMENT.md` | پرداخت قسط آنلاین یا دستی |
| `04_CARD_TO_CARD_REVIEW.md` | ارسال، بررسی، تأیید یا رد رسید کارت‌به‌کارت |
| `05_OVERDUE_FOLLOWUP.md` | پیگیری اقساط معوق توسط اپراتور |
| `06_LEGAL_REFERRAL.md` | ارجاع قرارداد یا قسط به واحد حقوقی |
| `07_SETTLEMENT.md` | تسویه کامل قرارداد |
| `08_CALENDAR_AND_REMINDERS.md` | رویدادهای تقویم و یادآوری‌ها |
| `09_CHAT_AND_BOT_MESSAGES.md` | چت مشتری، چت داخلی و پیام‌های ربات |
| `10_BACKUP_AND_UPDATE.md` | بکاپ، Restore و آپدیت سیستم |
| `11_PLUGIN_INSTALLATION.md` | نصب، فعال‌سازی و حذف پلاگین |
| `12_REPORT_EXPORT.md` | مشاهده و خروجی گرفتن از گزارش‌ها |

---

## قوانین عمومی Workflow

همه Workflowها باید قوانین زیر را رعایت کنند:

- هر عملیات حساس باید Permission داشته باشد.
- هر فرم باید CSRF داشته باشد.
- هر تغییر مالی باید Financial Log داشته باشد.
- هر تغییر حساس باید Audit Log داشته باشد.
- هر تلاش غیرمجاز باید Security Log داشته باشد.
- هر Workflow باید وضعیت خطا را مشخص کند.
- هیچ Workflow نباید اطلاعات داخلی حساس را به مشتری نمایش دهد.
- هیچ Workflow نباید مبلغ مالی را از Frontend به عنوان منبع حقیقت قبول کند.
- هر Workflow باید با UI Constitution هماهنگ باشد.
- هر Workflow باید قابل تست باشد.

---

## ساختار استاندارد هر Workflow

هر فایل Workflow باید این ساختار را داشته باشد:

```text
# شماره — نام Workflow

## هدف Workflow

## بازیگران

## Domainهای درگیر

## پیش‌نیازها

## ورودی‌ها

## خروجی‌ها

## مراحل اصلی

## Validationها

## Eventها

## Notificationها

## Timeline

## Logging و Audit

## خطاهای احتمالی

## قوانین UI

## قوانین امنیتی

## تست‌های ضروری

## Definition of Done
```

---

## وضعیت‌های Workflow

Workflowها می‌توانند وضعیت‌های مختلف داشته باشند.

وضعیت‌های عمومی پیشنهادی:

| وضعیت | توضیح |
|---|---|
| `started` | فرآیند شروع شده |
| `in_progress` | در حال انجام |
| `waiting_for_user` | منتظر اقدام کاربر |
| `waiting_for_review` | منتظر بررسی مدیر |
| `completed` | کامل شده |
| `failed` | شکست خورده |
| `cancelled` | لغو شده |
| `expired` | منقضی شده |

---

## ارتباط Workflow با Domainها

Workflow نباید جایگزین Domain شود.

Domainها مسئول منطق اصلی هستند.  
Workflow فقط ترتیب اجرای عملیات بین Domainها را توضیح می‌دهد.

مثال:

در Workflow پرداخت قسط:

- مبلغ نهایی از Financial Calculations گرفته می‌شود.
- پرداخت در Payments ثبت می‌شود.
- وضعیت قسط در Installments تغییر می‌کند.
- پیام در Notifications ارسال می‌شود.
- پیام ربات در Chat ثبت می‌شود.
- گزارش‌ها از داده جدید استفاده می‌کنند.

Workflow نباید خودش محاسبه مالی انجام دهد.

---

## Eventها در Workflow

هر Workflow مهم باید Event داشته باشد.

نمونه:

- `CustomerRegistered`
- `IdentityDocumentSubmitted`
- `ContractCreated`
- `InstallmentsGenerated`
- `PaymentApproved`
- `PaymentReceiptRejected`
- `InstallmentOverdue`
- `LegalCaseCreated`
- `ContractSettled`
- `BackupCompleted`
- `PluginInstalled`

قوانین:

- Event باید بعد از موفقیت عملیات اصلی Dispatch شود.
- Event نباید قبل از Commit شدن Transaction حساس Dispatch شود.
- Listenerها نباید باعث خراب شدن عملیات اصلی شوند.
- خطای Listener باید Log شود.

---

## Notificationها در Workflow

هر Workflow باید مشخص کند چه اعلان‌هایی ارسال می‌شود.

نمونه:

| Workflow | گیرنده | پیام |
|---|---|---|
| ثبت قرارداد | مشتری | قرارداد جدید برای شما ثبت شد |
| سررسید قسط | مشتری | موعد پرداخت قسط شما نزدیک است |
| رسید جدید | مدیر مالی | رسید پرداخت جدید ارسال شد |
| ارجاع حقوقی | وکیل | پرونده جدید به شما ارجاع شد |
| آپدیت ناموفق | مدیر اصلی | بروزرسانی با خطا مواجه شد |

قوانین:

- اعلان مشتری نباید شامل اطلاعات داخلی باشد.
- اعلان حساس فقط برای نقش مجاز ارسال شود.
- اعلان تکراری باید کنترل شود.

---

## Timeline در Workflow

Timeline برای نمایش تاریخچه عملیاتی استفاده می‌شود.

هر Workflow باید مشخص کند آیا Timeline لازم دارد یا نه.

مواردی که Timeline لازم دارند:

- ثبت قرارداد
- تولید اقساط
- پرداخت قسط
- ارسال رسید
- تأیید یا رد رسید
- معوق شدن قسط
- ثبت پیگیری اپراتور
- ارجاع حقوقی
- ثبت اقدام حقوقی
- تسویه قرارداد
- تغییرات مهم فایل‌ها

مواردی که معمولاً Timeline لازم ندارند:

- مشاهده صفحه
- فیلتر گزارش
- خواندن اعلان
- تغییرات UI بدون اثر داده‌ای

---

## Logging و Audit در Workflow

### Log عادی

برای عملیات معمولی استفاده می‌شود.

نمونه:

- شروع پرداخت
- ارسال اعلان
- اجرای Job
- ایجاد خروجی گزارش

### Audit Log

برای تغییرات حساس استفاده می‌شود.

نمونه:

- تغییر مبلغ قسط
- تأیید رسید پرداخت
- تغییر تنظیمات مالی
- حذف فایل حقوقی
- اجرای Restore
- نصب پلاگین

### Security Log

برای موارد امنیتی استفاده می‌شود.

نمونه:

- تلاش دسترسی غیرمجاز
- CSRF نامعتبر
- مشاهده فایل دیگران
- Callback نامعتبر درگاه
- آپلود فایل خطرناک

### Financial Log

برای تغییرات مالی استفاده می‌شود.

نمونه:

- تأیید پرداخت
- پرداخت ناقص
- اعمال جریمه
- اعمال تخفیف
- اصلاح مالی دستی
- تسویه کامل

---

## قوانین UI در Workflow

هر Workflow باید UI قابل فهم داشته باشد.

قوانین:

- کاربر باید بداند در چه مرحله‌ای است.
- عملیات حساس باید Confirmation داشته باشد.
- خطاها باید فارسی، کوتاه و قابل فهم باشند.
- موفقیت عملیات با Toast نمایش داده شود.
- عملیات طولانی باید Progress یا وضعیت داشته باشد.
- فرم‌ها باید خطا را زیر همان فیلد نشان دهند.
- در موبایل، مراحل باید ساده و قابل انجام باشند.
- Date Picker استاندارد استفاده شود.
- دکمه‌های خطرناک باید رنگ و متن هشدار داشته باشند.

---

## قوانین امنیتی Workflow

همه Workflowها باید این موارد را رعایت کنند:

- بررسی Login
- بررسی Permission
- بررسی Scope
- بررسی Ownership
- CSRF برای فرم‌ها
- Validation سمت سرور
- Prepared Statement
- Escape خروجی‌ها
- عدم اعتماد به داده Frontend
- عدم نمایش اطلاعات حساس
- Log تلاش غیرمجاز
- جلوگیری از عملیات تکراری

---

## Transaction در Workflowها

Workflowهای حساس باید Transaction داشته باشند.

نمونه Workflowهای نیازمند Transaction:

- ایجاد قرارداد و تولید اقساط
- تأیید پرداخت و بروزرسانی قسط
- تأیید رسید کارت‌به‌کارت
- تسویه کامل قرارداد
- ارجاع حقوقی
- Restore یا Update بخشی از دیتابیس

قانون:

اگر عملیات اصلی شکست خورد، تغییرات نیمه‌کاره نباید در دیتابیس باقی بماند.

---

## Idempotency

برخی Workflowها باید Idempotent باشند.

یعنی اگر دوبار اجرا شدند، نتیجه خراب یا تکراری ایجاد نکنند.

نمونه:

- Callback درگاه پرداخت
- تولید اقساط قرارداد
- ارسال Reminder روزانه
- اجرای Migration
- اجرای Job معوقات
- نصب Migration پلاگین

قوانین:

- پرداخت تکراری نباید دوبار approved شود.
- اقساط قرارداد نباید دوبار تولید شوند.
- Reminder تکراری نباید اسپم ایجاد کند.
- Migration دوباره نباید جدول خراب کند.

---

## خطاهای عمومی Workflow

خطاهای عمومی که باید مدیریت شوند:

| خطا | رفتار مناسب |
|---|---|
| Permission Denied | نمایش پیام عدم دسترسی و ثبت Security Log |
| Validation Error | نمایش خطا زیر فیلد |
| Payment Failed | نمایش امکان تلاش دوباره |
| File Upload Failed | نمایش علت خطا |
| Duplicate Request | جلوگیری و نمایش پیام مناسب |
| System Error | پیام عمومی + ثبت Error Log |
| External Service Failed | Log + Retry در صورت امکان |
| Transaction Failed | Rollback + پیام خطا |

---

## تست‌های عمومی Workflow

هر Workflow باید تست‌های زیر را داشته باشد:

- مسیر موفق
- مسیر خطا
- دسترسی غیرمجاز
- داده نامعتبر
- عملیات تکراری
- وضعیت نیمه‌کاره
- نقش‌های مختلف
- مشتری دیگر
- موبایل و دسکتاپ
- Log و Event
- Notification
- Rollback در صورت نیاز

---

## چک‌لیست طراحی Workflow

قبل از نهایی کردن هر Workflow بررسی شود:

- [ ] هدف Workflow مشخص است.
- [ ] بازیگران مشخص هستند.
- [ ] Domainهای درگیر مشخص هستند.
- [ ] ورودی‌ها مشخص هستند.
- [ ] خروجی‌ها مشخص هستند.
- [ ] مراحل اصلی نوشته شده‌اند.
- [ ] Validationها مشخص هستند.
- [ ] Eventها مشخص هستند.
- [ ] Notificationها مشخص هستند.
- [ ] Timeline مشخص است.
- [ ] Log و Audit مشخص است.
- [ ] خطاهای احتمالی مشخص هستند.
- [ ] Permission و Scope مشخص است.
- [ ] وضعیت موفق و شکست مشخص است.
- [ ] تست‌های ضروری مشخص هستند.

---

## Definition of Done

پوشه Workflows زمانی کامل است که:

- همه جریان‌های اصلی سیستم مستند شده باشند.
- هر Workflow مرحله‌به‌مرحله قابل پیاده‌سازی باشد.
- ارتباط Domainها در هر Workflow مشخص باشد.
- Event، Notification، Timeline و Log هر Workflow مشخص باشد.
- خطاها و حالت‌های شکست مشخص باشند.
- نقش‌ها و Permissionها در هر فرآیند مشخص باشند.
- Workflowها با مستندات Domainها تناقض نداشته باشند.
- Codex بتواند از روی این فایل‌ها منطق اجرایی سیستم را بسازد.

---

## پایان فایل