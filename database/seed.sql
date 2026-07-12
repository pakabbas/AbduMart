USE abdu_mart;

-- Default admin: admin@abdumart.com / Admin@123 (change after first login)
INSERT INTO users (email, password_hash, first_name, last_name, phone, role) VALUES
('admin@abdumart.com', '$2y$10$9UiXp..k59qj2h4wU1YQweYtFmCdRrmQL76HllboxB0VVlcQr4wuq', 'Abdu', 'Mart', '(248) 555-0100', 'admin');

INSERT INTO categories (name, sort_order, image_url) VALUES
('Fresh Produce', 1, 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=400'),
('Dairy & Eggs', 2, 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?w=400'),
('Bakery', 3, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400'),
('Beverages', 4, 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400'),
('Snacks', 5, 'https://images.unsplash.com/photo-1599490659213-e2b9527bd087?w=400'),
('Household', 6, 'https://images.unsplash.com/photo-1585421514738-01798e348ed3?w=400');

INSERT INTO products (category_id, name, description, price, inventory, image_url) VALUES
(1, 'Organic Bananas', 'Fresh organic bananas per lb', 0.69, 200, 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=400'),
(1, 'Roma Tomatoes', 'Vine-ripened Roma tomatoes per lb', 1.99, 150, 'https://images.unsplash.com/photo-1546094096-0df4bcaaa337?w=400'),
(1, 'Baby Spinach', 'Pre-washed baby spinach 10oz', 3.49, 80, 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=400'),
(2, 'Whole Milk', 'Vitamin D whole milk 1 gallon', 3.99, 60, 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=400'),
(2, 'Large Eggs', 'Farm fresh large eggs dozen', 4.29, 100, 'https://images.unsplash.com/photo-1582722878355-1c78189df202?w=400'),
(2, 'Sharp Cheddar', 'Wisconsin sharp cheddar 8oz', 5.49, 45, 'https://images.unsplash.com/photo-1618164436241-4473940d1f5c?w=400'),
(3, 'Sourdough Loaf', 'Artisan sourdough bread', 4.99, 30, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400'),
(3, 'Chocolate Muffins', 'Fresh baked chocolate chip muffins 4-pack', 5.99, 25, 'https://images.unsplash.com/photo-1607958996333-41aef7caefaa?w=400'),
(4, 'Sparkling Water', 'Lime sparkling water 12-pack', 6.99, 70, 'https://images.unsplash.com/photo-1523362628745-0c100150b504?w=400'),
(4, 'Cold Brew Coffee', 'Ready-to-drink cold brew 32oz', 5.49, 40, 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?w=400'),
(5, 'Potato Chips', 'Kettle cooked sea salt chips', 3.99, 120, 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=400'),
(5, 'Trail Mix', 'Michigan nut & berry trail mix 16oz', 7.99, 55, 'https://images.unsplash.com/photo-1599599810769-bcde5a160d32?w=400'),
(6, 'Paper Towels', '2-ply paper towels 6-roll pack', 8.99, 90, 'https://images.unsplash.com/photo-1585421514738-01798e348ed3?w=400'),
(6, 'Dish Soap', 'Lemon fresh dish soap 24oz', 3.49, 75, 'https://images.unsplash.com/photo-1610557892470-55d9e80f2ce8?w=400');
