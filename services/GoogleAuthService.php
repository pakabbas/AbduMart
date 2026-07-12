<?php

declare(strict_types=1);

namespace App;

use League\OAuth2\Client\Provider\Google;

class GoogleAuthService
{
    private Google $provider;

    public function __construct()
    {
        $redirectUri = rtrim((string) config('app.url'), '/') . '/auth/google-callback.php';
        $this->provider = new Google([
            'clientId' => (string) SettingsService::get('google_client_id', ''),
            'clientSecret' => (string) SettingsService::get('google_client_secret', ''),
            'redirectUri' => $redirectUri,
        ]);
    }

    public function isConfigured(): bool
    {
        return SettingsService::isGroupConfigured('google');
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);
    }

    public function fetchUser(string $code): array
    {
        $token = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
        $owner = $this->provider->getResourceOwner($token);
        $data = $owner->toArray();

        return [
            'google_id' => (string) ($data['sub'] ?? $owner->getId()),
            'email' => strtolower((string) ($data['email'] ?? '')),
            'first_name' => (string) ($data['given_name'] ?? 'Customer'),
            'last_name' => (string) ($data['family_name'] ?? ''),
            'avatar' => (string) ($data['picture'] ?? ''),
        ];
    }
}
