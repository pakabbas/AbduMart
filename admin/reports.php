<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'reports';

$range = $_GET['range'] ?? '30d';
$days = match ($range) {
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    default => 30,
};

$start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$end = date('Y-m-d');

$hasPaymentMethod = db_has_column('orders', 'payment_method');

// Cards
$stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ?');
$stmt->execute([$start, $end]);
$ordersTotal = (int) $stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'");
$stmt->execute([$start, $end]);
$ordersNonCancelled = (int) $stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'picked_up'");
$stmt->execute([$start, $end]);
$pickedUpCount = (int) $stmt->fetchColumn();

$stmt = db()->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'cancelled'");
$stmt->execute([$start, $end]);
$revenue = (float) $stmt->fetchColumn();

$arrivalRevenue = 0.0;
if ($hasPaymentMethod) {
    $stmt = db()->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND payment_method = 'arrival' AND status != 'cancelled'");
    $stmt->execute([$start, $end]);
    $arrivalRevenue = (float) $stmt->fetchColumn();
}

$avgOrder = $ordersNonCancelled > 0 ? $revenue / $ordersNonCancelled : 0.0;

// Graph data: orders per day + revenue per day
$stmt = db()->prepare(
    "SELECT DATE(created_at) AS day,
            COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END),0) AS revenue
     FROM orders
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);
$stmt->execute([$start, $end]);
$rows = $stmt->fetchAll();

$byDay = [];
for ($i = 0; $i < $days; $i++) {
    $d = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
    $byDay[$d] = ['orders' => 0, 'revenue' => 0.0];
}
foreach ($rows as $r) {
    $day = $r['day'];
    if (isset($byDay[$day])) {
        $byDay[$day]['orders'] = (int) $r['orders'];
        $byDay[$day]['revenue'] = (float) $r['revenue'];
    }
}

$labels = array_keys($byDay);
$ordersSeries = array_map(static fn($v) => (int) $v['orders'], array_values($byDay));
$revenueSeries = array_map(static fn($v) => round((float) $v['revenue'], 2), array_values($byDay));

$pageTitle = 'Reports';
$pageSubtitle = 'Orders and revenue overview';
$headerActions = '<form method="get" class="d-flex gap-2 align-items-center">'
    . '<select name="range" class="admin-input" style="min-width:140px" onchange="this.form.submit()">'
    . '<option value="7d"' . ($range === '7d' ? ' selected' : '') . '>Last 7 days</option>'
    . '<option value="30d"' . ($range === '30d' ? ' selected' : '') . '>Last 30 days</option>'
    . '<option value="90d"' . ($range === '90d' ? ' selected' : '') . '>Last 90 days</option>'
    . '</select>'
    . '</form>';

require dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat-label">Orders (<?= e($range) ?>)</div>
        <div class="admin-stat-value"><?= (int) $ordersNonCancelled ?></div>
    </div>
    <div class="admin-stat highlight">
        <div class="admin-stat-label">Revenue (<?= e($range) ?>)</div>
        <div class="admin-stat-value"><?= e(format_money($revenue)) ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Avg order</div>
        <div class="admin-stat-value"><?= e(format_money($avgOrder)) ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Picked up</div>
        <div class="admin-stat-value"><?= (int) $pickedUpCount ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header"><h2><i class="bi bi-graph-up me-2"></i>Orders trend</h2></div>
            <div class="admin-card-body padded">
                <canvas id="ordersChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header"><h2><i class="bi bi-wallet2 me-2"></i>Pay on Arrival</h2></div>
            <div class="admin-card-body padded">
                <?php if (!$hasPaymentMethod): ?>
                <div class="admin-callout">
                    <strong>Database update pending</strong>
                    <div class="hint mb-0">Run migration <code>005_pay_on_arrival_and_order_logs.sql</code> to enable pay-on-arrival reporting.</div>
                </div>
                <?php endif; ?>
                <p class="text-muted mb-2">Revenue from pay-on-arrival orders (<?= e($range) ?>):</p>
                <div class="fs-3 fw-bold" style="color:var(--admin-red)"><?= e(format_money($arrivalRevenue)) ?></div>
                <hr class="my-4">
                <p class="small text-muted mb-0">Tip: use this to track how much is being collected at pickup vs online.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    const el = document.getElementById('ordersChart');
    if (!el) return;
    const labels = <?= json_encode($labels) ?>;
    const orders = <?= json_encode($ordersSeries) ?>;
    const revenue = <?= json_encode($revenueSeries) ?>;

    new Chart(el, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Orders',
                    data: orders,
                    borderColor: '#c8102e',
                    backgroundColor: 'rgba(200, 16, 46, 0.10)',
                    tension: 0.35,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Revenue ($)',
                    data: revenue,
                    borderColor: '#111827',
                    backgroundColor: 'rgba(17, 24, 39, 0.08)',
                    tension: 0.35,
                    fill: false,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { mode: 'index', intersect: false },
            },
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });
})();
</script>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>

