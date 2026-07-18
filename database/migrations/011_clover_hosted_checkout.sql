USE abdu_mart;

ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM('stripe','arrival','clover') NOT NULL DEFAULT 'stripe',
    ADD COLUMN clover_checkout_session_id VARCHAR(64) DEFAULT NULL AFTER payment_method,
    ADD COLUMN clover_payment_id VARCHAR(64) DEFAULT NULL AFTER clover_checkout_session_id;

CREATE INDEX idx_orders_clover_session ON orders (clover_checkout_session_id);
