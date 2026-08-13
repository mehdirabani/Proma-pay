CREATE TABLE IF NOT EXISTS proma_connect_event_catalog (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_key VARCHAR(100) NOT NULL,
  title_fa VARCHAR(190) NOT NULL,
  description_fa VARCHAR(500) NOT NULL,
  category VARCHAR(40) NOT NULL,
  source_module VARCHAR(80) NOT NULL,
  sensitivity VARCHAR(20) NOT NULL DEFAULT 'service',
  default_enabled TINYINT(1) NOT NULL DEFAULT 0,
  mandatory TINYINT(1) NOT NULL DEFAULT 0,
  allowed_recipients_json LONGTEXT NOT NULL,
  supported_channels_json LONGTEXT NOT NULL,
  variables_json LONGTEXT NOT NULL,
  default_text TEXT NOT NULL,
  sample_text TEXT NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_connect_event_key (event_key),
  KEY idx_connect_event_list (is_active,category,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_provider_event_mappings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider_key VARCHAR(40) NOT NULL,
  provider_account_id BIGINT UNSIGNED NULL,
  event_key VARCHAR(100) NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  send_mode VARCHAR(30) NOT NULL DEFAULT 'disabled',
  external_template_id VARCHAR(190) NULL,
  variable_mapping_json LONGTEXT NOT NULL,
  template_body TEXT NULL,
  validation_state VARCHAR(30) NOT NULL DEFAULT 'unverified',
  template_version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_provider_event_mapping (provider_key,provider_account_id,event_key),
  KEY idx_provider_event_enabled (provider_key,is_enabled,event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_template_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NULL,
  body TEXT NOT NULL,
  variables_json LONGTEXT NOT NULL,
  change_reason VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  archived_at DATETIME NULL,
  UNIQUE KEY uq_template_version (template_id,version_no),
  KEY idx_template_version_created (template_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_retention_policies (
  policy_key VARCHAR(80) NOT NULL PRIMARY KEY,
  retention_days INT UNSIGNED NULL,
  can_delete TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO proma_connect_event_catalog
(event_key,title_fa,description_fa,category,source_module,sensitivity,default_enabled,mandatory,allowed_recipients_json,supported_channels_json,variables_json,default_text,sample_text,sort_order,is_active,created_at)
VALUES
('account_login_otp','کد ورود یکبار مصرف','ارسال کد موقت برای ورود کاربر به سامانه.','security','auth','security',0,1,'["user"]','["ippanel","smsir","bale","telegram"]','["otp","expiry_minutes","store_name"]','کد ورود شما به {store_name}: {otp}\nاعتبار: {expiry_minutes} دقیقه','کد ورود شما به پرما پی: ******\nاعتبار: ۳ دقیقه',10,1,NOW()),
('suspicious_login','ورود مشکوک','اطلاع‌رسانی درباره ورود غیرعادی یا دستگاه ناشناس.','security','auth','security',1,1,'["user","admin"]','["in_app","sms","telegram"]','["customer_name","login_time","portal_url"]','{customer_name} گرامی، ورود مشکوکی به حساب شما ثبت شد.','کاربر گرامی، ورود مشکوکی به حساب شما ثبت شد.',20,1,NOW()),
('account_locked','مسدود شدن موقت حساب','اطلاع‌رسانی پس از عبور از حد مجاز تلاش ناموفق.','security','auth','security',1,1,'["user","admin"]','["in_app","sms","telegram"]','["customer_name","lock_minutes","support_phone"]','حساب {customer_name} برای {lock_minutes} دقیقه موقتاً مسدود شد.','حساب کاربر برای ۱۵ دقیقه موقتاً مسدود شد.',30,1,NOW()),
('customer_created','ایجاد حساب مشتری','اطلاع‌رسانی ایجاد حساب و دسترسی مشتری به سامانه.','customer','customers','service',1,0,'["customer"]','["in_app","sms","bale","telegram"]','["customer_name","portal_url","store_name"]','{customer_name} گرامی، حساب شما در {store_name} ایجاد شد.','مشتری گرامی، حساب شما در پرما پی ایجاد شد.',100,1,NOW()),
('profile_change_requested','درخواست تغییر مشخصات','اطلاع‌رسانی ثبت درخواست تغییر اطلاعات حساب.','customer','profile','security',1,0,'["customer","admin"]','["in_app","sms","telegram"]','["customer_name","request_id"]','درخواست تغییر مشخصات شما با شناسه {request_id} ثبت شد.','درخواست تغییر مشخصات شما ثبت شد.',110,1,NOW()),
('profile_change_approved','تأیید تغییر مشخصات','اطلاع‌رسانی تأیید تغییرات توسط مدیریت.','customer','profile','security',1,0,'["customer"]','["in_app","sms","telegram"]','["customer_name"]','{customer_name} گرامی، تغییر مشخصات شما تأیید شد.','تغییر مشخصات شما تأیید شد.',120,1,NOW()),
('profile_change_rejected','رد تغییر مشخصات','اطلاع‌رسانی رد درخواست تغییر مشخصات.','customer','profile','security',1,0,'["customer"]','["in_app","sms","telegram"]','["customer_name","support_phone"]','{customer_name} گرامی، درخواست تغییر مشخصات تأیید نشد.','درخواست تغییر مشخصات تأیید نشد.',130,1,NOW()),
('contract_created','ایجاد قرارداد جدید','اطلاع‌رسانی ثبت قرارداد جدید برای مشتری.','contract','contracts','financial',1,0,'["customer","operator"]','["in_app","sms","bale","telegram"]','["customer_name","contract_number","portal_url"]','قرارداد شماره {contract_number} برای {customer_name} ثبت شد.','قرارداد شماره ۱۴۰۵-۱۰۰ ثبت شد.',200,1,NOW()),
('contract_document_available','آماده شدن نسخه قرارداد','اطلاع‌رسانی آماده بودن نسخه قابل مشاهده قرارداد.','contract','contracts','confidential',1,0,'["customer"]','["in_app","sms","bale","telegram"]','["customer_name","contract_number","portal_url"]','نسخه قرارداد {contract_number} آماده مشاهده است: {portal_url}','نسخه قرارداد آماده مشاهده است.',210,1,NOW()),
('signature_requested','درخواست امضای قرارداد','دعوت مخاطب برای مشاهده و امضای نسخه مشخص قرارداد.','signature','sign','legal',1,1,'["signer"]','["in_app","sms","bale","telegram"]','["customer_name","contract_number","signature_url"]','{customer_name} گرامی، قرارداد {contract_number} آماده بررسی و امضا است: {signature_url}','کاربر گرامی، قرارداد شماره ۱۴۰۵-۱۰۰ آماده امضا است.',300,1,NOW()),
('signature_reminder','یادآوری امضای قرارداد','یادآوری درخواست امضایی که هنوز تکمیل نشده است.','signature','sign','legal',1,0,'["signer"]','["in_app","sms","bale","telegram"]','["customer_name","contract_number","signature_url"]','یادآوری: قرارداد {contract_number} در انتظار امضای شما است.','یادآوری: قرارداد شما در انتظار امضا است.',310,1,NOW()),
('signature_viewed','مشاهده قرارداد توسط امضاکننده','اطلاع‌رسانی مشاهده سند امضا توسط مخاطب.','signature','sign','confidential',0,0,'["operator"]','["in_app","telegram"]','["customer_name","contract_number"]','قرارداد {contract_number} توسط {customer_name} مشاهده شد.','قرارداد توسط امضاکننده مشاهده شد.',320,1,NOW()),
('signature_completed','تکمیل امضای قرارداد','اطلاع‌رسانی تکمیل فرآیند امضا.','signature','sign','legal',1,1,'["signer","operator"]','["in_app","sms","bale","telegram"]','["customer_name","contract_number","portal_url"]','امضای قرارداد {contract_number} تکمیل شد.','امضای قرارداد تکمیل شد.',330,1,NOW()),
('signature_rejected','رد امضای قرارداد','اطلاع‌رسانی رد یا عدم پذیرش سند توسط امضاکننده.','signature','sign','legal',1,1,'["signer","operator"]','["in_app","sms","telegram"]','["customer_name","contract_number"]','قرارداد {contract_number} توسط امضاکننده رد شد.','درخواست امضا رد شد.',340,1,NOW()),
('signature_expired','انقضای درخواست امضا','اطلاع‌رسانی منقضی شدن مهلت امضای سند.','signature','sign','legal',1,0,'["signer","operator"]','["in_app","sms","telegram"]','["customer_name","contract_number"]','مهلت امضای قرارداد {contract_number} پایان یافت.','مهلت امضای قرارداد پایان یافت.',350,1,NOW()),
('installment_upcoming','نزدیک شدن موعد قسط','یادآوری پیش از تاریخ سررسید قسط.','installment','installments','financial',1,0,'["customer"]','["in_app","sms","bale","telegram"]','["customer_name","installment_number","due_date","payable_amount"]','قسط {installment_number} به مبلغ {payable_amount} در تاریخ {due_date} سررسید می‌شود.','قسط شما به‌زودی سررسید می‌شود.',400,1,NOW()),
('installment_due_today','سررسید قسط در امروز','اطلاع‌رسانی رسیدن موعد پرداخت قسط.','installment','installments','financial',1,0,'["customer"]','["in_app","sms","bale","telegram"]','["installment_number","payable_amount","portal_url"]','امروز موعد پرداخت قسط {installment_number} به مبلغ {payable_amount} است.','امروز موعد پرداخت قسط شما است.',410,1,NOW()),
('installment_grace_ending','پایان مهلت تنفس قسط','هشدار نزدیک شدن پایان مهلت بدون جریمه.','installment','installments','financial',1,0,'["customer"]','["in_app","sms","bale","telegram"]','["installment_number","due_date"]','مهلت تنفس قسط {installment_number} در {due_date} پایان می‌یابد.','مهلت تنفس قسط شما رو به پایان است.',420,1,NOW()),
('installment_overdue','معوق شدن قسط','اطلاع‌رسانی عبور قسط از موعد پرداخت.','installment','installments','financial',1,1,'["customer","operator"]','["in_app","sms","bale","telegram"]','["installment_number","payable_amount","due_date"]','قسط {installment_number} از موعد پرداخت عبور کرده است.','قسط شما معوق شده است.',430,1,NOW()),
('pre_legal_warning','اخطار پیش از اقدام حقوقی','هشدار نهایی پیش از ارجاع یا تشکیل پرونده حقوقی.','legal','legal','legal',1,1,'["customer","legal"]','["in_app","sms","bale","telegram"]','["customer_name","contract_number","support_phone"]','اخطار: قرارداد {contract_number} در آستانه اقدام حقوقی است.','اخطار پیش از اقدام حقوقی.',500,1,NOW()),
('legal_case_created','تشکیل پرونده حقوقی','اطلاع‌رسانی ایجاد پرونده داخلی حقوقی.','legal','legal','legal',1,1,'["customer","legal"]','["in_app","sms","telegram"]','["customer_name","contract_number","case_number"]','پرونده حقوقی {case_number} برای قرارداد {contract_number} ایجاد شد.','پرونده حقوقی ایجاد شد.',510,1,NOW()),
('payment_completed','ثبت پرداخت موفق','اطلاع‌رسانی ثبت موفق پرداخت.','payment','payments','financial',1,1,'["customer"]','["in_app","sms","bale","telegram"]','["customer_name","payment_amount","contract_number"]','پرداخت {payment_amount} برای قرارداد {contract_number} با موفقیت ثبت شد.','پرداخت شما با موفقیت ثبت شد.',600,1,NOW()),
('partial_payment_completed','ثبت پرداخت جزئی','اطلاع‌رسانی پرداخت بخشی از بدهی یا اقساط.','payment','payments','financial',1,0,'["customer"]','["in_app","sms","bale","telegram"]','["payment_amount","payable_amount","contract_number"]','پرداخت جزئی {payment_amount} برای قرارداد {contract_number} ثبت شد.','پرداخت جزئی شما ثبت شد.',610,1,NOW()),
('contract_settled','تسویه کامل قرارداد','اطلاع‌رسانی تسویه کامل قرارداد.','payment','contracts','financial',1,1,'["customer","operator"]','["in_app","sms","bale","telegram"]','["customer_name","contract_number"]','قرارداد {contract_number} به‌طور کامل تسویه شد.','قرارداد شما تسویه کامل شد.',620,1,NOW()),
('file_available','آماده شدن فایل جدید','اطلاع‌رسانی آماده بودن فایل یا سند برای مشاهده.','file','files','confidential',1,0,'["customer"]','["in_app","sms","telegram"]','["customer_name","file_title","portal_url"]','فایل {file_title} برای مشاهده آماده است: {portal_url}','فایل جدیدی برای مشاهده آماده است.',700,1,NOW());

INSERT IGNORE INTO proma_connect_retention_policies
(policy_key,retention_days,can_delete,updated_by,updated_at)
VALUES
('delivery_success',180,1,NULL,NOW()),
('delivery_failed',365,1,NULL,NOW()),
('otp_audit',180,1,NULL,NOW()),
('webhook_events',90,1,NULL,NOW()),
('temporary_exports',7,1,NULL,NOW()),
('signature_evidence',NULL,0,NULL,NOW()),
('signature_hashes',NULL,0,NULL,NOW()),
('signed_artifacts',NULL,0,NULL,NOW());

