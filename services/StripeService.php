<?php

declare(strict_types=1);

namespace App;

use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey((string) setting('stripe.secret_key'));
    }

    public function isConfigured(): bool
    {
        return setting('stripe.secret_key') !== '' && setting('stripe.publishable_key') !== '';
    }

    public function createCheckoutSession(int $orderId, array $lineItems, float $total, string $customerEmail): Session
    {
        $baseUrl = config('app.url');
        $order = $this->getOrder($orderId);

        return Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'customer_email' => $customerEmail,
            'line_items' => $lineItems,
            'metadata' => [
                'order_id' => (string) $orderId,
                'order_number' => $order['order_number'],
            ],
            'success_url' => $baseUrl . '/order-success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/checkout.php?cancelled=1',
        ]);
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        $secret = setting('stripe.webhook_secret');
        if ($secret === '') {
            throw new \RuntimeException('Stripe webhook secret not configured.');
        }

        $event = Webhook::constructEvent($payload, $signature, $secret);

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = (int) ($session->metadata->order_id ?? 0);
            if ($orderId > 0) {
                $stmt = db()->prepare(
                    'UPDATE orders SET status = ?, stripe_payment_intent = ?, stripe_session_id = ?, updated_at = NOW()
                     WHERE id = ? AND status = ?'
                );
                $stmt->execute(['paid', $session->payment_intent ?? null, $session->id, $orderId, 'pending']);
                send_order_confirmation_email($orderId);
            }
        }
    }

    public function fulfillSession(string $sessionId): ?array
    {
        $session = Session::retrieve($sessionId);
        if ($session->payment_status !== 'paid') {
            return null;
        }

        $orderId = (int) ($session->metadata->order_id ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $stmt = db()->prepare(
            'UPDATE orders SET status = ?, stripe_payment_intent = ?, stripe_session_id = ?, updated_at = NOW()
             WHERE id = ? AND status IN (?, ?)'
        );
        $stmt->execute(['paid', $session->payment_intent, $session->id, $orderId, 'pending', 'paid']);

        send_order_confirmation_email($orderId);

        return $this->getOrder($orderId);
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
