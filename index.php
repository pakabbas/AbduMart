<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (isset($_GET['signed_out'])) {
    flash('success', 'You have been signed out.');
    redirect('index.php');
}

// Legacy product filter URLs on the homepage go to the dedicated shop page.
if (isset($_GET['category']) || isset($_GET['q']) || isset($_GET['page']) || (isset($_GET['sort']) && $_GET['sort'] !== 'name')) {
    $params = array_filter([
        'category' => isset($_GET['category']) ? (int) $_GET['category'] : null,
        'q' => trim($_GET['q'] ?? '') !== '' ? trim($_GET['q']) : null,
        'sort' => ($_GET['sort'] ?? 'name') !== 'name' ? ($_GET['sort'] ?? null) : null,
        'page' => isset($_GET['page']) ? max(1, (int) $_GET['page']) : null,
    ], fn ($value) => $value !== null && $value !== '');
    redirect('shop.php' . ($params ? '?' . http_build_query($params) : ''));
}

$categories = get_categories();
$featuredProducts = get_featured_products(12);
$homeProductLimit = 8;
$homeProducts = get_products(['per_page' => $homeProductLimit, 'sort' => 'name']);
$totalProducts = count_products([]);

$pageTitle = 'Shop Fresh Groceries';
require __DIR__ . '/includes/header.php';
?>

<section class="hero-section">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <span class="hero-badge"><i class="bi bi-geo-alt-fill"></i> Canton, MI · Curbside Pickup</span>
                <h1 class="hero-title">Order Online.<br>Pick Up Curbside.</h1>
                <p class="hero-lead">Shop Abdu Market from your phone, pay securely, and we'll bring your order to your car when you tap <strong>I'm Here</strong>.</p>
                <div class="d-flex flex-wrap gap-2 hero-cta">
                    <a href="shop.php" class="btn btn-light btn-lg hero-btn-primary">Start Shopping</a>
                    <?php if (!is_logged_in()): ?>
                    <a href="register.php" class="btn btn-outline-light btn-lg">Create Account</a>
                    <?php endif; ?>
                </div>
                <p class="hero-location mb-0">
                    <i class="bi bi-shop"></i>
                    <?= e(setting('mart.address', config('mart.address'))) ?>
                </p>
            </div>
            <div class="col-lg-6">
                <div class="hero-card">
                    <h3 class="hero-card-title">How it works</h3>
                    <div class="hero-steps">
                        <div class="hero-step">
                            <span class="hero-step-num">1</span>
                            <div>
                                <strong>Shop</strong>
                                <small>Browse categories & add to cart</small>
                            </div>
                        </div>
                        <div class="hero-step">
                            <span class="hero-step-num">2</span>
                            <div>
                                <strong>Pay</strong>
                                <small>Secure checkout with Stripe</small>
                            </div>
                        </div>
                        <div class="hero-step">
                            <span class="hero-step-num">3</span>
                            <div>
                                <strong>Drive over</strong>
                                <small>Head to Abdu Market</small>
                            </div>
                        </div>
                        <div class="hero-step">
                            <span class="hero-step-num">4</span>
                            <div>
                                <strong>Tap I'm Here</strong>
                                <small>We bring it to your car</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-4" id="products">
    <?php $categoryCount = count($categories); ?>
    <div class="mb-5 category-section">
        <h2 class="section-title">Shop by Category</h2>
        <div class="row g-3 category-grid is-collapsed" id="categoryGrid">
            <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-md-4 col-lg-2 category-tile">
                <a href="shop.php?category=<?= (int) $cat['id'] ?>" class="category-card">
                    <div class="category-img catalog-tile-media<?= catalog_has_image($cat['image_url'] ?? null) ? '' : ' show-initials' ?>">
                        <?= catalog_tile_media($cat['name'], $cat['image_url'] ?? null) ?>
                    </div>
                    <span><?= e($cat['name']) ?></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($categoryCount > 4): ?>
        <div class="text-center mt-3">
            <button type="button" class="btn btn-outline-danger btn-sm category-see-more-btn" id="categorySeeMoreBtn" aria-expanded="false">
                See More
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($featuredProducts)): ?>
    <div class="mb-5 featured-products-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-title mb-0">Featured Products</h2>
            <a href="shop.php?sort=featured" class="small text-danger text-decoration-none">View all featured</a>
        </div>
        <div class="featured-carousel-wrap">
            <button type="button" class="featured-carousel-btn featured-carousel-prev" id="featuredCarouselPrev" aria-label="Previous featured products">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div class="featured-carousel" id="featuredCarousel">
                <?php foreach ($featuredProducts as $product): ?>
                <div class="featured-carousel-slide">
                    <article class="product-card card h-100 border-0 shadow-sm featured-product-card">
                        <div class="product-img catalog-tile-media<?= catalog_has_image($product['image_url'] ?? null) ? '' : ' show-initials' ?>">
                            <?= catalog_tile_media($product['name'], $product['image_url'] ?? null) ?>
                            <span class="featured-badge"><i class="bi bi-star-fill"></i> Featured</span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <span class="product-category"><?= e($product['category_name'] ?? 'General') ?></span>
                            <h3 class="product-name"><?= e($product['name']) ?></h3>
                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <strong class="product-price"><?= format_money($product['price']) ?></strong>
                                <?php if (is_logged_in() && product_is_purchasable($product)): ?>
                                <form method="post" action="<?= e(asset_url('mart-line.php')) ?>" class="mart-buy-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-plus-lg"></i> Add
                                    </button>
                                </form>
                                <?php elseif (!is_logged_in()): ?>
                                <a href="login.php" class="btn btn-sm btn-outline-danger">Sign in</a>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Unavailable</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="featured-carousel-btn featured-carousel-next" id="featuredCarouselNext" aria-label="Next featured products">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="home-products-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-title mb-0">Products</h2>
            <span class="text-muted"><?= $totalProducts ?> items</span>
        </div>

        <?php if (empty($homeProducts)): ?>
        <div class="empty-state text-center py-5">
            <i class="bi bi-basket display-4 text-danger"></i>
            <p class="mt-3 text-muted">No products available yet.</p>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($homeProducts as $product): ?>
            <?php require __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php if ($totalProducts > $homeProductLimit): ?>
        <div class="text-center mt-4">
            <a href="shop.php" class="btn btn-outline-danger btn-sm products-see-more-btn">See More</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
