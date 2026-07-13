<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'name';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 24;

$filters = [
    'category_id' => $categoryId ?: null,
    'search' => $search ?: null,
    'sort' => $sort,
    'page' => $page,
    'per_page' => $perPage,
];

$categories = get_categories();
$totalProducts = count_products($filters);
$products = get_products($filters);
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $filters['page'] = $page;
    $products = get_products($filters);
}

$queryParams = array_filter([
    'category' => $categoryId ?: null,
    'q' => $search !== '' ? $search : null,
    'sort' => $sort !== 'name' ? $sort : null,
], fn ($value) => $value !== null && $value !== '');

$categoryName = 'All Products';
if ($categoryId) {
    foreach ($categories as $cat) {
        if ((int) $cat['id'] === $categoryId) {
            $categoryName = $cat['name'];
            break;
        }
    }
}

$pageTitle = $categoryName;
require __DIR__ . '/includes/header.php';
?>

<section class="container py-4 shop-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <a href="index.php" class="small text-danger text-decoration-none d-inline-flex align-items-center gap-1 mb-2">
                <i class="bi bi-arrow-left"></i> Back to home
            </a>
            <h1 class="section-title mb-0"><?= e($categoryName) ?></h1>
        </div>
        <span class="text-muted">
            <?= $totalProducts ?> items
            <?php if ($totalPages > 1): ?>
            · Page <?= $page ?> of <?= $totalPages ?>
            <?php endif; ?>
        </span>
    </div>

    <div class="filter-bar card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="search" name="q" class="form-control" placeholder="Search products..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Sort by</label>
                    <select name="sort" class="form-select">
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <?php if (products_have_featured_column()): ?>
                        <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured first</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-danger w-100">Filter</button>
                </div>
                <?php if ($categoryId || $search !== '' || $sort !== 'name'): ?>
                <div class="col-12">
                    <a href="shop.php" class="small text-danger text-decoration-none">Clear all filters</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (empty($products)): ?>
    <div class="empty-state text-center py-5">
        <i class="bi bi-basket display-4 text-danger"></i>
        <p class="mt-3 text-muted">No products match your filters.</p>
        <a href="shop.php" class="btn btn-outline-danger">Clear filters</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($products as $product): ?>
        <?php require __DIR__ . '/includes/product_card.php'; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="shop-pagination mt-4" aria-label="Product pages">
        <ul class="pagination justify-content-center mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(shop_page_url($page - 1, $queryParams)) ?>">Previous</a>
            </li>
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(shop_page_url($i, $queryParams)) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(shop_page_url($page + 1, $queryParams)) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
