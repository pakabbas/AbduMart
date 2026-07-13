USE abdu_mart;

UPDATE categories
SET image_url = CASE
    WHEN LOWER(name) LIKE '%produce%' THEN 'https://picsum.photos/seed/abdu-produce/900/600'
    WHEN LOWER(name) LIKE '%dairy%' OR LOWER(name) LIKE '%egg%' THEN 'https://picsum.photos/seed/abdu-dairy-eggs/900/600'
    WHEN LOWER(name) LIKE '%bakery%' OR LOWER(name) LIKE '%bread%' THEN 'https://picsum.photos/seed/abdu-bakery/900/600'
    WHEN LOWER(name) LIKE '%beverage%' OR LOWER(name) LIKE '%drink%' THEN 'https://picsum.photos/seed/abdu-beverages/900/600'
    WHEN LOWER(name) LIKE '%snack%' OR LOWER(name) LIKE '%chips%' THEN 'https://picsum.photos/seed/abdu-snacks/900/600'
    WHEN LOWER(name) LIKE '%household%' OR LOWER(name) LIKE '%clean%' THEN 'https://picsum.photos/seed/abdu-household/900/600'
    ELSE CONCAT('https://picsum.photos/seed/abdu-cat-', id, '/900/600')
END
WHERE (image_url IS NULL OR image_url = '');

