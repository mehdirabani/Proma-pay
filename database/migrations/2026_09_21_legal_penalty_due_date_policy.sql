INSERT INTO settings (`key`, `value`, is_secret)
VALUES (
  'contract_legal_penalty_clause',
  'اینجانب امانت‌دار اعلام می‌کنم بند جریمه دیرکرد عادی و جریمه دیرکرد مرحله حقوقی را مطالعه کرده و می‌پذیرم. تا پیش از ثبت ارجاع رسمی و قابل‌پیگیری، جریمه دیرکرد با نرخ عادی ماهانه محاسبه می‌شود؛ پس از ثبت ارجاع رسمی، نرخ حقوقی از تاریخ سررسید هر قسط معوق محاسبه خواهد شد.',
  0
)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), is_secret = VALUES(is_secret);
