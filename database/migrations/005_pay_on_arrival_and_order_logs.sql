USE abdu_mart;

ALTER TABLE orders
    ADD COLUMN payment_method ENUM('stripe','arrival') NOT NULL DEFAULT 'stripe' AFTER stripe_payment_intent,
    ADD COLUMN picked_up_at TIMESTAMP NULL DEFAULT NULL AFTER customer_here_at,
    ADD COLUMN picked_up_by INT UNSIGNED NULL DEFAULT NULL AFTER picked_up_at,
    ADD CONSTRAINT fk_orders_picked_up_by FOREIGN KEY (picked_up_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS order_status_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(32) NULL,
    new_status VARCHAR(32) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_logs_order (order_id),
    INDEX idx_order_logs_created (created_at),
    CONSTRAINT fk_order_logs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

