<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (isset($_GET['signed_out'])) {
    flash('success', 'You have been signed out.');
    redirect('index.php');
}

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

function shop_page_url(int $pageNum, array $queryParams): string
{
    $params = $queryParams;
    if ($pageNum > 1) {
        $params['page'] = $pageNum;
    }
    $query = http_build_query($params);
    return 'index.php' . ($query !== '' ? '?' . $query : '');
}

$pageTitle = 'Shop Fresh Groceries';
require __DIR__ . '/includes/header.php';
?>

<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="hero-badge">Canton, MI · Curbside Pickup</span>
                <h1 class="hero-title">Abdu Market Curb Side Pickup</h1>
                <p class="hero-lead">Browse our aisles online, pay securely with Stripe, and we'll bring your order to your car when you tap <strong>I'm Here</strong>.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#products" class="btn btn-danger btn-lg">Start Shopping</a>
                    <?php if (!is_logged_in()): ?>
                    <a href="register.php" class="btn btn-outline-danger btn-lg">Create Account</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <div class="hero-card">
                    <i class="bi bi-shop-window"></i>
                    <h3>How it works</h3>
                    <ol class="mb-0">
                        <li>Shop categories & add to cart</li>
                        <li>Checkout & pay online</li>
                        <li>Drive to Abdu Market</li>
                        <li>Tap <em>I'm Here</em> — we come to you</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-4" id="products">
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
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-danger w-100">Filter</button>
                </div>
                <?php if ($categoryId || $search !== '' || $sort !== 'name'): ?>
                <div class="col-12">
                    <a href="index.php" class="small text-danger text-decoration-none">Clear all filters</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!$categoryId && $search === ''): ?>
    <div class="mb-5">
        <h2 class="section-title">Shop by Category</h2>
        <div class="row g-3">
            <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="?category=<?= (int) $cat['id'] ?>" class="category-card">
                    <div class="category-img">
                        <img src="<?= e(catalog_image_url($cat['image_url'] ?? null, 'category')) ?>"
                             alt="<?= e($cat['name']) ?>"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='<?= e(asset_url('assets/images/placeholder-category.svg')) ?>';">
                    </div>
                    <span><?= e($cat['name']) ?></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">
            <?= $categoryId ? e(array_values(array_filter($categories, fn($c) => (int)$c['id'] === $categoryId))[0]['name'] ?? 'Products') : 'All Products' ?>
        </h2>
        <span class="text-muted">
            <?= $totalProducts ?> items
            <?php if ($totalPages > 1): ?>
            · Page <?= $page ?> of <?= $totalPages ?>
            <?php endif; ?>
        </span>
    </div>

    <?php if (empty($products)): ?>
    <div class="empty-state text-center py-5">
        <i class="bi bi-basket display-4 text-danger"></i>
        <p class="mt-3 text-muted">No products match your filters.</p>
        <a href="index.php" class="btn btn-outline-danger">Clear filters</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($products as $product): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <article class="product-card card h-100 border-0 shadow-sm">
                <div class="product-img">
                    <img src="<?= e(catalog_image_url($product['image_url'] ?? null)) ?>"
                         alt="<?= e($product['name']) ?>"
                         loading="lazy"
                         onerror="this.onerror=null;this.src='<?= e(asset_url('assets/images/placeholder-product.svg')) ?>';">
                    <?php if ((int) $product['inventory'] > 0 && (int) $product['inventory'] <= 5): ?>
                    <span class="stock-badge">Only <?= (int) $product['inventory'] ?> left</span>
                    <?php elseif ((int) $product['inventory'] < 1): ?>
                    <span class="stock-badge stock-out">Out of stock</span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <span class="product-category"><?= e($product['category_name'] ?? 'General') ?></span>
                    <h3 class="product-name"><?= e($product['name']) ?></h3>
                    <p class="product-desc"><?= e(mb_strimwidth($product['description'] ?? '', 0, 80, '...')) ?></p>
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <strong class="product-price"><?= format_money($product['price']) ?></strong>
                        <?php if (is_logged_in()): ?>
                        <?php if (product_is_purchasable($product)): ?>
                        <form method="post" action="api/cart.php" class="add-to-cart-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Unavailable</button>
                        <?php endif; ?>
                        <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-outline-danger">Sign in to buy</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>
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
