-- Track admin new-order emails to avoid duplicate sends (webhook + success page).
ALTER TABLE orders
    ADD COLUMN admin_new_order_notified_at TIMESTAMP NULL DEFAULT NULL AFTER confirmation_email_sent_at;
