<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'name';
$page = max(1, (int) ($_GET['page'] ?? 1));
$isSearch = $search !== '';

$categories = get_categories();
$menuCategories = array_values(array_filter(
    $categories,
    static fn (array $cat): bool => (int) ($cat['product_count'] ?? 0) > 0
));

if ($isSearch) {
    $filters = [
        'search' => $search,
        'sort' => $sort,
        'page' => $page,
        'per_page' => 30,
    ];
    $totalProducts = count_products($filters);
    $products = get_products($filters);
    $menuGroups = [];
} else {
    $filters = ['sort' => $sort];
    $products = get_products($filters);
    $totalProducts = count($products);
    $menuGroups = group_products_for_menu($products, $menuCategories);
}

$totalPages = $isSearch ? max(1, (int) ceil($totalProducts / 30)) : 1;
if ($isSearch && $page > $totalPages) {
    $page = $totalPages;
    $filters['page'] = $page;
    $products = get_products($filters);
}

$queryParams = array_filter([
    'q' => $search !== '' ? $search : null,
    'sort' => $sort !== 'name' ? $sort : null,
], fn ($value) => $value !== null && $value !== '');

$activeCategoryAnchor = null;
if ($categoryId) {
    foreach ($menuCategories as $cat) {
        if ((int) $cat['id'] === $categoryId) {
            $activeCategoryAnchor = category_menu_anchor($cat);
            break;
        }
    }
}

$pageTitle = $isSearch ? ('Search: ' . $search) : 'Menu';
$bodyClass = 'shop-app-page';
require __DIR__ . '/includes/header.php';
?>

