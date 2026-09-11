<?php

declare(strict_types=1);

namespace App;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isConfigured(): bool
    {
        return $this->publicKey() !== '' && $this->privateKey() !== '';
    }

    public function publicKey(): string
    {
        return trim((string) SettingsService::get('vapid_public_key', ''));
    }

    public function privateKey(): string
    {
        return trim((string) SettingsService::get('vapid_private_key', ''));
    }

    public function subject(): string
    {
        $subject = trim((string) SettingsService::get('vapid_subject', ''));
        if ($subject !== '') {
            return $subject;
        }
        $from = trim((string) SettingsService::get('smtp_from_email', ''));
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:' . $from;
        }
        return 'mailto:admin@abdumarket.local';
    }

    /**
     * @return array{publicKey:string,privateKey:string}
     */
    public function generateAndStoreKeys(?string $subject = null): array
    {
        $keys = VAPID::createVapidKeys();
        SettingsService::set('vapid_public_key', $keys['publicKey']);
        SettingsService::set('vapid_private_key', $keys['privateKey']);
        if ($subject !== null && trim($subject) !== '') {
            SettingsService::set('vapid_subject', trim($subject));
        } elseif (SettingsService::get('vapid_subject', '') === '') {
            SettingsService::set('vapid_subject', $this->subject());
        }
        SettingsService::flushCache();

        return [
            'publicKey' => $keys['publicKey'],
            'privateKey' => $keys['privateKey'],
        ];
    }

    public function saveSubscription(int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): void
    {
        if (!db_has_table('push_subscriptions') || $userId <= 0) {
            throw new \RuntimeException('Push subscriptions are not available yet. Run migrations.');
        }
        $endpoint = trim($endpoint);
        $p256dh = trim($p256dh);
        $auth = trim($auth);
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new \InvalidArgumentException('Invalid push subscription.');
        }

        db()->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                user_agent = VALUES(user_agent),
                updated_at = CURRENT_TIMESTAMP'
        )->execute([$userId, $endpoint, $p256dh, $auth, $userAgent]);
    }

    public function deleteSubscription(string $endpoint, ?int $userId = null): void
    {
        if (!db_has_table('push_subscriptions')) {
            return;
        }
        if ($userId !== null) {
            db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?')
                ->execute([trim($endpoint), $userId]);
            return;
        }
        db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([trim($endpoint)]);
    }

    /**
     * @param array{title?:string,body?:string,url?:string,tag?:string} $payload
     * @return array{sent:int,failed:int}
     */
    public function sendToAdmins(array $payload): array
    {
        if (!$this->isConfigured() || !db_has_table('push_subscriptions')) {
            return ['sent' => 0, 'failed' => 0];
        }

        $stmt = db()->query(
            "SELECT ps.*
             FROM push_subscriptions ps
             INNER JOIN users u ON u.id = ps.user_id
             WHERE u.role = 'admin'"
        );
        $rows = $stmt->fetchAll() ?: [];
        if ($rows === []) {
            return ['sent' => 0, 'failed' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->subject(),
                'publicKey' => $this->publicKey(),
                'privateKey' => $this->privateKey(),
            ],
        ]);

        $json = json_encode([
            'title' => (string) ($payload['title'] ?? 'Abdu Market'),
            'body' => (string) ($payload['body'] ?? ''),
            'url' => (string) ($payload['url'] ?? '/admin/index.php'),
            'tag' => (string) ($payload['tag'] ?? 'abdu-admin'),
        ], JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $subscription = Subscription::create([
                'endpoint' => (string) $row['endpoint'],
                'publicKey' => (string) $row['p256dh'],
                'authToken' => (string) $row['auth'],
            ]);
            $webPush->queueNotification($subscription, $json);
        }

        $sent = 0;
        $failed = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
                continue;
            }
            $failed++;
            $endpoint = $report->getRequest()?->getUri()?->__toString();
            $code = $report->getResponse()?->getStatusCode();
            if ($endpoint && in_array($code, [404, 410], true)) {
                $this->deleteSubscription($endpoint);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
