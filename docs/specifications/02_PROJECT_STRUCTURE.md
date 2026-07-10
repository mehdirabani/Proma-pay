# Proma Pay — Project Structure
Version: 1.0
Status: Master Specification
Priority: Critical

---

# معرفی

این فایل ساختار رسمی پروژه Proma Pay را مشخص می‌کند.

Codex قبل از ایجاد هر فایل، کلاس، پوشه یا ماژول جدید موظف است این فایل را مطالعه کند.

هدف این فایل جلوگیری از شلوغ شدن پروژه، ایجاد فایل‌های تکراری، نام‌گذاری ناهماهنگ و معماری ناسازگار است.

---

# اصل طلایی

قبل از ایجاد هر فایل جدید ابتدا بررسی کن:

✔ آیا فایل مشابه وجود دارد؟

✔ آیا می‌توان قابلیت را به فایل فعلی اضافه کرد؟

✔ آیا می‌توان از Component مشترک استفاده کرد؟

✔ آیا می‌توان از Service موجود استفاده کرد؟

در صورت مثبت بودن پاسخ، فایل جدید ایجاد نکن.

---

# ساختار اصلی پروژه

```

/

app

config

core

database

public

resources

routes

storage

assets

docs

vendor

```

---

# app

تمام منطق اصلی پروژه داخل app قرار می‌گیرد.

```

app/

Controllers/

Models/

Services/

Repositories/

Helpers/

Middlewares/

Policies/

Events/

Listeners/

Jobs/

Traits/

Validators/

Enums/

DTO/

Exceptions/

Console/

```

---

# Controllers

هر Controller فقط مسئول یک ماژول است.

نمونه:

```

CustomerController

UserController

ContractController

InstallmentController

PaymentController

LegalController

ChatController

NotificationController

CalendarController

SettingController

ReportController

```

قوانین:

هر Controller فقط عملیات همان ماژول را انجام دهد.

Controller نباید بیشتر از ۵۰۰ خط باشد.

در صورت بزرگ شدن، عملیات به Service منتقل شود.

---

# Models

هر جدول دیتابیس فقط یک Model داشته باشد.

نمونه:

```

Customer

Contract

Installment

Payment

LegalCase

Message

Notification

CalendarEvent

```

هیچ Model نباید مسئول محاسبات مالی باشد.

---

# Services

تمام Business Logic اینجا قرار می‌گیرد.

نمونه:

```

CustomerService

ContractService

PaymentService

NotificationService

LegalService

CalendarService

ChatService

BackupService

UpdateService

RewardService

MedalService

ReportService

ImportService

ExportService

```

Serviceها نباید HTML تولید کنند.

---

# Repositories

فقط Queryهای پیچیده.

نمونه:

```

CustomerRepository

PaymentRepository

LegalRepository

```

---

# Validators

تمام Validationهای بزرگ.

مثال:

```

CreateCustomerValidator

CreateContractValidator

PayInstallmentValidator

UploadIdentityValidator

```

---

# Policies

تمام Permissionها.

نمونه:

```

CustomerPolicy

ContractPolicy

LegalPolicy

```

هیچ Permission داخل Controller نوشته نشود.

---

# Events

نمونه:

```

CustomerCreated

ContractCreated

InstallmentPaid

PaymentRejected

IdentityApproved

LegalCaseCreated

```

---

# Listeners

نمونه:

```

SendNotification

WriteLog

UpdateTimeline

UpdateCalendar

```

---

# Jobs

تمام عملیات زمان‌بر.

نمونه:

```

DeleteExpiredReceipts

DeleteExpiredChatImages

BackupDatabase

UpdateChecker

OptimizeImages

```

---

# Traits

فقط رفتارهای مشترک.

مثال:

```

HasUuid

HasTimeline

HasLogs

HasFiles

```

---

# Helpers

توابع عمومی.

```

money()

jalali()

slug()

uuid()

escape()

```

Helper نباید Query اجرا کند.

---

# Assets

```

assets/

css/

js/

images/

fonts/

icons/

sounds/

```

---

# Fonts

```

assets/fonts/

woff/

woff2/

```

فونت پیش‌فرض:

YekanBakh

---

# Sounds

```

assets/sounds/

notification.mp3

success.mp3

warning.mp3

```

---

# Images

```

avatars/

badges/

banks/

products/

```

---

# Documents

```

storage/

contracts/

identity/

payments/

legal/

backups/

temp/

logs/

```

---

# فایل‌های Private

مدارک هویتی

رسید پرداخت

اسناد حقوقی

نباید داخل public باشند.

---

# Components

کامپوننت‌های مشترک.

```

components/

Cards/

Forms/

Tables/

Timeline/

Modals/

Notifications/

Calendar/

Chat/

Charts/

```

---

# Modals

تمام Modalها باید داخل پوشه مشترک باشند.

نه داخل Viewهای مختلف.

---

# Viewها

ساختار:

```

views/

customers/

contracts/

payments/

calendar/

chat/

settings/

```

---

# Routes

Routeها به تفکیک ماژول.

```

routes/

web.php

api.php

customer.php

admin.php

legal.php

operator.php

```

---

# Config

```

config/

app.php

database.php

payment.php

chat.php

backup.php

calendar.php

```

---

# Documentation

```

docs/

00_PROJECT_OVERVIEW.md

01_ARCHITECTURE.md

02_PROJECT_STRUCTURE.md

...
```

---

# Naming Convention

Class:

PascalCase

Method:

camelCase

Variable:

camelCase

Constant:

UPPER_CASE

Database:

snake_case

Route:

kebab-case

---

# File Naming

Controller

```

CustomerController.php

```

Model

```

Customer.php

```

Service

```

CustomerService.php

```

Repository

```

CustomerRepository.php

```

Policy

```

CustomerPolicy.php

```

---

# Maximum Size

Controller

حداکثر:

500 Line

Service

حداکثر:

700 Line

View

حداکثر:

400 Line

در صورت بیشتر شدن، Refactor الزامی است.

---

# Refactor Rules

اگر:

یک فایل بیش از حد بزرگ شد

یا

بیش از یک مسئولیت پیدا کرد

باید Refactor شود.

---

# ممنوع

❌ Helperهای تکراری

❌ Controllerهای ۱۵۰۰ خطی

❌ Query داخل View

❌ HTML داخل Service

❌ Business Logic داخل Controller

❌ Permission داخل View

❌ Upload مستقیم داخل Controller

---

# چک‌لیست قبل از ایجاد فایل

□ فایل مشابه وجود ندارد.

□ مسیر صحیح انتخاب شده.

□ نام استاندارد است.

□ مسئولیت مشخص است.

□ قابلیت استفاده مجدد دارد.

□ باعث ایجاد وابستگی اضافی نمی‌شود.

---

# اصل نهایی

هر فایل باید فقط **یک مسئولیت مشخص (Single Responsibility)** داشته باشد.

اگر برای توضیح اینکه «این فایل چه کاری انجام می‌دهد» مجبور به استفاده از واژه «و» شدی (مثلاً «مدیریت قرارداد **و** پرداخت»)، احتمالاً آن فایل بیش از یک مسئولیت دارد و باید تقسیم شود.

---

# پایان جلد سوم

این فایل مرجع اصلی ساختار پروژه است و Codex باید قبل از ایجاد هر فایل جدید آن را رعایت کند.