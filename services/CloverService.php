<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class CloverService
{
    private string $baseUrl;
    private string $merchantId;
    private string $apiToken;

    public function __construct()
    {
        $env = setting('clover.env', 'sandbox');
        $this->baseUrl = $env === 'production'
            ? 'https://api.clover.com'
            : 'https://apisandbox.dev.clover.com';
        $this->merchantId = (string) setting('clover.merchant_id');
        $this->apiToken = (string) setting('clover.api_token');
    }

    public function isConfigured(): bool
    {
        return $this->merchantId !== '' && $this->apiToken !== '';
    }

    public function syncAll(): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Clover API credentials are not configured.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $categories = $this->fetchCategories();
            $categoryMap = $this->upsertCategories($pdo, $categories);
            $items = $this->fetchItems();
            $this->upsertProducts($pdo, $items, $categoryMap);

            $pdo->commit();
            $this->logSync('full', 'success', sprintf(
                'Synced %d categories and %d products.',
                count($categories),
                count($items)
            ));

            return ['categories' => count($categories), 'products' => count($items)];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->logSync('full', 'failed', $e->getMessage());
            throw $e;
        }
    }

    private function fetchCategories(): array
    {
        $data = $this->request('GET', "/v3/merchants/{$this->merchantId}/categories?limit=1000");
        return $data['elements'] ?? [];
    }

    private function fetchItems(): array
    {
        $data = $this->request('GET', "/v3/merchants/{$this->merchantId}/items?expand=itemStock,categories&limit=1000");
        return $data['elements'] ?? [];
    }

    private function upsertCategories(PDO $pdo, array $categories): array
    {
        $map = [];
        $stmt = $pdo->prepare(
            'INSERT INTO categories (clover_id, name, sort_order, is_active, synced_at)
             VALUES (?, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order), synced_at = NOW()'
        );

        $sort = 0;
        foreach ($categories as $cat) {
            $cloverId = $cat['id'] ?? null;
            if (!$cloverId) {
                continue;
            }
            $name = $cat['name'] ?? 'Uncategorized';
            $stmt->execute([$cloverId, $name, $sort++]);

            $idStmt = $pdo->prepare('SELECT id FROM categories WHERE clover_id = ?');
            $idStmt->execute([$cloverId]);
            $map[$cloverId] = (int) $idStmt->fetchColumn();
        }
        return $map;
    }

    private function upsertProducts(PDO $pdo, array $items, array $categoryMap): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO products (clover_id, category_id, name, description, price, inventory, is_active, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                category_id = VALUES(category_id),
                name = VALUES(name),
                description = VALUES(description),
                price = VALUES(price),
                inventory = VALUES(inventory),
                is_active = VALUES(is_active),
                synced_at = NOW()'
        );

        foreach ($items as $item) {
            $cloverId = $item['id'] ?? null;
            if (!$cloverId) {
                continue;
            }

            $priceCents = (int) ($item['price'] ?? 0);
            $price = $priceCents / 100;
            $inventory = (int) ($item['itemStock']['quantity'] ?? $item['stockCount'] ?? 0);
            $hidden = (bool) ($item['hidden'] ?? false);
            $name = $item['name'] ?? 'Product';
            $description = $item['alternateName'] ?? null;

            $categoryId = null;
            $cats = $item['categories']['elements'] ?? [];
            if (!empty($cats[0]['id']) && isset($categoryMap[$cats[0]['id']])) {
                $categoryId = $categoryMap[$cats[0]['id']];
            }

            $stmt->execute([
                $cloverId,
                $categoryId,
                $name,
                $description,
                $price,
                max(0, $inventory),
                $hidden ? 0 : 1,
            ]);
        }
    }

    private function request(string $method, string $path): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Accept: application/json',
            ],
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Clover API request failed: ' . $error);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $message = $data['message'] ?? $response;
            throw new \RuntimeException("Clover API error ({$httpCode}): {$message}");
        }

        return is_array($data) ? $data : [];
    }

    private function logSync(string $type, string $status, ?string $message): void
    {
        $stmt = db()->prepare(
            'INSERT INTO clover_sync_log (sync_type, status, message) VALUES (?, ?, ?)'
        );
        $stmt->execute([$type, $status, $message]);
    }

    public static function getSyncLogs(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = db()->prepare(
            'SELECT * FROM clover_sync_log ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getSyncStats(): array
    {
        $pdo = db();
        $lastSync = $pdo->query(
            "SELECT created_at FROM clover_sync_log WHERE status = 'success' ORDER BY created_at DESC LIMIT 1"
        )->fetchColumn();

        return [
            'categories_total' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
            'categories_clover' => (int) $pdo->query('SELECT COUNT(*) FROM categories WHERE clover_id IS NOT NULL')->fetchColumn(),
            'products_total' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'products_clover' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE clover_id IS NOT NULL')->fetchColumn(),
            'products_active' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
            'last_success_at' => $lastSync ?: null,
        ];
    }
}
