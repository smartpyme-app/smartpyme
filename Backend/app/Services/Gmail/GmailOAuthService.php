<?php

namespace App\Services\Gmail;

use App\Models\DteManagement\UserEmailAccount;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Log;

class GmailOAuthService
{
    /**
     * Get the Google OAuth2 authorization URL.
     *
     * @param int $userId
     * @param int $idEmpresa
     * @return string
     */
    public function getAuthorizationUrl(int $userId, int $idEmpresa): string
    {
        $client = $this->createClient();
        $state = base64_encode(json_encode([
            'user_id' => $userId,
            'id_empresa' => $idEmpresa,
        ]));
        $client->setState($state);
        return $client->createAuthUrl();
    }

    /**
     * Handle the OAuth2 callback from Google.
     *
     * @param string $code
     * @param string $state
     * @return UserEmailAccount
     * @throws \InvalidArgumentException
     */
    public function handleCallback(string $code, string $state): UserEmailAccount
    {
        $decoded = json_decode(base64_decode($state), true);
        if (!$decoded || !isset($decoded['user_id']) || !isset($decoded['id_empresa'])) {
            throw new \InvalidArgumentException('Invalid OAuth state parameter.');
        }

        $userId = (int) $decoded['user_id'];
        $idEmpresa = (int) $decoded['id_empresa'];

        $client = $this->createClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            Log::error('Gmail OAuth token error', ['token' => $token]);
            throw new \RuntimeException('Failed to exchange code for token: ' . ($token['error_description'] ?? $token['error']));
        }

        $client->setAccessToken($token);

        $email = $this->getUserEmailFromGoogle($client);

        $account = UserEmailAccount::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'id_empresa' => $idEmpresa,
                    'email' => $email,
                    'provider' => 'gmail',
                ],
                [
                    'user_id' => $userId,
                    'access_token' => $token['access_token'] ?? null,
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'token_expires_at' => isset($token['expires_in'])
                        ? now()->addSeconds($token['expires_in'])
                        : null,
                    'is_active' => true,
                ]
            );

        return $account;
    }

    /**
     * Refresh the access token for an account.
     *
     * @param UserEmailAccount $account
     * @return UserEmailAccount
     */
    public function refreshAccessToken(UserEmailAccount $account): UserEmailAccount
    {
        if ($account->provider !== 'gmail') {
            throw new \InvalidArgumentException('Account is not a Gmail account.');
        }

        $client = $this->createClient();
        $client->setAccessToken([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in' => 0,
        ]);

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($account->refresh_token);
            $token = $client->getAccessToken();

            $account->update([
                'access_token' => $token['access_token'] ?? null,
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds($token['expires_in'])
                    : null,
            ]);
        }

        return $account->fresh();
    }

    /**
     * Create and configure the Google API client.
     *
     * @return GoogleClient
     */
    protected function createClient(): GoogleClient
    {
        $redirectUri = config('services.gmail.redirect_uri')
            ?: (config('app.url') . '/api/email-accounts/gmail/callback');

        $client = new GoogleClient();
        $client->setClientId(config('services.gmail.client_id'));
        $client->setClientSecret(config('services.gmail.client_secret'));
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            Gmail::GMAIL_READONLY,
            'email',
            'https://www.googleapis.com/auth/userinfo.email',
        ]);

        return $client;
    }

    /**
     * Get user email from Gmail API (avoids needing People API).
     *
     * @param GoogleClient $client
     * @return string
     */
    protected function getUserEmailFromGoogle(GoogleClient $client): string
    {
        $gmail = new Gmail($client);
        $profile = $gmail->users->getProfile('me');
        $email = $profile->getEmailAddress();

        if (empty($email)) {
            throw new \RuntimeException('Could not retrieve email from Google.');
        }

        return $email;
    }
}
