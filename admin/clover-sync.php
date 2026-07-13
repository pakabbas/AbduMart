<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

use App\CloverService;
use App\SettingsService;

$adminSection = 'clover-sync';
$syncMessage = null;
$syncError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $syncError = 'Invalid request.';
    } else {
        try {
            $clover = new CloverService();
            $result = $clover->syncAll();
            $syncMessage = sprintf(
                'Synced %d categories and %d products from Clover.',
                $result['categories'],
                $result['products']
            );
        } catch (Throwable $e) {
            $syncError = $e->getMessage();
        }
    }
}

$cloverConfigured = SettingsService::isGroupConfigured('clover');
$cloverEnv = setting('clover.env', 'sandbox');
$merchantId = setting('clover.merchant_id', '');
$stats = CloverService::getSyncStats();
$logs = CloverService::getSyncLogs(50);

$pageTitle = 'Clover Sync';
$pageSubtitle = 'Pull categories and products from your Clover POS';
$headerActions = $cloverConfigured
    ? '<form method="post" class="d-inline">' . csrf_field() .
        '<input type="hidden" name="action" value="sync">' .
        '<button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-arrow-repeat"></i> Sync now</button></form>'
    : '<a href="settings.php#clover" class="admin-btn admin-btn-outline"><i class="bi bi-sliders"></i> Configure Clover</a>';

require dirname(__DIR__) . '/includes/admin_header.php';

if ($syncMessage): ?>
<div class="admin-toast admin-toast-success"><i class="bi bi-check-circle"></i> <?= e($syncMessage) ?></div>
<?php endif;
if ($syncError): ?>
<div class="admin-toast admin-toast-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($syncError) ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header"><h2>Connection</h2></div>
            <div class="admin-card-body padded">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="status-chip <?= $cloverConfigured ? 'ok' : '' ?>" style="margin:0">
                        <div class="chip-icon"><i class="bi bi-shop"></i></div>
                        <div>
                            <strong><?= $cloverConfigured ? 'Connected' : 'Not configured' ?></strong>
                            <span><?= e(ucfirst($cloverEnv)) ?> environment</span>
                        </div>
                    </div>
                </div>
                <?php if ($merchantId): ?>
                <p class="small text-muted mb-2">Merchant ID</p>
                <p class="mb-3"><code><?= e($merchantId) ?></code></p>
                <?php endif; ?>
                <?php if (!$cloverConfigured): ?>
                <p class="small mb-3">Add your Clover merchant ID and API token in Settings before running a sync.</p>
                <a href="settings.php#clover" class="admin-btn admin-btn-primary admin-btn-sm">Open Clover settings</a>
                <?php else: ?>
                <p class="small mb-0 text-muted">Sync pulls categories and inventory items from Clover into your storefront catalog.</p>
                <p class="small mb-0 text-muted mt-2"><i class="bi bi-clock"></i> While the store is open, Clover syncs automatically every hour.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="admin-stats" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));">
            <div class="admin-stat">
                <div class="admin-stat-label">Categories</div>
                <div class="admin-stat-value"><?= $stats['categories_total'] ?></div>
                <div class="small text-muted"><?= $stats['categories_clover'] ?> from Clover</div>
            </div>
            <div class="admin-stat">
                <div class="admin-stat-label">Products</div>
                <div class="admin-stat-value"><?= $stats['products_total'] ?></div>
                <div class="small text-muted"><?= $stats['products_clover'] ?> from Clover</div>
            </div>
            <div class="admin-stat">
                <div class="admin-stat-label">Active products</div>
                <div class="admin-stat-value"><?= $stats['products_active'] ?></div>
            </div>
            <div class="admin-stat">
                <div class="admin-stat-label">Last successful sync</div>
                <div class="admin-stat-value" style="font-size:1rem;line-height:1.3">
                    <?= $stats['last_success_at']
                        ? e(date('M j, g:i A', strtotime($stats['last_success_at'])))
                        : '—' ?>
                </div>
            </div>
        </div>
        <div class="admin-card mt-4">
            <div class="admin-card-body padded">
                <p class="mb-2"><strong>Quick links</strong></p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="categories.php" class="admin-btn admin-btn-outline admin-btn-sm"><i class="bi bi-folder2"></i> Categories</a>
                    <a href="products.php" class="admin-btn admin-btn-outline admin-btn-sm"><i class="bi bi-box-seam"></i> Products</a>
                    <a href="settings.php#clover" class="admin-btn admin-btn-outline admin-btn-sm"><i class="bi bi-sliders"></i> Clover settings</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2>Sync history</h2>
        <span class="admin-badge"><?= count($logs) ?> recent entries</span>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="4">
                        <div class="admin-empty py-4">
                            <i class="bi bi-clock-history"></i>
                            <p>No sync runs yet. Use <strong>Sync now</strong> to pull your Clover catalog.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-nowrap"><?= e(date('M j, Y g:i A', strtotime($log['created_at']))) ?></td>
                    <td><?= e(ucfirst($log['sync_type'])) ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                        <span class="admin-badge admin-badge-green">Success</span>
                        <?php else: ?>
                        <span class="admin-badge admin-badge-red">Failed</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($log['message'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
