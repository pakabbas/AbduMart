<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'products';
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = null;
$categoryFilter = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Invalid request.');
        redirect('products.php');
    }

    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $price = round((float) ($_POST['price'] ?? 0), 2);
    $inventory = max(0, (int) ($_POST['inventory'] ?? 0));
    $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
    $imageUrl = trim($_POST['image_url'] ?? '') ?: null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($action === 'delete' && $productId > 0) {
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
        flash('success', 'Product deleted.');
        redirect('products.php');
    }

    if ($name === '') {
        flash('danger', 'Product name is required.');
        redirect($productId > 0 ? 'products.php?id=' . $productId : 'products.php?new=1');
    }

    if ($action === 'update' && $productId > 0) {
        db()->prepare(
            'UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, inventory = ?, image_url = ?, is_active = ? WHERE id = ?'
        )->execute([$categoryId, $name, $description, $price, $inventory, $imageUrl, $isActive, $productId]);
        flash('success', 'Product updated.');
        redirect('products.php?id=' . $productId);
    }

    if ($action === 'create') {
        db()->prepare(
            'INSERT INTO products (category_id, name, description, price, inventory, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$categoryId, $name, $description, $price, $inventory, $imageUrl, $isActive]);
        $newId = (int) db()->lastInsertId();
        flash('success', 'Product created.');
        redirect('products.php?id=' . $newId);
    }

    redirect('products.php');
}

$allCategories = get_categories(false);

if ($editId) {
    $editing = get_product($editId);
    if (!$editing) {
        flash('danger', 'Product not found.');
        redirect('products.php');
    }
} elseif (isset($_GET['new'])) {
    $editing = [
        'id' => 0,
        'category_id' => $categoryFilter ?: null,
        'name' => '',
        'description' => '',
        'price' => '0.00',
        'inventory' => 0,
        'image_url' => '',
        'is_active' => 1,
        'clover_id' => null,
        'synced_at' => null,
        'category_name' => null,
    ];
}

$filters = ['include_inactive' => true, 'sort' => 'name'];
if ($categoryFilter > 0) {
    $filters['category_id'] = $categoryFilter;
}
if ($search !== '') {
    $filters['search'] = $search;
}
$products = $editing ? [] : get_products($filters);

$pageTitle = $editing ? ($editing['id'] ? 'Edit product' : 'New product') : 'Products';
$pageSubtitle = $editing ? null : 'Manage inventory and pricing (works alongside Clover sync)';
$headerActions = $editing
    ? '<a href="products.php" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-left"></i> All products</a>'
    : '<a href="products.php?new=1" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i> Add product</a>';

require dirname(__DIR__) . '/includes/admin_header.php';

if ($editing):
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-header"><h2><?= $editing['id'] ? 'Edit product' : 'New product' ?></h2></div>
            <div class="admin-card-body padded">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $editing['id'] ?>">
                    <input type="hidden" name="action" value="<?= $editing['id'] ? 'update' : 'create' ?>">

                    <div class="admin-field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="admin-input" value="<?= e($editing['name']) ?>" required>
                    </div>
                    <div class="admin-field">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="admin-input">
                            <option value="">— Uncategorized —</option>
                            <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?><?= !$cat['is_active'] ? ' (hidden)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="admin-input" rows="3"><?= e($editing['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="admin-field">
                                <label for="price">Price ($)</label>
                                <input type="number" id="price" name="price" class="admin-input" step="0.01" min="0" value="<?= e(number_format((float) $editing['price'], 2, '.', '')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="admin-field">
                                <label for="inventory">Inventory</label>
                                <input type="number" id="inventory" name="inventory" class="admin-input" min="0" value="<?= (int) $editing['inventory'] ?>">
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="admin-field mb-0 w-100">
                                <label class="d-flex align-items-center gap-2">
                                    <input type="checkbox" name="is_active" value="1" <?= $editing['is_active'] ? 'checked' : '' ?>>
                                    Active on storefront
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="admin-field">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" class="admin-input" value="<?= e($editing['image_url'] ?? '') ?>" placeholder="https://...">
                    </div>
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $editing['id'] ? 'Save changes' : 'Create product' ?></button>
                </form>
            </div>
        </div>
    </div>
    <?php if ($editing['id']): ?>
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="admin-card-header"><h2>Clover link</h2></div>
            <div class="admin-card-body padded">
                <?php if ($editing['clover_id']): ?>
                <p class="mb-2"><span class="admin-badge admin-badge-green">Synced from Clover</span></p>
                <p class="small text-muted mb-2">Clover ID: <code><?= e($editing['clover_id']) ?></code></p>
                <?php if ($editing['synced_at']): ?>
                <p class="small text-muted mb-0">Last synced <?= e(date('M j, Y g:i A', strtotime($editing['synced_at']))) ?></p>
                <?php endif; ?>
                <p class="small mt-3 mb-0">A Clover sync will refresh name, price, inventory, and category from your POS.</p>
                <?php else: ?>
                <p class="mb-0 text-muted">This product was created manually and is not linked to Clover.</p>
                <?php endif; ?>
            </div>
        </div>
        <form method="post" class="mt-3" onsubmit="return confirm('Delete this product?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="product_id" value="<?= (int) $editing['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-outline text-danger w-100"><i class="bi bi-trash"></i> Delete product</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="admin-card mb-4">
    <div class="admin-card-body padded">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-5">
                <div class="admin-field mb-0">
                    <label for="q">Search</label>
                    <input type="search" id="q" name="q" class="admin-input" value="<?= e($search) ?>" placeholder="Name or description">
                </div>
            </div>
            <div class="col-md-4">
                <div class="admin-field mb-0">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="admin-input">
                        <option value="">All categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $categoryFilter === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="admin-btn admin-btn-primary flex-grow-1">Filter</button>
                <?php if ($search !== '' || $categoryFilter > 0): ?>
                <a href="products.php" class="admin-btn admin-btn-outline">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="7">
                        <div class="admin-empty py-4">
                            <i class="bi bi-box-seam"></i>
                            <p>No products found. Add one manually or sync from Clover.</p>
                            <a href="clover-sync.php" class="admin-btn admin-btn-outline admin-btn-sm">Go to Clover Sync</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <strong><?= e($product['name']) ?></strong>
                        <?php if ($product['image_url']): ?>
                        <span class="admin-badge ms-1">image</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($product['category_name'] ?? '—') ?></td>
                    <td><?= format_money($product['price']) ?></td>
                    <td><?= (int) $product['inventory'] ?></td>
                    <td>
                        <?php if ($product['clover_id']): ?>
                        <span class="admin-badge admin-badge-green">Clover</span>
                        <?php else: ?>
                        <span class="admin-badge">Manual</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($product['is_active'] && $product['inventory'] > 0): ?>
                        <span class="admin-badge admin-badge-green">Active</span>
                        <?php elseif ($product['is_active']): ?>
                        <span class="admin-badge">Out of stock</span>
                        <?php else: ?>
                        <span class="admin-badge">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="products.php?id=<?= (int) $product['id'] ?>" class="admin-btn admin-btn-outline admin-btn-sm">Edit</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif;

require dirname(__DIR__) . '/includes/admin_footer.php';
