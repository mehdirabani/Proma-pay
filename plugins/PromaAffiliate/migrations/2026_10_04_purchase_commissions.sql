ALTER TABLE proma_affiliate_referrals
    MODIFY COLUMN status ENUM('pending','qualified','rejected') NOT NULL DEFAULT 'pending';

INSERT IGNORE INTO proma_affiliate_settings (setting_key, setting_value)
VALUES ('reward_trigger', 'first_purchase');
