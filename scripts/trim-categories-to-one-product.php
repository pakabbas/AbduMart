<?php

declare(strict_types=1);

/**
 * CLI: keep one product per listed category, delete the rest, assign images.
 *
 * Usage: php scripts/trim-categories-to-one-product.php
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

/** @var array<string, int> category name => product id to keep */
$keep = [
    'Fresh Produce' => 1729,          // Bananas
    'Dairy & Eggs' => 1723,           // Large Eggs
    'Snacks & Beverages' => 1719,     // Potato Chips
    'Pantry Staples' => 1714,         // White Rice
    'Meat & Seafood' => 1765,         // Chicken Breast
    'Frozen Foods' => 1760,           // Vanilla Ice Cream
    'Bakery' => 1759,                 // Sourdough Bread Loaf
    'Household Essentials' => 1754,   // Laundry Detergent
];

$pdo = db();
$deletedTotal = 0;
$assignedTotal = 0;

foreach ($keep as $categoryName => $keepId) {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.name FROM products p
         INNER JOIN categories c ON c.id = p.category_id
         WHERE c.name = ?
         ORDER BY p.id'
    );
    $stmt->execute([$categoryName]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($products === []) {
        echo "SKIP {$categoryName}: no products\n";
        continue;
    }

    $ids = array_map(static fn (array $p): int => (int) $p['id'], $products);
    if (!in_array($keepId, $ids, true)) {
        fwrite(STDERR, "ERROR {$categoryName}: keep id {$keepId} not found\n");
        exit(1);
    }

    $toDelete = array_values(array_filter($ids, static fn (int $id): bool => $id !== $keepId));
    if ($toDelete !== []) {
        $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
        $del = $pdo->prepare("DELETE FROM products WHERE id IN ({$placeholders})");
        $del->execute($toDelete);
        $deletedTotal += $del->rowCount();
        echo "{$categoryName}: deleted " . implode(', ', $toDelete) . "\n";
    } else {
        echo "{$categoryName}: already one product\n";
    }

    $nameStmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
    $nameStmt->execute([$keepId]);
    $name = (string) $nameStmt->fetchColumn();
    $url = assign_product_food_image($keepId, $name);
    $pdo->prepare('UPDATE products SET image_url = ? WHERE id = ?')->execute([$url, $keepId]);
    $assignedTotal++;
    echo "{$categoryName}: kept {$keepId} {$name} => {$url}\n";
}

echo "Done. Deleted {$deletedTotal} product(s), assigned images to {$assignedTotal}.\n";
