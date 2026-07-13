-- Clover-synced items often have inventory=0 when stock is not tracked in Clover.
-- Set a default so they appear on the storefront and can be ordered online.
UPDATE products
SET inventory = 999
WHERE clover_id IS NOT NULL
  AND inventory = 0
  AND is_active = 1;