<div class="shop-app" id="shopApp" data-active-category="<?= e($activeCategoryAnchor ?? '') ?>">
    <div class="shop-app-hero">
        <div class="container">
            <div class="shop-app-hero-card">
                <div class="shop-app-hero-copy">
                    <a href="index.php" class="shop-app-back"><i class="bi bi-arrow-left"></i> Home</a>
                    <h1 class="shop-app-title">Abdu Market Menu</h1>
                    <p class="shop-app-subtitle">
                        <i class="bi bi-geo-alt-fill"></i>
                        Curbside pickup · <?= e(setting('mart.address', config('mart.address'))) ?>
                    </p>
                    <div class="shop-app-meta">
                        <span><i class="bi bi-bag-check"></i> <?= (int) $totalProducts ?> items</span>
                        <span><i class="bi bi-clock"></i> Order for pickup</span>
                    </div>
                </div>
            </div>

            <form method="get" class="shop-app-search" role="search">
                <?php if ($sort !== 'name'): ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <?php endif; ?>
                <i class="bi bi-search shop-app-search-icon" aria-hidden="true"></i>
                <input
                    type="search"
                    name="q"
                    class="shop-app-search-input"
                    placeholder="Search dishes, drinks, snacks..."
                    value="<?= e($search) ?>"
                    autocomplete="off"
                >
                <?php if ($search !== ''): ?>
                <a href="shop.php<?= $sort !== 'name' ? '?sort=' . rawurlencode($sort) : '' ?>" class="shop-app-search-clear" aria-label="Clear search">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </form>

            <div class="shop-app-toolbar">
                <div class="shop-app-sort" role="group" aria-label="Sort products">
                    <?php
                    $sortOptions = [
                        'name' => 'A–Z',
                        'featured' => 'Popular',
                        'price_asc' => 'Price ↑',
                        'price_desc' => 'Price ↓',
                    ];
                    if (!products_have_featured_column()) {
                        unset($sortOptions['featured']);
                    }
                    foreach ($sortOptions as $value => $label):
                        $sortParams = array_filter([
                            'q' => $search !== '' ? $search : null,
                            'sort' => $value !== 'name' ? $value : null,
                        ]);
                        $sortUrl = 'shop.php' . ($sortParams ? '?' . http_build_query($sortParams) : '');
                    ?>
                    <a href="<?= e($sortUrl) ?>" class="shop-app-sort-chip<?= $sort === $value ? ' is-active' : '' ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isSearch && !empty($menuGroups)): ?>
    <div class="shop-category-carousel-wrap" id="shopCategoryCarouselWrap">
        <button type="button" class="shop-category-carousel-btn shop-category-carousel-prev" id="shopCategoryPrev" aria-label="Previous categories">
            <i class="bi bi-chevron-left"></i>
        </button>
        <nav class="shop-category-nav" id="shopCategoryNav" aria-label="Menu categories">
            <div class="shop-category-nav-track" id="shopCategoryTrack">
                <a href="shop.php<?= $sort !== 'name' ? '?sort=' . rawurlencode($sort) : '' ?>" class="shop-cat-chip<?= !$categoryId ? ' is-active' : '' ?>" data-target="shopMenuTop">
                    <span class="shop-cat-chip-icon all"><i class="bi bi-grid-fill"></i></span>
                    <span class="shop-cat-chip-label">All</span>
                </a>
                <?php foreach ($menuGroups as $group): ?>
                <?php $anchor = category_menu_anchor($group['category']); ?>
                <a
                    href="#<?= e($anchor) ?>"
                    class="shop-cat-chip<?= $activeCategoryAnchor === $anchor ? ' is-active' : '' ?>"
                    data-target="<?= e($anchor) ?>"
                >
                    <span class="shop-cat-chip-icon catalog-tile-media<?= catalog_has_image($group['category']['image_url'] ?? null) ? '' : ' show-initials' ?>">
                        <?= catalog_tile_media((string) $group['category']['name'], $group['category']['image_url'] ?? null) ?>
                    </span>
                    <span class="shop-cat-chip-label"><?= e($group['category']['name']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </nav>
        <button type="button" class="shop-category-carousel-btn shop-category-carousel-next" id="shopCategoryNext" aria-label="Next categories">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="container shop-app-content" id="shopMenuTop">
        <?php $productColClass = 'col-6 col-lg-3'; ?>
        <?php if ($isSearch): ?>
        <div class="shop-search-header">
            <h2 class="shop-section-title">Results for “<?= e($search) ?>”</h2>
            <p class="shop-section-count"><?= (int) $totalProducts ?> items found</p>
        </div>
        <?php if (empty($products)): ?>
        <div class="shop-empty">
            <i class="bi bi-search"></i>
            <h3>No matches found</h3>
            <p>Try another search or browse the full menu.</p>
            <a href="shop.php" class="btn btn-danger btn-sm">Browse menu</a>
        </div>
        <?php else: ?>
        <div class="row g-3 shop-product-grid">
            <?php foreach ($products as $product): ?>
            <?php require __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav class="shop-pagination mt-4" aria-label="Search result pages">
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

        <?php elseif (empty($menuGroups)): ?>
        <div class="shop-empty">
            <i class="bi bi-basket"></i>
            <h3>Menu is empty</h3>
            <p>Check back soon for new items.</p>
            <a href="index.php" class="btn btn-danger btn-sm">Back to home</a>
        </div>
        <?php else: ?>
        <?php foreach ($menuGroups as $group): ?>
        <?php $anchor = category_menu_anchor($group['category']); ?>
        <section class="shop-menu-section" id="<?= e($anchor) ?>" data-category-section="<?= e($anchor) ?>">
            <div class="shop-menu-section-head">
                <h2 class="shop-section-title"><?= e($group['category']['name']) ?></h2>
                <span class="shop-section-count"><?= count($group['products']) ?> items</span>
            </div>
            <div class="row g-3 shop-product-grid">
                <?php foreach ($group['products'] as $product): ?>
                <?php require __DIR__ . '/includes/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="<?= e(asset_url('assets/js/shop.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/js/shop.js') ?>"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
