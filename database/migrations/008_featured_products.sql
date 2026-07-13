USE abdu_mart;

ALTER TABLE products
    ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

CREATE INDEX idx_products_featured ON products(is_featured);
