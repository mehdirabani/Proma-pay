پوشه:

`docs/database/`

نام فایل:

`04_USERS_ROLES_PERMISSIONS_TABLES.md`

مسیر کامل فایل:

`docs/database/04_USERS_ROLES_PERMISSIONS_TABLES.md`

````markdown
# 04 — Users, Roles & Permissions Tables

مستند جدول‌های کاربران، نقش‌ها، Permissionها، نشست‌ها، ورود، بازیابی رمز عبور، Scope و کنترل دسترسی در پروژه **Proma Pay**

---

## فهرست مطالب

- [هدف فایل](#هدف-فایل)
- [تعریف Users & Roles Domain در دیتابیس](#تعریف-users--roles-domain-در-دیتابیس)
- [اصل مهم](#اصل-مهم)
- [لیست جدول‌های Users, Roles & Permissions](#لیست-جدولهای-users-roles--permissions)
- [جدول users](#جدول-users)
- [جدول roles](#جدول-roles)
- [جدول permissions](#جدول-permissions)
- [جدول role_permissions](#جدول-role_permissions)
- [جدول user_roles](#جدول-user_roles)
- [جدول user_permissions](#جدول-user_permissions)
- [جدول user_sessions](#جدول-user_sessions)
- [جدول password_resets](#جدول-password_resets)
- [جدول user_login_attempts](#جدول-user_login_attempts)
- [جدول user_scope_assignments](#جدول-user_scope_assignments)
- [جدول user_status_histories](#جدول-user_status_histories)
- [رابطه کاربران با سایر Domainها](#رابطه-کاربران-با-سایر-domainها)
- [قوانین RBAC](#قوانین-rbac)
- [قوانین Scope](#قوانین-scope)
- [قوانین امنیت رمز عبور](#قوانین-امنیت-رمز-عبور)
- [قوانین نشست کاربر](#قوانین-نشست-کاربر)
- [قوانین Permission](#قوانین-permission)
- [قوانین Audit و Security Log](#قوانین-audit-و-security-log)
- [قوانین Index و Performance](#قوانین-index-و-performance)
- [Seedهای ضروری](#seedهای-ضروری)
- [چک‌لیست پیاده‌سازی](#چکلیست-پیادهسازی)
- [Definition of Done](#definition-of-done)

---

## هدف فایل

هدف این فایل این است که ساختار دیتابیس مربوط به کاربران، نقش‌ها، Permissionها، نشست‌ها و کنترل دسترسی در پروژه **Proma Pay** مشخص شود.

این بخش برای امنیت کل سیستم حیاتی است؛ چون تمام عملیات حساس مثل ایجاد قرارداد، تأیید پرداخت، ارجاع حقوقی، خروجی گزارش، نصب پلاگین، بکاپ و آپدیت باید فقط توسط کاربر مجاز انجام شوند.

این فایل برای Codex مشخص می‌کند که:

- کاربران چگونه ذخیره شوند.
- نقش‌ها چگونه تعریف شوند.
- Permissionها چگونه مدیریت شوند.
- رابطه کاربر و نقش چگونه ذخیره شود.
- Scope دسترسی کاربران چگونه کنترل شود.
- نشست‌های کاربر چگونه ذخیره و باطل شوند.
- تلاش‌های ورود ناموفق چگونه ثبت شوند.
- عملیات حساس چه Audit و Security Logهایی لازم دارند.

---

## تعریف Users & Roles Domain در دیتابیس

این Domain مسئول مدیریت هویت و دسترسی کاربران سیستم است.

کاربر می‌تواند یکی از انواع زیر باشد:

- کاربر داخلی سیستم
- مدیر
- حسابدار
- اپراتور
- وکیل
- مدیر واحد
- Super Admin
- مشتری دارای پنل
- کاربر سیستمی برای عملیات داخلی

نقش‌ها مشخص می‌کنند هر کاربر چه سطحی از دسترسی دارد.

Permissionها مشخص می‌کنند کاربر دقیقاً چه کاری می‌تواند انجام دهد.

Scope مشخص می‌کند کاربر روی چه محدوده‌ای از داده‌ها دسترسی دارد.

مثلاً:

یک اپراتور ممکن است Permission مشاهده معوقات را داشته باشد، اما فقط برای مشتریان خودش.

---

## اصل مهم

اصل مهم در Users, Roles & Permissions:

> هیچ عملیات حساسی نباید فقط با بررسی UI یا Frontend کنترل شود.

تمام کنترل‌های زیر باید سمت سرور انجام شوند:

- Authentication
- Permission Check
- Scope Check
- Ownership Check
- CSRF Check
- Session Validity Check
- Account Status Check

قانون طلایی:

> اگر کاربر در UI دکمه‌ای را نمی‌بیند، کافی نیست؛ سرور هم باید دسترسی را رد کند.

---

## لیست جدول‌های Users, Roles & Permissions

جدول‌های پیشنهادی این Domain:

| جدول | کاربرد |
|---|---|
| `users` | اطلاعات حساب‌های قابل ورود به سیستم |
| `roles` | نقش‌های سیستم |
| `permissions` | Permissionهای سیستم |
| `role_permissions` | اتصال نقش‌ها به Permissionها |
| `user_roles` | اتصال کاربران به نقش‌ها |
| `user_permissions` | Permission مستقیم یا Override برای کاربر |
| `user_sessions` | نشست‌های فعال و قبلی کاربر |
| `password_resets` | توکن‌های بازیابی رمز عبور |
| `user_login_attempts` | تلاش‌های ورود موفق و ناموفق |
| `user_scope_assignments` | محدوده دسترسی کاربران |
| `user_status_histories` | تاریخچه تغییر وضعیت کاربران |

---

## جدول users

### هدف جدول

جدول `users` اطلاعات حساب‌های قابل ورود به سیستم را نگهداری می‌کند.

این جدول برای کاربران داخلی و در صورت نیاز برای مشتریان دارای پنل استفاده می‌شود.

---

### نام جدول

```text
users
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه داخلی |
| `user_number` | VARCHAR(50) | شماره رسمی کاربر |
| `customer_id` | BIGINT UNSIGNED NULL | اتصال به مشتری در صورت حساب مشتری |
| `first_name` | VARCHAR(100) NULL | نام |
| `last_name` | VARCHAR(100) NULL | نام خانوادگی |
| `full_name` | VARCHAR(191) NULL | نام کامل |
| `username` | VARCHAR(100) NULL | نام کاربری |
| `email` | VARCHAR(191) NULL | ایمیل |
| `phone` | VARCHAR(30) NULL | شماره موبایل |
| `password_hash` | VARCHAR(255) | رمز عبور هش‌شده |
| `user_type` | VARCHAR(50) | نوع کاربر |
| `status` | VARCHAR(50) | وضعیت حساب |
| `is_super_admin` | TINYINT(1) | دسترسی کامل سیستمی |
| `is_system_user` | TINYINT(1) | کاربر سیستمی |
| `must_change_password` | TINYINT(1) | الزام تغییر رمز در ورود بعدی |
| `last_login_at` | DATETIME NULL | آخرین ورود |
| `last_login_ip` | VARCHAR(45) NULL | IP آخرین ورود |
| `last_activity_at` | DATETIME NULL | آخرین فعالیت |
| `password_changed_at` | DATETIME NULL | زمان آخرین تغییر رمز |
| `locked_until` | DATETIME NULL | زمان پایان قفل حساب |
| `failed_login_count` | INT UNSIGNED | تعداد تلاش ناموفق |
| `timezone` | VARCHAR(100) NULL | Timezone اختصاصی |
| `locale` | VARCHAR(20) NULL | زبان کاربر |
| `avatar_file_id` | BIGINT UNSIGNED NULL | تصویر پروفایل |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### user_typeهای پیشنهادی

```text
internal
customer
system
api
```

---

### statusهای پیشنهادی

```text
active
inactive
pending
blocked
locked
deleted
```

---

### قوانین

- رمز عبور خام هرگز ذخیره نشود.
- فقط `password_hash` ذخیره شود.
- ایمیل یا موبایل باید طبق تنظیمات سیستم Unique یا کنترل‌شده باشد.
- `is_super_admin` فقط برای کاربر سطح بالا استفاده شود.
- مشتری دارای پنل باید به `customer_id` وصل باشد.
- کاربر سیستمی نباید برای ورود عادی استفاده شود.
- حساب locked تا زمان `locked_until` اجازه ورود نداشته باشد.
- حذف کاربر باید Soft Delete باشد.
- کاربری که عملیات مالی یا حقوقی ثبت کرده نباید فیزیکی حذف شود.
- تغییر وضعیت کاربر باید Audit Log داشته باشد.
- تغییر `is_super_admin` باید Audit Log و Security Log داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_users_user_number
uniq_users_username
uniq_users_email
idx_users_phone
idx_users_customer_id
idx_users_user_type
idx_users_status
idx_users_is_super_admin
idx_users_is_system_user
idx_users_last_login_at
idx_users_deleted_at
```

---

### نمونه ساختار SQL

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_number VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    full_name VARCHAR(191) NULL,
    username VARCHAR(100) NULL,
    email VARCHAR(191) NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_type VARCHAR(50) NOT NULL DEFAULT 'internal',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_system_user TINYINT(1) NOT NULL DEFAULT 0,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    last_activity_at DATETIME NULL,
    password_changed_at DATETIME NULL,
    locked_until DATETIME NULL,
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    timezone VARCHAR(100) NULL,
    locale VARCHAR(20) NULL,
    avatar_file_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_user_number (user_number),
    UNIQUE KEY uniq_users_username (username),
    UNIQUE KEY uniq_users_email (email),
    KEY idx_users_phone (phone),
    KEY idx_users_customer_id (customer_id),
    KEY idx_users_user_type (user_type),
    KEY idx_users_status (status),
    KEY idx_users_is_super_admin (is_super_admin),
    KEY idx_users_is_system_user (is_system_user),
    KEY idx_users_last_login_at (last_login_at),
    KEY idx_users_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## جدول roles

### هدف جدول

جدول `roles` نقش‌های سیستم را نگهداری می‌کند.

نقش‌ها مجموعه‌ای از Permissionها هستند.

---

### نام جدول

```text
roles
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `role_key` | VARCHAR(100) | کلید یکتا |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `role_type` | VARCHAR(50) | نوع نقش |
| `is_system` | TINYINT(1) | نقش سیستمی |
| `is_default` | TINYINT(1) | نقش پیش‌فرض |
| `is_active` | TINYINT(1) | فعال بودن |
| `sort_order` | INT UNSIGNED | ترتیب نمایش |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |
| `deleted_at` | DATETIME NULL | زمان حذف نرم |
| `created_by` | BIGINT UNSIGNED NULL | ایجادکننده |
| `updated_by` | BIGINT UNSIGNED NULL | ویرایش‌کننده |
| `deleted_by` | BIGINT UNSIGNED NULL | حذف‌کننده |

---

### role_typeهای پیشنهادی

```text
internal
customer
system
plugin
```

---

### نقش‌های پایه پیشنهادی

```text
super_admin
admin
accountant
operator
lawyer
department_manager
customer
system
```

---

### قوانین

- `role_key` باید Unique باشد.
- نقش Super Admin نباید حذف شود.
- نقش‌های سیستمی فقط با Permission ویژه قابل تغییر باشند.
- حذف نقش باید Soft Delete باشد.
- اگر نقش به کاربر وصل است، حذف فیزیکی ممنوع است.
- تغییر Permissionهای نقش باید Audit Log داشته باشد.
- نقش Customer نباید Permission داخلی داشته باشد.
- نقش Plugin باید namespace مشخص داشته باشد.

---

### Indexهای پیشنهادی

```text
uniq_roles_role_key
idx_roles_role_type
idx_roles_is_system
idx_roles_is_default
idx_roles_is_active
idx_roles_deleted_at
```

---

## جدول permissions

### هدف جدول

جدول `permissions` لیست تمام دسترسی‌های قابل کنترل سیستم را نگهداری می‌کند.

Permission کوچک‌ترین واحد کنترل دسترسی است.

---

### نام جدول

```text
permissions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `permission_key` | VARCHAR(150) | کلید یکتا |
| `name` | VARCHAR(191) | نام نمایشی |
| `description` | TEXT NULL | توضیح |
| `domain` | VARCHAR(100) | Domain مربوطه |
| `resource` | VARCHAR(100) | منبع |
| `action` | VARCHAR(100) | عملیات |
| `is_sensitive` | TINYINT(1) | حساس بودن |
| `is_system` | TINYINT(1) | Permission سیستمی |
| `is_active` | TINYINT(1) | فعال بودن |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### الگوی permission_key

```text
domain.resource.action
```

نمونه:

```text
customers.customers.view
customers.customers.create
customers.customers.update
contracts.contracts.create
payments.payments.approve
reports.reports.export
plugins.plugins.manage
backups.backups.restore
```

برای پلاگین‌ها:

```text
plugin.{plugin_id}.{action}
```

نمونه:

```text
plugin.sms_provider.manage
plugin.ai_assistant.use
```

---

### قوانین

- `permission_key` باید Unique باشد.
- Permission نباید مبهم باشد.
- Permission حساس باید `is_sensitive = 1` داشته باشد.
- Permissionهای حذف‌شده نباید داده Roleها را خراب کنند.
- Permissionهای Core باید Seed شوند.
- Permission پلاگین باید هنگام نصب پلاگین ثبت شود.
- مشتری نباید Permissionهای داخلی یا مدیریتی دریافت کند.

---

### Indexهای پیشنهادی

```text
uniq_permissions_permission_key
idx_permissions_domain
idx_permissions_resource
idx_permissions_action
idx_permissions_is_sensitive
idx_permissions_is_system
idx_permissions_is_active
```

---

## جدول role_permissions

### هدف جدول

جدول `role_permissions` رابطه چندبه‌چند بین نقش‌ها و Permissionها را نگهداری می‌کند.

---

### نام جدول

```text
role_permissions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `role_id` | BIGINT UNSIGNED | نقش |
| `permission_id` | BIGINT UNSIGNED | Permission |
| `granted_by` | BIGINT UNSIGNED NULL | اعطاکننده |
| `granted_at` | DATETIME NULL | زمان اعطا |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### قوانین

- ترکیب `role_id` و `permission_id` باید Unique باشد.
- Permission حساس نباید بدون Audit به نقش اضافه شود.
- حذف Permission از نقش باید Audit Log داشته باشد.
- تغییر Permission نقش سیستمی باید محدود باشد.
- تغییر Permission نقش Super Admin باید بسیار محدود باشد.
- Permissionهای Customer و Internal نباید بی‌قاعده ترکیب شوند.

---

### Indexهای پیشنهادی

```text
uniq_role_permissions_role_permission
idx_role_permissions_role_id
idx_role_permissions_permission_id
idx_role_permissions_granted_by
```

---

## جدول user_roles

### هدف جدول

جدول `user_roles` رابطه چندبه‌چند بین کاربران و نقش‌ها را نگهداری می‌کند.

یک کاربر می‌تواند چند نقش داشته باشد.

---

### نام جدول

```text
user_roles
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED | کاربر |
| `role_id` | BIGINT UNSIGNED | نقش |
| `assigned_by` | BIGINT UNSIGNED NULL | اختصاص‌دهنده |
| `assigned_at` | DATETIME NULL | زمان اختصاص |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `is_active` | TINYINT(1) | فعال بودن |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### قوانین

- ترکیب `user_id` و `role_id` برای نقش فعال باید Unique باشد.
- اعطای نقش حساس باید Audit Log داشته باشد.
- اعطای نقش Super Admin باید Security Log داشته باشد.
- نقش منقضی‌شده نباید Permission ایجاد کند.
- نقش غیرفعال نباید Permission ایجاد کند.
- مشتری نباید نقش داخلی دریافت کند.
- کاربر داخلی نباید به اشتباه نقش Customer-only بگیرد، مگر طراحی شده باشد.

---

### Indexهای پیشنهادی

```text
idx_user_roles_user_id
idx_user_roles_role_id
idx_user_roles_is_active
idx_user_roles_expires_at
idx_user_roles_assigned_by
```

---

## جدول user_permissions

### هدف جدول

جدول `user_permissions` برای اعطای Permission مستقیم یا Override به یک کاربر استفاده می‌شود.

این جدول باید با احتیاط استفاده شود؛ چون می‌تواند مدیریت دسترسی را پیچیده کند.

---

### نام جدول

```text
user_permissions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED | کاربر |
| `permission_id` | BIGINT UNSIGNED | Permission |
| `effect` | VARCHAR(20) | نتیجه |
| `reason` | TEXT NULL | دلیل |
| `granted_by` | BIGINT UNSIGNED NULL | اعطاکننده |
| `granted_at` | DATETIME NULL | زمان اعطا |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `is_active` | TINYINT(1) | فعال بودن |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### effectهای پیشنهادی

```text
allow
deny
```

---

### قوانین

- استفاده از Permission مستقیم باید محدود باشد.
- `deny` باید نسبت به `allow` اولویت داشته باشد.
- Permission مستقیم حساس باید Audit Log داشته باشد.
- Permission مستقیم باید reason داشته باشد.
- Permission مستقیم بهتر است expires_at داشته باشد.
- مشتری نباید Permission داخلی مستقیم بگیرد.
- Permission مستقیم نباید جایگزین نقش‌های استاندارد شود.

---

### Indexهای پیشنهادی

```text
idx_user_permissions_user_id
idx_user_permissions_permission_id
idx_user_permissions_effect
idx_user_permissions_is_active
idx_user_permissions_expires_at
```

---

## جدول user_sessions

### هدف جدول

جدول `user_sessions` برای مدیریت نشست‌های کاربران استفاده می‌شود.

با اینکه PHP Session می‌تواند فایل‌محور باشد، نگهداری Metadata نشست در دیتابیس برای امنیت، مشاهده نشست‌ها و خروج اجباری مفید است.

---

### نام جدول

```text
user_sessions
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED | کاربر |
| `session_hash` | VARCHAR(191) | Hash شناسه نشست |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | مرورگر یا کلاینت |
| `device_name` | VARCHAR(191) NULL | نام دستگاه |
| `platform` | VARCHAR(100) NULL | پلتفرم |
| `login_at` | DATETIME | زمان ورود |
| `last_activity_at` | DATETIME NULL | آخرین فعالیت |
| `expires_at` | DATETIME NULL | زمان انقضا |
| `logout_at` | DATETIME NULL | زمان خروج |
| `revoked_at` | DATETIME NULL | زمان ابطال |
| `revoked_by` | BIGINT UNSIGNED NULL | ابطال‌کننده |
| `status` | VARCHAR(50) | وضعیت |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### statusهای پیشنهادی

```text
active
expired
revoked
logged_out
blocked
```

---

### قوانین

- session_id خام نباید ذخیره شود.
- فقط hash نشست ذخیره شود.
- خروج از همه دستگاه‌ها باید ممکن باشد.
- تغییر رمز عبور می‌تواند نشست‌های قبلی را revoke کند.
- نشست کاربر blocked باید باطل شود.
- مشاهده نشست‌ها باید Permission داشته باشد.
- ابطال نشست دیگران باید Audit Log داشته باشد.

---

### Indexهای پیشنهادی

```text
idx_user_sessions_user_id
uniq_user_sessions_session_hash
idx_user_sessions_status
idx_user_sessions_last_activity_at
idx_user_sessions_expires_at
idx_user_sessions_revoked_at
```

---

## جدول password_resets

### هدف جدول

جدول `password_resets` برای مدیریت توکن‌های بازیابی رمز عبور استفاده می‌شود.

---

### نام جدول

```text
password_resets
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED NULL | کاربر |
| `email` | VARCHAR(191) NULL | ایمیل درخواست‌دهنده |
| `phone` | VARCHAR(30) NULL | موبایل درخواست‌دهنده |
| `token_hash` | VARCHAR(191) | Hash توکن |
| `channel` | VARCHAR(50) | کانال ارسال |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | User Agent |
| `status` | VARCHAR(50) | وضعیت |
| `requested_at` | DATETIME | زمان درخواست |
| `expires_at` | DATETIME | زمان انقضا |
| `used_at` | DATETIME NULL | زمان استفاده |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### channelهای پیشنهادی

```text
email
sms
manual
```

---

### statusهای پیشنهادی

```text
pending
used
expired
cancelled
revoked
```

---

### قوانین

- توکن خام نباید ذخیره شود.
- فقط `token_hash` ذخیره شود.
- توکن باید زمان انقضا داشته باشد.
- هر توکن فقط یک بار قابل استفاده باشد.
- درخواست‌های زیاد باید محدود شوند.
- بازیابی رمز باید Security Log داشته باشد.
- تغییر رمز موفق باید نشست‌های قبلی را باطل کند.

---

### Indexهای پیشنهادی

```text
idx_password_resets_user_id
idx_password_resets_email
idx_password_resets_phone
idx_password_resets_token_hash
idx_password_resets_status
idx_password_resets_expires_at
```

---

## جدول user_login_attempts

### هدف جدول

جدول `user_login_attempts` تلاش‌های ورود موفق و ناموفق را ذخیره می‌کند.

این جدول برای امنیت، تشخیص حملات و قفل حساب کاربرد دارد.

---

### نام جدول

```text
user_login_attempts
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED NULL | کاربر در صورت شناسایی |
| `identifier` | VARCHAR(191) NULL | ایمیل، موبایل یا نام کاربری واردشده |
| `ip_address` | VARCHAR(45) NULL | IP |
| `user_agent` | TEXT NULL | مرورگر |
| `status` | VARCHAR(50) | وضعیت تلاش |
| `failure_reason` | VARCHAR(100) NULL | دلیل شکست |
| `attempted_at` | DATETIME | زمان تلاش |
| `metadata` | JSON NULL | داده تکمیلی |
| `created_at` | DATETIME NULL | زمان ایجاد |

---

### statusهای پیشنهادی

```text
success
failed
blocked
locked
```

---

### failure_reasonهای پیشنهادی

```text
invalid_credentials
user_not_found
user_inactive
user_locked
csrf_invalid
too_many_attempts
password_expired
```

---

### قوانین

- تلاش ناموفق باید ثبت شود.
- تلاش زیاد از یک IP باید محدود شود.
- تلاش زیاد برای یک حساب باید حساب را قفل کند.
- اطلاعات رمز واردشده هرگز ذخیره نشود.
- user_agent و IP برای بررسی امنیتی ذخیره شوند.
- این جدول می‌تواند با Security Log هم‌زمان استفاده شود.
- داده‌های قدیمی باید قابل پاکسازی یا Archive باشند.

---

### Indexهای پیشنهادی

```text
idx_user_login_attempts_user_id
idx_user_login_attempts_identifier
idx_user_login_attempts_ip_address
idx_user_login_attempts_status
idx_user_login_attempts_attempted_at
```

---

## جدول user_scope_assignments

### هدف جدول

جدول `user_scope_assignments` محدوده دسترسی کاربران را مشخص می‌کند.

Permission مشخص می‌کند کاربر چه کاری می‌تواند انجام دهد.  
Scope مشخص می‌کند روی چه داده‌هایی می‌تواند آن کار را انجام دهد.

مثال:

اپراتور می‌تواند معوقات را ببیند، اما فقط معوقات مشتریان خودش را.

---

### نام جدول

```text
user_scope_assignments
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED | کاربر |
| `scope_type` | VARCHAR(50) | نوع Scope |
| `scope_value` | VARCHAR(191) NULL | مقدار Scope |
| `related_type` | VARCHAR(100) NULL | نوع موجودیت مرتبط |
| `related_id` | BIGINT UNSIGNED NULL | شناسه مرتبط |
| `starts_at` | DATETIME NULL | شروع اعتبار |
| `ends_at` | DATETIME NULL | پایان اعتبار |
| `is_active` | TINYINT(1) | فعال بودن |
| `assigned_by` | BIGINT UNSIGNED NULL | اختصاص‌دهنده |
| `assigned_at` | DATETIME NULL | زمان اختصاص |
| `reason` | TEXT NULL | دلیل |
| `created_at` | DATETIME NULL | زمان ایجاد |
| `updated_at` | DATETIME NULL | زمان بروزرسانی |

---

### scope_typeهای پیشنهادی

```text
all
own
assigned
department
branch
customer
contract
legal_case
custom
```

---

### نمونه Scope

```text
all
own
assigned
department:5
branch:2
customer:15
contract:30
```

---

### قوانین

- Scope باید سمت سرور اعمال شود.
- Scope نباید فقط در UI کنترل شود.
- Super Admin می‌تواند Scope کامل داشته باشد.
- اپراتور معمولاً Scope `assigned` یا `own` دارد.
- مدیر واحد معمولاً Scope `department` دارد.
- مشتری فقط Scope خودش را دارد.
- تغییر Scope باید Audit Log داشته باشد.
- Scope منقضی‌شده نباید اعمال شود.

---

### Indexهای پیشنهادی

```text
idx_user_scope_assignments_user_id
idx_user_scope_assignments_scope_type
idx_user_scope_assignments_related
idx_user_scope_assignments_is_active
idx_user_scope_assignments_starts_at
idx_user_scope_assignments_ends_at
```

---

## جدول user_status_histories

### هدف جدول

جدول `user_status_histories` تاریخچه تغییر وضعیت کاربران را نگهداری می‌کند.

---

### نام جدول

```text
user_status_histories
```

---

### ستون‌های پیشنهادی

| ستون | نوع | توضیح |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | شناسه |
| `user_id` | BIGINT UNSIGNED | کاربر |
| `old_status` | VARCHAR(50) NULL | وضعیت قبلی |
| `new_status` | VARCHAR(50) | وضعیت جدید |
| `reason` | TEXT NULL | دلیل تغییر |
| `changed_by` | BIGINT UNSIGNED NULL | تغییر دهنده |
| `changed_at` | DATETIME | زمان تغییر |
| `metadata` | JSON NULL | داده تکمیلی |

---

### قوانین

- تغییر status کاربر باید در این جدول ثبت شود.
- قفل شدن حساب به دلیل ورود ناموفق باید ثبت شود.
- فعال یا غیرفعال کردن حساب باید ثبت شود.
- حذف نرم کاربر باید ثبت شود.
- تغییر وضعیت Super Admin باید Security Log هم داشته باشد.
- این جدول نباید Soft Delete شود.

---

### Indexهای پیشنهادی

```text
idx_user_status_histories_user_id
idx_user_status_histories_new_status
idx_user_status_histories_changed_by
idx_user_status_histories_changed_at
```

---

## رابطه کاربران با سایر Domainها

کاربران تقریباً با همه Domainهای سیستم ارتباط دارند.

| Domain | رابطه |
|---|---|
| Customers | کاربر می‌تواند به customer_id وصل شود |
| Contracts | created_by، approved_by، cancelled_by |
| Installments | پیگیری و تغییر وضعیت |
| Payments | approved_by، rejected_by، reviewed_by |
| Legal | assigned_lawyer_id، referred_by |
| Files | uploaded_by، downloaded_by |
| Reports | exported_by، downloaded_by |
| Plugins | installed_by، enabled_by |
| Backup & Update | created_by، restored_by، updated_by |
| Audit | actor_user_id |
| Security | user_id یا identifier |
| Chat | sender_user_id |
| Calendar | assigned_user_id، created_by |

---

## قوانین RBAC

RBAC یعنی Role Based Access Control.

قوانین:

- هر کاربر از طریق Roleها Permission دریافت می‌کند.
- Permission مستقیم فقط برای استثناها استفاده شود.
- Super Admin همه دسترسی‌ها را دارد، اما عملیات او همچنان Log می‌شود.
- نقش Customer باید کاملاً محدود باشد.
- نقش‌های سیستمی نباید بی‌دلیل قابل حذف باشند.
- Permission حساس باید جدا تعریف شود.
- تغییر Role و Permission باید Audit Log داشته باشد.
- Permissionها باید سمت سرور بررسی شوند.

---

## قوانین Scope

Scope مشخص می‌کند Permission روی چه محدوده‌ای اعمال می‌شود.

مثال:

```text
payments.view + own
payments.view + department
payments.view + all
```

قوانین:

- Scope باید در Query اعمال شود.
- Scope نباید در Frontend اعتماد شود.
- Scope کاربر باید در گزارش‌ها هم رعایت شود.
- Scope باید در Exportها هم رعایت شود.
- اگر کاربر Permission دارد ولی Scope ندارد، باید فقط داده‌های مجاز حداقلی را ببیند یا دسترسی رد شود.
- تغییر Scope باید Audit Log داشته باشد.

---

## قوانین امنیت رمز عبور

قوانین:

- رمز عبور خام ذخیره نشود.
- Hash رمز عبور در `password_hash` ذخیره شود.
- الگوریتم Hash باید امن باشد.
- تغییر رمز باید `password_changed_at` را بروزرسانی کند.
- بعد از تغییر رمز، نشست‌های قبلی می‌توانند revoke شوند.
- رمز عبور ضعیف باید رد شود.
- تلاش‌های ورود ناموفق باید محدود شوند.
- بازیابی رمز باید token_hash و expiration داشته باشد.

---

## قوانین نشست کاربر

قوانین:

- session_id خام ذخیره نشود.
- فقط session_hash ذخیره شود.
- نشست منقضی‌شده نباید معتبر باشد.
- نشست revoke شده نباید معتبر باشد.
- خروج از همه دستگاه‌ها باید ممکن باشد.
- تغییر رمز می‌تواند نشست‌های قبلی را باطل کند.
- تغییر نقش حساس می‌تواند نیازمند ورود مجدد باشد.
- نشست کاربر blocked باید باطل شود.

---

## قوانین Permission

Permissionها باید واضح و قابل جستجو باشند.

نمونه Permissionهای پایه:

```text
users.users.view
users.users.create
users.users.update
users.users.delete
users.roles.view
users.roles.create
users.roles.update
users.roles.delete
users.permissions.view
users.permissions.assign
customers.customers.view
customers.customers.create
contracts.contracts.create
payments.payments.approve
payments.payments.reject
reports.reports.view
reports.reports.export
plugins.plugins.manage
backups.backups.create
backups.backups.restore
settings.settings.manage
```

قوانین:

- Permission نباید خیلی کلی باشد.
- Permissionهای حساس باید جدا شوند.
- Export باید Permission جدا داشته باشد.
- Restore بکاپ باید Permission جدا و حساس داشته باشد.
- نصب پلاگین باید Permission جدا و حساس داشته باشد.
- تغییر Permissionها باید Audit Log داشته باشد.

---

## قوانین Audit و Security Log

### Audit Log الزامی برای:

- ایجاد کاربر داخلی
- ویرایش کاربر
- حذف نرم کاربر
- تغییر وضعیت کاربر
- قفل یا باز کردن حساب
- تغییر نقش کاربر
- اعطای Permission مستقیم
- حذف Permission مستقیم
- تغییر Permission نقش
- تغییر Scope کاربر
- ابطال نشست کاربر
- تغییر رمز توسط Admin
- فعال یا غیرفعال کردن Super Admin

### Security Log الزامی برای:

- تلاش ورود ناموفق زیاد
- ورود از IP مشکوک
- تلاش دسترسی بدون Permission
- تلاش تغییر Role بدون Permission
- تلاش تغییر Scope بدون Permission
- CSRF نامعتبر
- تلاش استفاده از نشست منقضی‌شده
- تلاش استفاده از نشست revoke شده
- تلاش بازیابی رمز مشکوک
- تغییر Super Admin
- دستکاری user_id در درخواست‌ها

---

## قوانین Index و Performance

قوانین:

- جستجوی کاربر با email، phone و username باید سریع باشد.
- بررسی Permission باید بهینه باشد.
- role_permissions و user_roles باید Index مناسب داشته باشند.
- session_hash باید Unique و سریع قابل جستجو باشد.
- login_attempts باید بر اساس IP و identifier فیلترپذیر باشد.
- لاگ‌های ورود قدیمی باید پاکسازی یا Archive شوند.
- Queryهای RBAC نباید در هر درخواست سنگین باشند.
- Permissionهای کاربر می‌توانند Cache شوند، اما Cache نباید منبع حقیقت باشد.

Indexهای مهم:

```text
users.email
users.phone
users.username
users.status
roles.role_key
permissions.permission_key
role_permissions.role_id
role_permissions.permission_id
user_roles.user_id
user_roles.role_id
user_sessions.session_hash
user_login_attempts.ip_address
user_login_attempts.identifier
```

---

## Seedهای ضروری

### نقش‌های پایه

```text
super_admin
admin
accountant
operator
lawyer
department_manager
customer
system
```

---

### Permissionهای پایه کاربران

```text
users.users.view
users.users.create
users.users.update
users.users.delete
users.roles.view
users.roles.create
users.roles.update
users.roles.delete
users.permissions.view
users.permissions.assign
users.sessions.view
users.sessions.revoke
```

---

### Permissionهای حساس

```text
users.super_admin.assign
users.roles.assign_sensitive
users.permissions.assign_sensitive
backups.backups.restore
plugins.plugins.manage
reports.reports.export_sensitive
settings.settings.manage
```

---

### کاربر اولیه

در نصب اولیه باید یک کاربر Super Admin ساخته شود.

قوانین:

- رمز اولیه باید بعد از اولین ورود تغییر کند.
- `must_change_password = 1` باشد.
- اطلاعات ورود نباید در Log خام ذخیره شود.
- ساخت کاربر اولیه باید در Installation Log ثبت شود.

---

## چک‌لیست پیاده‌سازی

قبل از پیاده‌سازی این جدول‌ها بررسی شود:

- [ ] جدول `users` ساخته شده است.
- [ ] رمز عبور فقط به صورت Hash ذخیره می‌شود.
- [ ] جدول `roles` ساخته شده است.
- [ ] جدول `permissions` ساخته شده است.
- [ ] جدول `role_permissions` ساخته شده است.
- [ ] جدول `user_roles` ساخته شده است.
- [ ] جدول `user_permissions` برای Override وجود دارد.
- [ ] جدول `user_sessions` وجود دارد.
- [ ] session_id خام ذخیره نمی‌شود.
- [ ] جدول `password_resets` وجود دارد.
- [ ] token خام بازیابی رمز ذخیره نمی‌شود.
- [ ] جدول `user_login_attempts` وجود دارد.
- [ ] جدول `user_scope_assignments` وجود دارد.
- [ ] جدول `user_status_histories` وجود دارد.
- [ ] Permissionها سمت سرور بررسی می‌شوند.
- [ ] Scope در Queryها اعمال می‌شود.
- [ ] عملیات حساس Audit Log دارند.
- [ ] تلاش‌های غیرمجاز Security Log دارند.
- [ ] نقش‌های اولیه Seed شده‌اند.
- [ ] Super Admin اولیه قابل ایجاد است.

---

## Definition of Done

Users, Roles & Permissions Tables زمانی کامل هستند که:

- کاربر داخلی و مشتری قابل ورود به سیستم باشند.
- رمز عبور خام هیچ‌جا ذخیره نشود.
- نقش‌ها و Permissionها قابل تعریف باشند.
- کاربر بتواند چند نقش داشته باشد.
- نقش بتواند چند Permission داشته باشد.
- Permission مستقیم به کاربر فقط در موارد کنترل‌شده قابل اعطا باشد.
- Permissionهای حساس جدا و قابل Audit باشند.
- Scope کاربر قابل تعریف و در Queryها قابل اعمال باشد.
- نشست‌های کاربر قابل مدیریت، ابطال و انقضا باشند.
- بازیابی رمز عبور امن و دارای token_hash باشد.
- تلاش‌های ورود ناموفق ثبت و قابل محدودسازی باشند.
- تغییر وضعیت کاربر در تاریخچه ثبت شود.
- عملیات حساس Audit Log داشته باشند.
- تلاش‌های غیرمجاز Security Log داشته باشند.
- مشتری هیچ دسترسی داخلی غیرمجاز نداشته باشد.
- Super Admin قابل کنترل، قابل Audit و امن باشد.
- Codex بتواند از روی این مستندات Migrationهای Auth/RBAC را بسازد.

---

## پایان فایل
````
