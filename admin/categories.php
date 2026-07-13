<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'categories';
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Invalid request.');
        redirect('categories.php');
    }

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $imageUrl = trim($_POST['image_url'] ?? '') ?: null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($action === 'delete' && $categoryId > 0) {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$categoryId]);
        flash('success', 'Category deleted.');
        redirect('categories.php');
    }

    if ($name === '') {
        flash('danger', 'Category name is required.');
        redirect($categoryId > 0 ? 'categories.php?id=' . $categoryId : 'categories.php?new=1');
    }

    if ($action === 'update' && $categoryId > 0) {
        db()->prepare(
            'UPDATE categories SET name = ?, sort_order = ?, image_url = ?, is_active = ? WHERE id = ?'
        )->execute([$name, $sortOrder, $imageUrl, $isActive, $categoryId]);
        flash('success', 'Category updated.');
        redirect('categories.php?id=' . $categoryId);
    }

    if ($action === 'create') {
        db()->prepare(
            'INSERT INTO categories (name, sort_order, image_url, is_active) VALUES (?, ?, ?, ?)'
        )->execute([$name, $sortOrder, $imageUrl, $isActive]);
        $newId = (int) db()->lastInsertId();
        flash('success', 'Category created.');
        redirect('categories.php?id=' . $newId);
    }

    redirect('categories.php');
}

if ($editId) {
    $editing = get_category($editId);
    if (!$editing) {
        flash('danger', 'Category not found.');
        redirect('categories.php');
    }
} elseif (isset($_GET['new'])) {
    $editing = [
        'id' => 0,
        'name' => '',
        'sort_order' => 0,
        'image_url' => '',
        'is_active' => 1,
        'clover_id' => null,
        'synced_at' => null,
    ];
}

$categories = get_categories(false);

$counts = [
    'total' => count($categories),
    'active' => 0,
    'hidden' => 0,
    'with_images' => 0,
    'missing_images' => 0,
];
foreach ($categories as $c) {
    if (!empty($c['is_active'])) $counts['active']++; else $counts['hidden']++;
    if (!empty($c['image_url'])) $counts['with_images']++; else $counts['missing_images']++;
}

$pageTitle = $editing ? ($editing['id'] ? 'Edit category' : 'New category') : 'Categories';
$pageSubtitle = $editing ? null : 'Manage storefront categories (manual or Clover-synced)';
$headerActions = $editing
    ? '<a href="categories.php" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-left"></i> All categories</a>'
    : '<a href="categories.php?new=1" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i> Add category</a>';

require dirname(__DIR__) . '/includes/admin_header.php';

if ($editing):
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-header"><h2><?= $editing['id'] ? 'Edit category' : 'New category' ?></h2></div>
            <div class="admin-card-body padded">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="category_id" value="<?= (int) $editing['id'] ?>">
                    <input type="hidden" name="action" value="<?= $editing['id'] ? 'update' : 'create' ?>">

                    <div class="admin-field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="admin-input" value="<?= e($editing['name']) ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label for="sort_order">Sort order</label>
                                <input type="number" id="sort_order" name="sort_order" class="admin-input" value="<?= (int) $editing['sort_order'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
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
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $editing['id'] ? 'Save changes' : 'Create category' ?></button>
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
                <p class="small mt-3 mb-0">Manual edits are kept until the next Clover sync overwrites name and sort order.</p>
                <?php else: ?>
                <p class="mb-0 text-muted">This category was created manually and is not linked to Clover.</p>
                <?php endif; ?>
            </div>
        </div>
        <form method="post" class="mt-3" onsubmit="return confirm('Delete this category? Products in it will become uncategorized.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" value="<?= (int) $editing['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-outline text-danger w-100"><i class="bi bi-trash"></i> Delete category</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat-label">Total categories</div>
        <div class="admin-stat-value"><?= (int) $counts['total'] ?></div>
    </div>
    <div class="admin-stat highlight">
        <div class="admin-stat-label">Active</div>
        <div class="admin-stat-value"><?= (int) $counts['active'] ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Missing images</div>
        <div class="admin-stat-value"><?= (int) $counts['missing_images'] ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Hidden</div>
        <div class="admin-stat-value"><?= (int) $counts['hidden'] ?></div>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Products</th>
                    <th>Sort</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="6">
                        <div class="admin-empty py-4">
                            <i class="bi bi-folder2"></i>
                            <p>No categories yet. Add one manually or sync from Clover.</p>
                            <a href="clover-sync.php" class="admin-btn admin-btn-outline admin-btn-sm">Go to Clover Sync</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <strong><?= e($cat['name']) ?></strong>
                        <?php if ($cat['image_url']): ?>
                        <span class="admin-badge ms-1">image</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $cat['product_count'] ?></td>
                    <td><?= (int) $cat['sort_order'] ?></td>
                    <td>
                        <?php if ($cat['clover_id']): ?>
                        <span class="admin-badge admin-badge-green">Clover</span>
                        <?php else: ?>
                        <span class="admin-badge">Manual</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($cat['is_active']): ?>
                        <span class="admin-badge admin-badge-green">Active</span>
                        <?php else: ?>
                        <span class="admin-badge">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="categories.php?id=<?= (int) $cat['id'] ?>" class="admin-btn admin-btn-outline admin-btn-sm">Edit</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif;

require dirname(__DIR__) . '/includes/admin_footer.php';
