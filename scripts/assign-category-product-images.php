<?php

declare(strict_types=1);

/**
 * CLI: assign name-relevant images to products in a category.
 *
 * Usage: php scripts/assign-category-product-images.php "Baby & Kids"
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

$categoryName = $argv[1] ?? 'Baby & Kids';
$updated = reassign_category_product_images($categoryName);
echo "Updated {$updated} product(s) in \"{$categoryName}\".\n";

$stmt = db()->prepare(
    'SELECT p.id, p.name, p.image_url FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE c.name = ?
     ORDER BY p.name'
);
$stmt->execute([$categoryName]);
foreach ($stmt->fetchAll() as $row) {
    echo sprintf("%d\t%s\t%s\n", (int) $row['id'], $row['name'], (string) ($row['image_url'] ?? ''));
}
