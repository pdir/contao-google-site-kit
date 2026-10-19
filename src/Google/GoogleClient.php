<?php

declare(strict_types=1);

/*
 * Google Site Kit bundle for Contao Open Source CMS
 *
 * Copyright (C) pdir GmbH <develop@pdir.de>
 */

namespace Pdir\SiteKitBundle\Google;

use Pdir\SiteKitBundle\Google\Exception\GoogleApiException;
use Pdir\SiteKitBundle\Google\Exception\NotConnectedException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * OAuth 2.0 (authorization code flow with offline access) and authorized
 * requests against the Google APIs. The refresh token is stored encrypted in
 * the database; access tokens are kept (encrypted) in the application cache.
 */
class GoogleClient
{
    public const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const ACCESS_TOKEN_CACHE_KEY = 'pdir_site_kit.access_token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CredentialProvider $credentials,
        private readonly SettingsStore $settings,
        private readonly Crypto $crypto,
        private readonly CacheInterface $cache,
    ) {
    }

    public function isConnected(): bool
    {
        return null !== $this->settings->getSecret(SettingsStore::REFRESH_TOKEN);
    }

    public function getAccountEmail(): string|null
    {
        return $this->settings->get(SettingsStore::ACCOUNT_EMAIL);
    }

    /**
     * @return list<string>
     */
    public function getGrantedScopes(): array
    {
        $scopes = trim((string) $this->settings->get(SettingsStore::SCOPES));

        return '' === $scopes ? [] : array_values(array_filter(explode(' ', $scopes)));
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->getGrantedScopes(), true);
    }

    /**
     * @param list<string> $extraScopes Requested in addition to self::SCOPES (incremental authorization)
     */
    public function getAuthorizationUrl(string $redirectUri, string $state, string|null $loginHint = null, array $extraScopes = []): string
    {
        $clientId = $this->credentials->getClientId();

        if (null === $clientId) {
            throw new NotConnectedException('No OAuth client ID configured.');
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', array_unique([...self::SCOPES, ...$extraScopes])),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ];

        if (null !== $loginHint && '' !== $loginHint) {
            $params['login_hint'] = $loginHint;
        }

        return self::AUTH_URL.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchanges the authorization code for tokens and stores the connection.
     */
    public function connect(string $code, string $redirectUri): void
    {
        $data = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if (empty($data['refresh_token'])) {
            throw new GoogleApiException('Google did not return a refresh token. Remove the app under https://myaccount.google.com/permissions and connect again.');
        }

        $this->settings->setSecret(SettingsStore::REFRESH_TOKEN, (string) $data['refresh_token']);
        $this->settings->set(SettingsStore::SCOPES, (string) ($data['scope'] ?? ''));
        $this->settings->set(SettingsStore::CONNECTED_AT, (string) time());
        $this->storeAccessToken($data);

        try {
            $userInfo = $this->request('GET', self::USERINFO_URL);
            $this->settings->set(SettingsStore::ACCOUNT_EMAIL, isset($userInfo['email']) ? (string) $userInfo['email'] : null);
        } catch (GoogleApiException) {
            $this->settings->set(SettingsStore::ACCOUNT_EMAIL, null);
        }
    }

    /**
     * Revokes the token at Google (best effort) and removes the stored connection.
     */
    public function disconnect(): void
    {
        $refreshToken = $this->settings->getSecret(SettingsStore::REFRESH_TOKEN);

        if (null !== $refreshToken) {
            try {
                $this->httpClient->request('POST', self::REVOKE_URL, ['body' => ['token' => $refreshToken]])->getStatusCode();
            } catch (HttpClientException) {
                // Ignore, the local connection is removed anyway
            }
        }

        $this->settings->clearConnection();
        $this->cache->delete(self::ACCESS_TOKEN_CACHE_KEY);
    }

    /**
     * Sends an authorized request and returns the decoded JSON response.
     *
     * @param array<string, mixed> $options Symfony HttpClient options
     *
     * @return array<mixed>
     */
    public function request(string $method, string $url, array $options = []): array
    {
        $options['headers']['Authorization'] = 'Bearer '.$this->getAccessToken();
        $response = $this->send($method, $url, $options);

        // The access token might have been revoked or expired early: refresh once
        if (401 === $this->statusCode($response)) {
            $this->cache->delete(self::ACCESS_TOKEN_CACHE_KEY);
            $options['headers']['Authorization'] = 'Bearer '.$this->getAccessToken();
            $response = $this->send($method, $url, $options);
        }

        return $this->decode($response);
    }

    /**
     * Sends an unauthenticated request (e.g. PageSpeed Insights with an API key).
     *
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    public function publicRequest(string $method, string $url, array $options = []): array
    {
        return $this->decode($this->send($method, $url, $options));
    }

    private function getAccessToken(): string
    {
        $encrypted = $this->cache->get(
            self::ACCESS_TOKEN_CACHE_KEY,
            function (ItemInterface $item): string {
                $refreshToken = $this->settings->getSecret(SettingsStore::REFRESH_TOKEN);

                if (null === $refreshToken) {
                    throw new NotConnectedException('Site Kit is not connected to a Google account.');
                }

                try {
                    $data = $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
                } catch (GoogleApiException $e) {
                    if ('invalid_grant' === $e->getReason()) {
                        // The user revoked access or the token expired: the connection is gone
                        $this->settings->clearConnection();

                        throw new NotConnectedException('Google access was revoked or has expired. Please connect again.', 401, 'invalid_grant', $e);
                    }

                    throw $e;
                }

                $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 3600) - 120));

                return $this->crypto->encrypt((string) $data['access_token']);
            },
        );

        $token = $this->crypto->decrypt($encrypted);

        if (null === $token) {
            $this->cache->delete(self::ACCESS_TOKEN_CACHE_KEY);

            throw new GoogleApiException('Could not decrypt the cached access token.');
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeAccessToken(array $data): void
    {
        if (empty($data['access_token'])) {
            return;
        }

        $this->cache->delete(self::ACCESS_TOKEN_CACHE_KEY);
        $this->cache->get(
            self::ACCESS_TOKEN_CACHE_KEY,
            function (ItemInterface $item) use ($data): string {
                $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 3600) - 120));

                return $this->crypto->encrypt((string) $data['access_token']);
            },
        );
    }

    /**
     * @param array<string, string> $params
     *
     * @return array<mixed>
     */
    private function tokenRequest(array $params): array
    {
        $clientId = $this->credentials->getClientId();
        $clientSecret = $this->credentials->getClientSecret();

        if (null === $clientId || null === $clientSecret) {
            throw new NotConnectedException('No OAuth client credentials configured.');
        }

        $params['client_id'] = $clientId;
        $params['client_secret'] = $clientSecret;

        $data = $this->decode($this->send('POST', self::TOKEN_URL, ['body' => $params]));

        if (empty($data['access_token'])) {
            throw new GoogleApiException('The token response from Google did not contain an access token.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $url, array $options): ResponseInterface
    {
        $options['timeout'] ??= 20;
        $options['headers']['Accept'] = 'application/json';

        try {
            return $this->httpClient->request($method, $url, $options);
        } catch (HttpClientException $e) {
            throw new GoogleApiException('Could not reach Google: '.$e->getMessage(), 0, '', $e);
        }
    }

    private function statusCode(ResponseInterface $response): int
    {
        try {
            return $response->getStatusCode();
        } catch (HttpClientException $e) {
            throw new GoogleApiException('Could not reach Google: '.$e->getMessage(), 0, '', $e);
        }
    }

    /**
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $status = $this->statusCode($response);

        try {
            $content = $response->getContent(false);
        } catch (HttpClientException $e) {
            throw new GoogleApiException('Could not reach Google: '.$e->getMessage(), 0, '', $e);
        }

        // Some endpoints (e.g. adding a Search Console property) return no body
        if ($status < 400 && '' === trim($content)) {
            return [];
        }

        $data = json_decode($content, true);

        if ($status >= 400) {
            [$message, $reason] = $this->extractError(\is_array($data) ? $data : []);

            throw new GoogleApiException($message ?: \sprintf('Google returned HTTP status %d.', $status), $status, $reason);
        }

        if (!\is_array($data)) {
            throw new GoogleApiException('Google returned an invalid response.', $status);
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array{0: string, 1: string}
     */
    private function extractError(array $data): array
    {
        // Google API errors: {"error": {"code": 403, "message": "...", "status": "PERMISSION_DENIED"}}
        if (isset($data['error']) && \is_array($data['error'])) {
            return [(string) ($data['error']['message'] ?? ''), (string) ($data['error']['status'] ?? '')];
        }

        // OAuth errors: {"error": "invalid_grant", "error_description": "..."}
        if (isset($data['error']) && \is_string($data['error'])) {
            return [(string) ($data['error_description'] ?? $data['error']), $data['error']];
        }

        return ['', ''];
    }
}
