-- Internal admin notes on orders (cancellation reason, issues, etc.)
ALTER TABLE orders
    ADD COLUMN admin_notes TEXT NULL DEFAULT NULL AFTER pickup_notes;
