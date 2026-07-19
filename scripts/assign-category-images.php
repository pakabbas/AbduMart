<?php

declare(strict_types=1);

/**
 * CLI: assign name-relevant Unsplash photos to every category.
 *
 * Usage: php scripts/assign-category-images.php
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI only.\n");
    exit(1);
}

$updated = reassign_all_category_images();
echo "Updated {$updated} categor" . ($updated === 1 ? 'y' : 'ies') . " with relevant images.\n";

foreach (get_categories(false) as $c) {
    echo sprintf(
        "%d\t%s\t%s\n",
        (int) $c['id'],
        $c['name'],
        (string) ($c['image_url'] ?? '')
    );
}
