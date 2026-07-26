-- Delivery fulfillment support
-- Pickup keeps existing statuses; delivery adds processing / out_for_delivery / delivered / returned.

ALTER TABLE orders
    ADD COLUMN fulfillment_type ENUM('pickup', 'delivery') NOT NULL DEFAULT 'pickup' AFTER status,
    ADD COLUMN delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER tax,
    ADD COLUMN delivery_address_line1 VARCHAR(255) NULL DEFAULT NULL AFTER vehicle_description,
    ADD COLUMN delivery_address_line2 VARCHAR(255) NULL DEFAULT NULL AFTER delivery_address_line1,
    ADD COLUMN delivery_city VARCHAR(100) NULL DEFAULT NULL AFTER delivery_address_line2,
    ADD COLUMN delivery_state VARCHAR(50) NULL DEFAULT NULL AFTER delivery_city,
    ADD COLUMN delivery_zip VARCHAR(20) NULL DEFAULT NULL AFTER delivery_state;

ALTER TABLE orders
    MODIFY COLUMN status ENUM(
        'pending',
        'paid',
        'preparing',
        'ready',
        'picked_up',
        'cancelled',
        'processing',
        'out_for_delivery',
        'delivered',
        'returned'
    ) NOT NULL DEFAULT 'pending';

CREATE INDEX idx_orders_fulfillment ON orders (fulfillment_type);
CREATE INDEX idx_orders_fulfillment_status ON orders (fulfillment_type, status);

INSERT INTO settings (setting_key, setting_value)
VALUES
    ('delivery_fee', '5.00'),
    ('delivery_min_order', '25.00')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
