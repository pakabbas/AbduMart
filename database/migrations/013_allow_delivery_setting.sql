-- Ensure delivery toggle setting exists (default enabled)
INSERT INTO settings (setting_key, setting_value)
VALUES ('allow_delivery', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
