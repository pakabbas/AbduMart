-- Remove broken Unsplash demo images (404). Tiles will show name initials instead.
UPDATE categories SET image_url = NULL WHERE image_url LIKE '%unsplash.com%';
UPDATE products SET image_url = NULL WHERE image_url LIKE '%unsplash.com%';
