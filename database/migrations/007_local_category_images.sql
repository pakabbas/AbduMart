USE abdu_mart;

UPDATE categories
SET image_url = CASE
    WHEN LOWER(name) LIKE '%produce%' THEN '/assets/images/categories/produce.svg'
    WHEN LOWER(name) LIKE '%dairy%' OR LOWER(name) LIKE '%egg%' THEN '/assets/images/categories/dairy-eggs.svg'
    WHEN LOWER(name) LIKE '%bakery%' OR LOWER(name) LIKE '%bread%' THEN '/assets/images/categories/bakery.svg'
    WHEN LOWER(name) LIKE '%beverage%' OR LOWER(name) LIKE '%drink%' THEN '/assets/images/categories/beverages.svg'
    WHEN LOWER(name) LIKE '%snack%' OR LOWER(name) LIKE '%chips%' THEN '/assets/images/categories/snacks.svg'
    WHEN LOWER(name) LIKE '%household%' OR LOWER(name) LIKE '%clean%' THEN '/assets/images/categories/household.svg'
    ELSE '/assets/images/categories/default.svg'
END
WHERE image_url IS NULL
   OR image_url = ''
   OR image_url LIKE '%picsum.photos%'
   OR image_url LIKE '%unsplash.com%';
