USE abdu_mart;

-- Default admin: admin@abdumart.com / Admin@123 (change after first login)
INSERT INTO users (email, password_hash, first_name, last_name, phone, email_verified_at, role) VALUES
('admin@abdumart.com', '$2y$10$9UiXp..k59qj2h4wU1YQweYtFmCdRrmQL76HllboxB0VVlcQr4wuq', 'Abdu', 'Mart', '(248) 555-0100', NOW(), 'admin');

INSERT INTO categories (name, sort_order, image_url) VALUES
('Fresh Produce', 1, NULL),
('Dairy & Eggs', 2, NULL),
('Bakery', 3, NULL),
('Beverages', 4, NULL),
('Snacks', 5, NULL),
('Household', 6, NULL);

INSERT INTO products (category_id, name, description, price, inventory, image_url) VALUES
(1, 'Organic Bananas', 'Fresh organic bananas per lb', 0.69, 200, NULL),
(1, 'Roma Tomatoes', 'Vine-ripened Roma tomatoes per lb', 1.99, 150, NULL),
(1, 'Baby Spinach', 'Pre-washed baby spinach 10oz', 3.49, 80, NULL),
(2, 'Whole Milk', 'Vitamin D whole milk 1 gallon', 3.99, 60, NULL),
(2, 'Large Eggs', 'Farm fresh large eggs dozen', 4.29, 100, NULL),
(2, 'Sharp Cheddar', 'Wisconsin sharp cheddar 8oz', 5.49, 45, NULL),
(3, 'Sourdough Loaf', 'Artisan sourdough bread', 4.99, 30, NULL),
(3, 'Chocolate Muffins', 'Fresh baked chocolate chip muffins 4-pack', 5.99, 25, NULL),
(4, 'Sparkling Water', 'Lime sparkling water 12-pack', 6.99, 70, NULL),
(4, 'Cold Brew Coffee', 'Ready-to-drink cold brew 32oz', 5.49, 40, NULL),
(5, 'Potato Chips', 'Kettle cooked sea salt chips', 3.99, 120, NULL),
(5, 'Trail Mix', 'Michigan nut & berry trail mix 16oz', 7.99, 55, NULL),
(6, 'Paper Towels', '2-ply paper towels 6-roll pack', 8.99, 90, NULL),
(6, 'Dish Soap', 'Lemon fresh dish soap 24oz', 3.49, 75, NULL);
