<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'reports';

$tab = $_GET['tab'] ?? 'sales';
if (!in_array($tab, ['sales', 'products', 'customers'], true)) {
    $tab = 'sales';
}

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
$dbError = null;

$ordersTotal = 0;
$ordersNonCancelled = 0;
$pickedUpCount = 0;
$revenue = 0.0;
$arrivalRevenue = 0.0;
$avgOrder = 0.0;
$rows = [];
$bestItems = [];
$bestCategories = [];
$statusBreakdown = [];
$paymentBreakdown = [];
$customerRegRows = [];
$newCustomers = 0;

try {
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

    if ($hasPaymentMethod) {
        $stmt = db()->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND payment_method = 'arrival' AND status != 'cancelled'");
        $stmt->execute([$start, $end]);
        $arrivalRevenue = (float) $stmt->fetchColumn();
    }

    $avgOrder = $ordersNonCancelled > 0 ? $revenue / $ordersNonCancelled : 0.0;

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

    $stmt = db()->prepare(
        "SELECT oi.product_id,
                oi.product_name,
                SUM(oi.quantity) AS units_sold,
                COALESCE(SUM(oi.line_total), 0) AS item_revenue,
                COUNT(DISTINCT oi.order_id) AS orders_count
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE DATE(o.created_at) BETWEEN ? AND ?
           AND o.status != 'cancelled'
         GROUP BY oi.product_id, oi.product_name
         ORDER BY units_sold DESC, item_revenue DESC
         LIMIT 10"
    );
    $stmt->execute([$start, $end]);
    $bestItems = $stmt->fetchAll();

    $stmt = db()->prepare(
        "SELECT COALESCE(c.id, 0) AS category_id,
                COALESCE(c.name, 'Uncategorized') AS category_name,
                SUM(oi.quantity) AS units_sold,
                COALESCE(SUM(oi.line_total), 0) AS category_revenue,
                COUNT(DISTINCT oi.order_id) AS orders_count
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         LEFT JOIN products p ON p.id = oi.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE DATE(o.created_at) BETWEEN ? AND ?
           AND o.status != 'cancelled'
         GROUP BY c.id, c.name
         ORDER BY category_revenue DESC, units_sold DESC
         LIMIT 10"
    );
    $stmt->execute([$start, $end]);
    $bestCategories = $stmt->fetchAll();

    $stmt = db()->prepare(
        "SELECT status, COUNT(*) AS order_count
         FROM orders
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY status
         ORDER BY order_count DESC"
    );
    $stmt->execute([$start, $end]);
    $statusBreakdown = $stmt->fetchAll();

    $stmt = db()->prepare(
        "SELECT DATE(created_at) AS day, COUNT(*) AS signups
         FROM users
         WHERE role = 'customer'
           AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY day ASC"
    );
    $stmt->execute([$start, $end]);
    $customerRegRows = $stmt->fetchAll();

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM users
         WHERE role = 'customer'
           AND DATE(created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$start, $end]);
    $newCustomers = (int) $stmt->fetchColumn();

    if ($hasPaymentMethod) {
        $stmt = db()->prepare(
            "SELECT payment_method, COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS method_revenue
             FROM orders
             WHERE DATE(created_at) BETWEEN ? AND ?
               AND status != 'cancelled'
             GROUP BY payment_method
             ORDER BY method_revenue DESC"
        );
        $stmt->execute([$start, $end]);
        $paymentBreakdown = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

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
$ordersSeries = array_map(static fn ($v) => (int) $v['orders'], array_values($byDay));
$revenueSeries = array_map(static fn ($v) => round((float) $v['revenue'], 2), array_values($byDay));

$customerByDay = [];
for ($i = 0; $i < $days; $i++) {
    $d = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
    $customerByDay[$d] = 0;
}
foreach ($customerRegRows as $row) {
    $day = $row['day'];
    if (isset($customerByDay[$day])) {
        $customerByDay[$day] = (int) $row['signups'];
    }
}
$customerRegSeries = array_values($customerByDay);

$chartLabels = array_map(static fn (string $d): string => date('M j', strtotime($d)), $labels);

$bestItemLabels = array_map(static fn (array $row): string => (string) $row['product_name'], $bestItems);
$bestItemUnits = array_map(static fn (array $row): int => (int) $row['units_sold'], $bestItems);
$bestItemRevenue = array_map(static fn (array $row): float => round((float) $row['item_revenue'], 2), $bestItems);

$bestCategoryLabels = array_map(static fn (array $row): string => (string) $row['category_name'], $bestCategories);
$bestCategoryRevenue = array_map(static fn (array $row): float => round((float) $row['category_revenue'], 2), $bestCategories);
$bestCategoryUnits = array_map(static fn (array $row): int => (int) $row['units_sold'], $bestCategories);

$statusLabels = array_map(static function (array $row): string {
    return ucfirst(str_replace('_', ' ', (string) $row['status']));
}, $statusBreakdown);
$statusCounts = array_map(static fn (array $row): int => (int) $row['order_count'], $statusBreakdown);

$paymentLabels = array_map(static function (array $row): string {
    return match ($row['payment_method'] ?? '') {
        'arrival' => 'Pay on arrival',
        'clover' => 'Clover online',
        default => 'Stripe online',
    };
}, $paymentBreakdown);
$paymentRevenue = array_map(static fn (array $row): float => round((float) $row['method_revenue'], 2), $paymentBreakdown);

$pageTitle = 'Reports';
$pageSubtitle = match ($tab) {
    'products' => 'Product and category performance',
    'customers' => 'Customer signup and growth',
    default => 'Sales, revenue, and order trends',
};
$reportsBase = 'reports.php?range=' . rawurlencode($range);
$headerActions = '<form method="get" class="d-flex gap-2 align-items-center">'
    . '<input type="hidden" name="tab" value="' . e($tab) . '">'
    . '<select name="range" class="admin-input" style="min-width:140px" onchange="this.form.submit()">'
    . '<option value="7d"' . ($range === '7d' ? ' selected' : '') . '>Last 7 days</option>'
    . '<option value="30d"' . ($range === '30d' ? ' selected' : '') . '>Last 30 days</option>'
    . '<option value="90d"' . ($range === '90d' ? ' selected' : '') . '>Last 90 days</option>'
    . '</select>'
    . '</form>';

require dirname(__DIR__) . '/includes/admin_header.php';
?>

<?php if ($dbError): ?>
<div class="admin-toast admin-toast-danger"><i class="bi bi-exclamation-triangle"></i> Reports temporarily unavailable: <?= e($dbError) ?></div>
<?php endif; ?>

<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat-label">Orders (<?= e($range) ?>)</div>
        <div class="admin-stat-value"><?= (int) $ordersNonCancelled ?></div>
        <div class="small text-muted"><?= (int) $ordersTotal ?> total incl. cancelled</div>
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
    <div class="admin-stat">
        <div class="admin-stat-label">New customers</div>
        <div class="admin-stat-value"><?= (int) $newCustomers ?></div>
        <div class="small text-muted"><?= e($range) ?> signups</div>
    </div>
</div>

<div class="admin-tabbar admin-tabbar--reports mb-4">
    <nav class="admin-tabbar-nav admin-tabbar-nav--3" aria-label="Report sections">
        <a href="<?= e($reportsBase . '&tab=sales') ?>" class="admin-tabbar-item<?= $tab === 'sales' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-graph-up-arrow"></i></span>
            <span class="admin-tabbar-copy">
                <strong>Sales</strong>
                <small>Revenue & orders</small>
            </span>
            <span class="admin-tabbar-count"><?= (int) $ordersNonCancelled ?></span>
        </a>
        <a href="<?= e($reportsBase . '&tab=products') ?>" class="admin-tabbar-item<?= $tab === 'products' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-box-seam"></i></span>
            <span class="admin-tabbar-copy">
                <strong>Products</strong>
                <small>Top sellers & categories</small>
            </span>
            <span class="admin-tabbar-count"><?= count($bestItems) ?></span>
        </a>
        <a href="<?= e($reportsBase . '&tab=customers') ?>" class="admin-tabbar-item<?= $tab === 'customers' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-people"></i></span>
            <span class="admin-tabbar-copy">
                <strong>Customers</strong>
                <small>Signups & growth</small>
            </span>
            <span class="admin-tabbar-count"><?= (int) $newCustomers ?></span>
        </a>
    </nav>
</div>

<div class="admin-report-panel">
<?php if ($tab === 'sales'): ?>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="admin-card h-100">
            <div class="admin-card-header"><h2><i class="bi bi-graph-up me-2"></i>Orders & revenue trend</h2></div>
            <div class="admin-card-body padded">
                <canvas id="ordersChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header"><h2><i class="bi bi-pie-chart me-2"></i>Order status</h2></div>
            <div class="admin-card-body padded">
                <?php if (empty($statusBreakdown)): ?>
                <p class="text-muted mb-0">No orders in this range.</p>
                <?php else: ?>
                <canvas id="statusChart" height="180"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="admin-card-header"><h2><i class="bi bi-bar-chart me-2"></i>Daily revenue</h2></div>
            <div class="admin-card-body padded">
                <canvas id="revenueBarChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="admin-card-header"><h2><i class="bi bi-wallet2 me-2"></i>Payment mix</h2></div>
            <div class="admin-card-body padded">
                <?php if (!$hasPaymentMethod): ?>
                <p class="text-muted mb-0">Payment method tracking requires migration <code>005_pay_on_arrival_and_order_logs.sql</code>.</p>
                <?php elseif (empty($paymentBreakdown)): ?>
                <p class="text-muted mb-0">No paid orders in this range.</p>
                <?php else: ?>
                <canvas id="paymentChart" height="160" class="mb-3"></canvas>
                <p class="mb-2 text-muted">Pay on arrival revenue (<?= e($range) ?>):</p>
                <div class="fs-4 fw-bold" style="color:var(--admin-red)"><?= e(format_money($arrivalRevenue)) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'products'): ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h2><i class="bi bi-trophy me-2"></i>Best selling items</h2>
                <span class="admin-badge">Top 10 by units</span>
            </div>
            <div class="admin-card-body padded">
                <?php if (empty($bestItems)): ?>
                <div class="admin-empty py-4">
                    <i class="bi bi-box-seam"></i>
                    <p>No item sales in this range yet.</p>
                </div>
                <?php else: ?>
                <canvas id="bestItemsChart" height="140" class="mb-4"></canvas>
                <div class="table-responsive">
                    <table class="admin-table report-rank-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th class="text-end">Units</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Orders</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bestItems as $index => $item): ?>
                            <tr>
                                <td><span class="report-rank"><?= (int) $index + 1 ?></span></td>
                                <td><strong><?= e($item['product_name']) ?></strong></td>
                                <td class="text-end"><?= (int) $item['units_sold'] ?></td>
                                <td class="text-end"><?= e(format_money($item['item_revenue'])) ?></td>
                                <td class="text-end"><?= (int) $item['orders_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h2><i class="bi bi-folder2 me-2"></i>Best selling categories</h2>
                <span class="admin-badge">Top 10 by revenue</span>
            </div>
            <div class="admin-card-body padded">
                <?php if (empty($bestCategories)): ?>
                <div class="admin-empty py-4">
                    <i class="bi bi-folder2-open"></i>
                    <p>No category sales in this range yet.</p>
                </div>
                <?php else: ?>
                <canvas id="bestCategoriesChart" height="180" class="mb-4"></canvas>
                <div class="table-responsive">
                    <table class="admin-table report-rank-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Units</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bestCategories as $category): ?>
                            <tr>
                                <td><strong><?= e($category['category_name']) ?></strong></td>
                                <td class="text-end"><?= (int) $category['units_sold'] ?></td>
                                <td class="text-end"><?= e(format_money($category['category_revenue'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="admin-stat h-100 mb-0">
            <div class="admin-stat-label">New customers</div>
            <div class="admin-stat-value"><?= (int) $newCustomers ?></div>
            <div class="small text-muted">Signups in <?= e($range) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-stat h-100 mb-0">
            <div class="admin-stat-label">Avg daily signups</div>
            <div class="admin-stat-value"><?= $days > 0 ? number_format($newCustomers / $days, 1) : '0' ?></div>
            <div class="small text-muted">Per day this period</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-stat h-100 mb-0">
            <div class="admin-stat-label">Peak signup day</div>
            <?php $peakSignups = max($customerRegSeries ?: [0]); ?>
            <div class="admin-stat-value"><?= (int) $peakSignups ?></div>
            <div class="small text-muted">Most in a single day</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="bi bi-person-plus me-2"></i>Customer registration trend</h2>
                <span class="admin-badge"><?= (int) $newCustomers ?> new in <?= e($range) ?></span>
            </div>
            <div class="admin-card-body padded">
                <canvas id="customerRegChart" height="110"></canvas>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const orders = <?= json_encode($ordersSeries) ?>;
    const revenue = <?= json_encode($revenueSeries) ?>;
    const bestItemLabels = <?= json_encode($bestItemLabels) ?>;
    const bestItemUnits = <?= json_encode($bestItemUnits) ?>;
    const bestCategoryLabels = <?= json_encode($bestCategoryLabels) ?>;
    const bestCategoryRevenue = <?= json_encode($bestCategoryRevenue) ?>;
    const statusLabels = <?= json_encode($statusLabels) ?>;
    const statusCounts = <?= json_encode($statusCounts) ?>;
    const paymentLabels = <?= json_encode($paymentLabels) ?>;
    const paymentRevenue = <?= json_encode($paymentRevenue) ?>;
    const customerRegistrations = <?= json_encode($customerRegSeries) ?>;
    const activeTab = <?= json_encode($tab) ?>;

    const palette = ['#c8102e', '#111827', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#64748b'];
    const statusColors = {
        pending: '#94a3b8',
        paid: '#3b82f6',
        preparing: '#f59e0b',
        ready: '#10b981',
        picked_up: '#111827',
        cancelled: '#c8102e',
    };

    function makeChart(id, config) {
        const el = document.getElementById(id);
        if (!el) return null;
        return new Chart(el, config);
    }

    if (activeTab === 'sales') {
        makeChart('ordersChart', {
            type: 'line',
            data: {
                labels: chartLabels,
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
                plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
                }
            }
        });

        if (statusLabels.length) {
            makeChart('statusChart', {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: statusLabels.map((label, i) => {
                            const key = label.toLowerCase().replace(/ /g, '_');
                            return statusColors[key] || palette[i % palette.length];
                        }),
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '62%',
                }
            });
        }

        makeChart('revenueBarChart', {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Revenue ($)',
                    data: revenue,
                    backgroundColor: 'rgba(17, 24, 39, 0.82)',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        if (paymentLabels.length) {
            makeChart('paymentChart', {
                type: 'pie',
                data: {
                    labels: paymentLabels,
                    datasets: [{
                        data: paymentRevenue,
                        backgroundColor: ['#111827', '#c8102e'],
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label(ctx) {
                                    const value = ctx.parsed || 0;
                                    return (ctx.label || '') + ': $' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    if (activeTab === 'products') {
        if (bestItemLabels.length) {
            makeChart('bestItemsChart', {
                type: 'bar',
                data: {
                    labels: bestItemLabels,
                    datasets: [{
                        label: 'Units sold',
                        data: bestItemUnits,
                        backgroundColor: 'rgba(200, 16, 46, 0.85)',
                        borderRadius: 8,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        if (bestCategoryLabels.length) {
            makeChart('bestCategoriesChart', {
                type: 'doughnut',
                data: {
                    labels: bestCategoryLabels,
                    datasets: [{
                        data: bestCategoryRevenue,
                        backgroundColor: palette,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label(ctx) {
                                    const value = ctx.parsed || 0;
                                    return (ctx.label || '') + ': $' + value.toFixed(2);
                                }
                            }
                        }
                    },
                    cutout: '55%',
                }
            });
        }
    }

    if (activeTab === 'customers') {
        makeChart('customerRegChart', {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'New customers',
                    data: customerRegistrations,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.12)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
})();
</script>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
