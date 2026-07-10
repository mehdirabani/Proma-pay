UPDATE users
SET username = national_id, updated_at = NOW()
WHERE role = 'customer'
  AND (username IS NULL OR username = '')
  AND national_id IS NOT NULL
  AND national_id != '';
