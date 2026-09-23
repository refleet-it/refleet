<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for interacting with Google OAuth API.
 */
final readonly class GoogleOAuthClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private string $googleClientId,
        #[Autowire('%env(GOOGLE_CLIENT_SECRET)%')]
        private string $googleClientSecret,
    ) {
    }

    /**
     * Verifies Google ID token and returns user data.
     *
     * @return array{email: string, email_verified: bool, sub: string, name: string, picture: string}
     *
     * @throws \RuntimeException if token is invalid
     */
    public function verifyIdToken(string $idToken): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
                'query' => [
                    'id_token' => $idToken,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException('Invalid Google ID token');
            }

            $data = $response->toArray();

            // Verify the token is for our application
            if (!isset($data['aud']) || $data['aud'] !== $this->googleClientId) {
                throw new \RuntimeException('ID token not intended for this application');
            }

            // Verify email is verified
            if (!isset($data['email_verified']) || true !== $data['email_verified']) {
                throw new \RuntimeException('Email not verified by Google');
            }

            if (!isset($data['email'], $data['sub'])) {
                throw new \RuntimeException('Missing required fields in token data');
            }

            return [
                'email' => $data['email'],
                'email_verified' => $data['email_verified'],
                'sub' => $data['sub'], // Google user ID
                'name' => $data['name'] ?? '',
                'picture' => $data['picture'] ?? '',
            ];
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('Failed to verify Google ID token: '.$throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * Exchanges authorization code for access token and ID token.
     *
     * @return array{access_token: string, id_token: string, expires_in: int, refresh_token?: string}
     *
     * @throws \RuntimeException if exchange fails
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'body' => [
                    'code' => $code,
                    'client_id' => $this->googleClientId,
                    'client_secret' => $this->googleClientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException('Failed to exchange authorization code');
            }

            $data = $response->toArray();

            if (!isset($data['access_token'], $data['id_token'], $data['expires_in'])) {
                throw new \RuntimeException('Missing required fields in token response');
            }

            return [
                'access_token' => $data['access_token'],
                'id_token' => $data['id_token'],
                'expires_in' => (int) $data['expires_in'],
                'refresh_token' => $data['refresh_token'] ?? null,
            ];
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('Failed to exchange code for token: '.$throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * Generates the Google OAuth authorization URL.
     */
    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        $params = \http_build_query([
            'client_id' => $this->googleClientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$params;
    }
}
