# Proma Pay — System Architecture

Version: 1.0
Status: Master Specification
Priority: Critical

---

# معرفی

این فایل، معماری رسمی پروژه Proma Pay را تعریف می‌کند.

هدف این مستند جلوگیری از ایجاد ساختارهای ناسازگار، کدهای تکراری، وابستگی‌های غیرضروری و افزایش پیچیدگی پروژه است.

قبل از ایجاد هر فایل، کلاس، Route، Controller یا جدول جدید، Codex باید این فایل را مطالعه کند.

---

# هدف معماری

معماری پروژه باید ویژگی‌های زیر را داشته باشد:

- توسعه‌پذیر
- ماژولار
- قابل تست
- خوانا
- قابل نگهداری
- کمترین وابستگی ممکن
- عملکرد بالا
- مناسب پروژه‌های بزرگ

---

# معماری اصلی

پروژه بر پایه MVC توسعه داده می‌شود.

```
Browser

↓

Router

↓

Middleware

↓

Controller

↓

Service

↓

Repository (در صورت نیاز)

↓

Model

↓

Database

↓

Response

↓

View
```

---

# مسئولیت هر لایه

## Router

Router فقط وظیفه دارد:

- تشخیص Route
- ارسال Request
- اجرای Middleware
- ارسال Request به Controller

Router نباید:

- Query اجرا کند.
- HTML تولید کند.
- Business Logic داشته باشد.

---

## Middleware

Middleware مسئول:

- Authentication
- Permission
- CSRF
- Rate Limit
- Maintenance Mode
- Logging

هیچ Middleware نباید Query غیرضروری اجرا کند.

---

## Controller

Controller فقط هماهنگ‌کننده است.

وظایف:

- دریافت Request
- اعتبارسنجی اولیه
- فراخوانی Service
- ارسال Response

Controller نباید:

- Query پیچیده اجرا کند.
- محاسبات مالی انجام دهد.
- HTML تولید کند.
- فایل آپلودی را مستقیماً پردازش کند.

Controller باید بسیار کوتاه باشد.

---

## Service Layer

تمام Business Logic پروژه باید در Serviceها قرار گیرد.

نمونه:

CustomerService

ContractService

InstallmentService

PaymentService

NotificationService

LegalService

CalendarService

BackupService

UpdateService

ReportService

ChatService

RewardService

MedalService

ContractFinancialSummaryService

---

Serviceها باید:

- مستقل باشند.
- تست‌پذیر باشند.
- از View مستقل باشند.

---

## Repository

در Queryهای بزرگ از Repository استفاده شود.

نمونه:

CustomerRepository

PaymentRepository

LegalRepository

Repository مسئول Query است.

Business Logic نباید داخل Repository باشد.

---

## Model

Model فقط مسئول ارتباط با Database است.

Model نباید:

- محاسبات مالی انجام دهد.
- Permission بررسی کند.
- Notification ارسال کند.

---

## View

View فقط مسئول نمایش است.

View نباید:

- Query اجرا کند.
- Session تغییر دهد.
- Business Logic داشته باشد.

---

# Helper

Helper فقط برای توابع عمومی استفاده شود.

نمونه:

formatMoney()

formatDate()

slug()

uuid()

escape()

Helper نباید:

- Query اجرا کند.
- Session تغییر دهد.
- Payment انجام دهد.

---

# Component

تمام UIهای تکراری باید Component شوند.

نمونه:

Card

Modal

Alert

Toast

Badge

Timeline

Payment Card

Notification Item

Customer Card

Contract Card

Installment Row

File Upload

Search Input

Date Picker

---

# فرم‌ها

تمام فرم‌ها باید:

CSRF

Validation

Old Input

Error Message

Loading State

داشته باشند.

---

# ساختار پوشه‌ها

```
app/

controllers/

models/

services/

repositories/

helpers/

middlewares/

views/

components/

core/

config/

storage/

public/

assets/

docs/
```

