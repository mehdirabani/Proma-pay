-- Device credentials are visible only to a technician assigned to the repair.
-- Every reveal remains audited by RepairController.
INSERT INTO system_plugin_role_permissions
  (plugin_id, permission_key, role, is_allowed, updated_at)
VALUES
  ('proma-repair', 'plugin.proma-repair.view_device_password', 'repair_technician', 1, NOW())
ON DUPLICATE KEY UPDATE
  is_allowed = 1,
  updated_at = NOW();
