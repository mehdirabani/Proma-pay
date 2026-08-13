CREATE TABLE IF NOT EXISTS proma_connect_template_variables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  variable_key VARCHAR(80) NOT NULL,
  title_fa VARCHAR(190) NOT NULL,
  description_fa VARCHAR(500) NULL,
  value_type VARCHAR(30) NOT NULL DEFAULT 'string',
  source_resolver VARCHAR(190) NOT NULL,
  sample_value VARCHAR(500) NULL,
  sensitivity VARCHAR(20) NOT NULL DEFAULT 'normal',
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  archived_at DATETIME NULL,
  UNIQUE KEY uq_connect_variable_key (variable_key),
  KEY idx_connect_variable_state (is_active,sensitivity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_variable_provider_mappings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  variable_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(40) NOT NULL,
  provider_parameter VARCHAR(120) NOT NULL,
  wrapper_prefix VARCHAR(20) NULL,
  wrapper_suffix VARCHAR(20) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_connect_variable_provider (variable_id,provider_key),
  KEY idx_connect_provider_parameter (provider_key,provider_parameter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_broadcast_campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(36) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message_body TEXT NOT NULL,
  channels_json LONGTEXT NOT NULL,
  audience_json LONGTEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
  queued_count INT UNSIGNED NOT NULL DEFAULT 0,
  sent_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_connect_broadcast_public (public_id),
  KEY idx_connect_broadcast_status (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proma_connect_broadcast_dispatches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  recipient_key CHAR(64) NOT NULL,
  channel VARCHAR(30) NOT NULL,
  delivery_id BIGINT UNSIGNED NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_connect_broadcast_delivery (campaign_id,recipient_key,channel),
  KEY idx_connect_broadcast_dispatch (campaign_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO proma_connect_template_variables
(variable_key,title_fa,description_fa,value_type,source_resolver,sample_value,sensitivity,is_system,is_active,created_at)
VALUES
('customer_name','نام مشتری','نام نمایشی مشتری','string','customer.full_name','مشتری گرامی','personal',1,1,NOW()),
('contract_number','شماره قرارداد','شماره عمومی قرارداد','string','contract.number','۱۴۰۵-۱۰۰','normal',1,1,NOW()),
('installment_number','شماره قسط','ترتیب قسط در قرارداد','integer','installment.sequence','۳','normal',1,1,NOW()),
('due_date','تاریخ سررسید','تاریخ سررسید شمسی','date','installment.due_date','۱۴۰۵/۰۶/۱۵','normal',1,1,NOW()),
('payable_amount','مبلغ قابل پرداخت','مانده قابل پرداخت','money','installment.payable_amount','۱۲٬۰۰۰٬۰۰۰ ریال','financial',1,1,NOW()),
('payment_amount','مبلغ پرداخت','مبلغ تراکنش ثبت‌شده','money','payment.amount','۵٬۰۰۰٬۰۰۰ ریال','financial',1,1,NOW()),
('signature_url','پیوند امضا','پیوند یک‌بارمصرف مشاهده و امضا','url','signature.public_url','https://example.invalid/sign/…','secret',1,1,NOW()),
('portal_url','پیوند سامانه','نشانی پنل کاربر','url','application.portal_url','https://example.invalid/portal','normal',1,1,NOW()),
('otp','کد یکبارمصرف','کد موقت؛ هرگز در گزارش ذخیره نمی‌شود','otp','otp.code','******','secret',1,1,NOW()),
('expiry_minutes','دقایق اعتبار','زمان باقی‌مانده اعتبار کد','integer','otp.expiry_minutes','۳','normal',1,1,NOW());

INSERT IGNORE INTO proma_connect_variable_provider_mappings
(variable_id,provider_key,provider_parameter,wrapper_prefix,wrapper_suffix,is_active,updated_at)
SELECT id,'ippanel',variable_key,'%', '%',1,NOW() FROM proma_connect_template_variables;
INSERT IGNORE INTO proma_connect_variable_provider_mappings
(variable_id,provider_key,provider_parameter,wrapper_prefix,wrapper_suffix,is_active,updated_at)
SELECT id,'smsir',variable_key,NULL,NULL,1,NOW() FROM proma_connect_template_variables;
INSERT IGNORE INTO proma_connect_variable_provider_mappings
(variable_id,provider_key,provider_parameter,wrapper_prefix,wrapper_suffix,is_active,updated_at)
SELECT id,'bale',variable_key,'#','#',1,NOW() FROM proma_connect_template_variables;
INSERT IGNORE INTO proma_connect_variable_provider_mappings
(variable_id,provider_key,provider_parameter,wrapper_prefix,wrapper_suffix,is_active,updated_at)
SELECT id,'telegram',variable_key,'{','}',1,NOW() FROM proma_connect_template_variables;
