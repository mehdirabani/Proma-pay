UPDATE chat_channels
SET title = COALESCE(NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'system_name' LIMIT 1), ''), title)
WHERE slug = 'public-announcements';

UPDATE users
SET full_name = COALESCE(NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'system_name' LIMIT 1), ''), full_name),
    updated_at = NOW()
WHERE username = 'proma_notice_bot';