بدون دلیل پوشه جدید ایجاد نشود.

---

# قوانین ایجاد فایل

قبل از ایجاد فایل جدید:

۱- بررسی کن فایل مشابه وجود دارد یا خیر.

۲- اگر وجود دارد آن را توسعه بده.

۳- اگر فایل جدید لازم است، دلیل آن مشخص باشد.

---

# قوانین توسعه قابلیت جدید

قبل از افزودن هر قابلیت:

۱- بررسی وابستگی‌ها

۲- بررسی Database

۳- بررسی UI

۴- بررسی Permission

۵- بررسی Notification

۶- بررسی Calendar

۷- بررسی Report

۸- بررسی Log

۹- بررسی Performance

اگر قابلیت جدید به هر یک از موارد بالا مربوط باشد، بخش مربوط نیز به‌روزرسانی شود.

---

# Dependency Rule

هیچ Service نباید مستقیماً به View وابسته باشد.

هیچ Model نباید Service را صدا بزند.

View نباید Controller را دور بزند.

---

# Error Handling

تمام Exceptionها باید مدیریت شوند.

هیچ Fatal Error نباید به کاربر نمایش داده شود.

پیغام‌های کاربر:

خوانا

فارسی

مفهوم

Log کامل در سیستم ذخیره شود.

---

# Logging

تمام عملیات مهم باید Log شوند.

نمونه:

ورود

ثبت قرارداد

ویرایش قرارداد

حذف قرارداد

پرداخت

ارجاع حقوقی

ثبت شکایت

آپلود مدارک

رد مدارک

تأیید مدارک

بروزرسانی سیستم

بکاپ

---

# Notification

هیچ Service نباید مستقیماً Notification ارسال کند.

از NotificationService استفاده شود.

---

# File Upload

تمام Uploadها فقط از FileUploadService انجام شوند.

هیچ Controller نباید فایل را مستقیماً جابه‌جا کند.

---

# Payment

تمام محاسبات مالی فقط داخل PaymentService و ContractFinancialSummaryService انجام شوند.

هیچ Controller یا View نباید مبلغ نهایی را محاسبه کند.

---

# Security

تمام Permissionها سمت Server بررسی شوند.

پنهان کردن Button به معنی محدودیت دسترسی نیست.

---

# Performance

همیشه:

Pagination

Index

Prepared Statement

Lazy Loading

Eager Loading

Cache

در نظر گرفته شوند.

---

# Reuse Policy

هر کدی که بیش از دو بار تکرار می‌شود باید به Component، Helper یا Service تبدیل شود.

---

# اصل طلایی معماری

هر قابلیت جدید باید:

✔ معماری پروژه را ساده‌تر کند.

✔ قابلیت نگهداری را افزایش دهد.

✔ از کدهای موجود استفاده کند.

✔ کمترین وابستگی را ایجاد کند.

✔ قابل تست باشد.

✔ امنیت را کاهش ندهد.

✔ Performance را کاهش ندهد.

✔ مستندات مربوطه را نیز به‌روزرسانی کند.

---

# چک‌لیست قبل از Merge

قبل از Merge هر Feature، Codex باید بررسی کند:

□ آیا Controller کوتاه است؟

□ آیا Business Logic داخل Service قرار دارد؟

□ آیا Query تکراری ایجاد نشده؟

□ آیا Component جدید لازم بوده است؟

□ آیا فایل جدید واقعاً ضروری بوده است؟

□ آیا Permission بررسی شده است؟

□ آیا Log اضافه شده است؟

□ آیا Notification لازم اضافه شده است؟

□ آیا مستندات به‌روزرسانی شده‌اند؟

□ آیا Performance بررسی شده است؟

---

# پایان جلد دوم

این فایل مرجع اصلی معماری پروژه است و تمام توسعه‌های آینده باید مطابق آن انجام شوند.
