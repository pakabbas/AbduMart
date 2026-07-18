<?php

declare(strict_types=1);

namespace App;

class CloverCheckoutService
{
    private string $baseUrl;
    private string $merchantId;
    private string $apiToken;
    private string $webhookSecret;

    public function __construct()
    {
        $env = (string) setting('clover.env', 'sandbox');
        $this->baseUrl = $env === 'production'
            ? 'https://api.clover.com'
            : 'https://apisandbox.dev.clover.com';
        $this->merchantId = (string) setting('clover.merchant_id');
        $this->apiToken = (string) setting('clover.api_token');
        $this->webhookSecret = (string) setting('clover.webhook_secret');
    }

    public function isConfigured(): bool
    {
        return $this->merchantId !== '' && $this->apiToken !== '';
    }

    /**
     * @param array<int, array{name:string,price:float|int|string,quantity:int}> $cartItems
     * @param array{email?:string,first_name?:string,last_name?:string,phone?:string} $customer
     * @return array{checkoutSessionId:string,href:string,expirationTime?:string}
     */
    public function createCheckoutSession(int $orderId, array $cartItems, float $tax, array $customer = []): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Clover payments are not configured.');
        }

        $order = $this->getOrder($orderId);
        $baseUrl = rtrim((string) config('app.url'), '/');

        $lineItems = [];
        foreach ($cartItems as $item) {
            $lineItems[] = [
                'name' => (string) $item['name'],
                'price' => (int) round(((float) $item['price']) * 100),
                'unitQty' => max(1, (int) $item['quantity']),
            ];
        }

        if ($tax > 0) {
            $lineItems[] = [
                'name' => 'Sales Tax',
                'price' => (int) round($tax * 100),
                'unitQty' => 1,
            ];
        }

        if ($lineItems === []) {
            throw new \RuntimeException('Cannot create Clover checkout with an empty cart.');
        }

        $payload = [
            'customer' => $this->buildCustomerPayload($customer),
            'shoppingCart' => [
                'lineItems' => $lineItems,
            ],
            'redirectUrls' => [
                'success' => $baseUrl . '/order-success.php?provider=clover&session_id={CHECKOUT_SESSION_ID}',
                'failure' => $baseUrl . '/checkout.php?cancelled=1',
                'cancel' => $baseUrl . '/checkout.php?cancelled=1',
            ],
        ];

        $data = $this->request('POST', '/invoicingcheckoutservice/v1/checkouts', $payload);
        $sessionId = (string) ($data['checkoutSessionId'] ?? '');
        $href = (string) ($data['href'] ?? '');

        if ($sessionId === '' || $href === '') {
            throw new \RuntimeException('Clover did not return a checkout session URL.');
        }

        // Keep a local mapping so webhooks / success redirects can find the order.
        // {CHECKOUT_SESSION_ID} is not always rewritten by Clover; store exact id.
        if (db_has_column('orders', 'clover_checkout_session_id')) {
            $stmt = db()->prepare(
                'UPDATE orders SET clover_checkout_session_id = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$sessionId, $orderId]);
        }

        return [
            'checkoutSessionId' => $sessionId,
            'href' => $href,
            'expirationTime' => isset($data['expirationTime']) ? (string) $data['expirationTime'] : null,
        ];
    }

    public function handleWebhook(string $payload, ?string $signatureHeader): void
    {
        if ($this->webhookSecret !== '') {
            $this->verifySignature($payload, (string) $signatureHeader);
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid Clover webhook payload.');
        }

        $status = strtoupper((string) ($data['status'] ?? $data['Status'] ?? ''));
        $type = strtoupper((string) ($data['type'] ?? $data['Type'] ?? 'PAYMENT'));
        $paymentId = (string) ($data['id'] ?? $data['Id'] ?? '');
        $sessionId = (string) ($data['data'] ?? $data['Data'] ?? $data['checkoutSessionId'] ?? '');

        if ($sessionId === '' && isset($data['checkoutSessionId'])) {
            $sessionId = (string) $data['checkoutSessionId'];
        }

        if ($type !== '' && $type !== 'PAYMENT') {
            return;
        }

        if (!in_array($status, ['APPROVED', 'SUCCESS', 'PAID', 'COMPLETED'], true)) {
            return;
        }

        if ($sessionId === '') {
            throw new \RuntimeException('Clover webhook missing checkout session id.');
        }

        $this->markOrderPaidBySession($sessionId, $paymentId !== '' ? $paymentId : null);
    }

    public function fulfillSession(string $sessionId): ?array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        $order = $this->findOrderBySession($sessionId);
        if (!$order) {
            return null;
        }

        if (($order['status'] ?? '') === 'pending') {
            // Success redirect can race the webhook; treat a returned session as paid.
            $this->markOrderPaidBySession($sessionId, null);
            $order = $this->getOrder((int) $order['id']);
        }

        return $order;
    }

    public function findOrderBySession(string $sessionId): ?array
    {
        if (!db_has_column('orders', 'clover_checkout_session_id')) {
            return null;
        }

        $stmt = db()->prepare('SELECT * FROM orders WHERE clover_checkout_session_id = ? LIMIT 1');
        $stmt->execute([$sessionId]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    private function markOrderPaidBySession(string $sessionId, ?string $paymentId): void
    {
        $order = $this->findOrderBySession($sessionId);
        if (!$order) {
            throw new \RuntimeException('No order found for Clover checkout session.');
        }

        $orderId = (int) $order['id'];
        if (($order['status'] ?? '') !== 'pending') {
            return;
        }

        if (db_has_column('orders', 'clover_payment_id')) {
            $stmt = db()->prepare(
                'UPDATE orders
                 SET status = ?, clover_payment_id = COALESCE(?, clover_payment_id), updated_at = NOW()
                 WHERE id = ? AND status = ?'
            );
            $stmt->execute(['paid', $paymentId, $orderId, 'pending']);
        } else {
            $stmt = db()->prepare(
                'UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? AND status = ?'
            );
            $stmt->execute(['paid', $orderId, 'pending']);
        }

        if ($stmt->rowCount() > 0) {
            send_order_confirmation_email($orderId);
            notify_admins_new_order($orderId);
        }
    }

    /**
     * @param array{email?:string,first_name?:string,last_name?:string,phone?:string} $customer
     * @return array<string, string>
     */
    private function buildCustomerPayload(array $customer): array
    {
        $payload = [];
        $email = trim((string) ($customer['email'] ?? ''));
        $first = trim((string) ($customer['first_name'] ?? ''));
        $last = trim((string) ($customer['last_name'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($customer['phone'] ?? '')) ?: '';

        if ($email !== '') {
            $payload['email'] = $email;
        }
        if ($first !== '') {
            $payload['firstName'] = $first;
        }
        if ($last !== '') {
            $payload['lastName'] = $last;
        }
        if ($phone !== '') {
            $payload['phoneNumber'] = $phone;
        }

        return $payload === [] ? ['email' => 'guest@example.com'] : $payload;
    }

    private function verifySignature(string $payload, string $signatureHeader): void
    {
        if ($signatureHeader === '') {
            throw new \RuntimeException('Missing Clover-Signature header.');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                $parts[trim($k)] = trim($v);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        if ($timestamp === '' || $signature === '') {
            throw new \RuntimeException('Invalid Clover-Signature header.');
        }

        $signed = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $this->webhookSecret);

        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Clover webhook signature verification failed.');
        }
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Clover-Merchant-Id: ' . $this->merchantId,
        ];

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Clover checkout request failed: ' . $error);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $message = is_array($data) ? ($data['message'] ?? $response) : $response;
            throw new \RuntimeException('Clover checkout error (' . $httpCode . '): ' . $message);
        }

        return is_array($data) ? $data : [];
    }

    private function getOrder(int $orderId): array
    {
        $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new \RuntimeException('Order not found.');
        }

        return $order;
    }
}
