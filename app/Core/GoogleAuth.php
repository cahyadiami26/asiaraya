<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Google Workspace (OAuth 2.0 / OpenID Connect) sign-in.
 *
 * Uses the authorisation-code flow against Google's endpoints with PHP streams
 * (curl when available). Employees are matched on their registered company
 * e-mail address, so the Google account and the HRIS employee record are
 * linked by `employees.email`.
 *
 * Configure client id/secret either in app/config/env.php or in
 * Pengaturan → Integrasi Google (stored in settings).
 */
final class GoogleAuth
{
    private const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO       = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function clientId(): string
    {
        return (string) (Settings::get('google_client_id') ?: config('google_client_id', ''));
    }

    public static function clientSecret(): string
    {
        return (string) (Settings::get('google_client_secret') ?: config('google_client_secret', ''));
    }

    public static function redirectUri(): string
    {
        $configured = (string) config('google_redirect_uri', '');
        if ($configured !== '') {
            return $configured;
        }
        return url('login/google/callback');
    }

    public static function isConfigured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function authUrl(string $state): string
    {
        $params = [
            'client_id'     => self::clientId(),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'hd'            => self::workspaceDomain(),
        ];
        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /** Restrict to the company Workspace domain when configured. */
    private static function workspaceDomain(): string
    {
        $email = (string) (Settings::get('company_email') ?: '');
        $domain = substr((string) strrchr($email, '@'), 1);
        return $domain ?: '';
    }

    public static function fetchProfile(string $code): ?array
    {
        $token = self::requestToken($code);
        if (!$token || empty($token['access_token'])) {
            return null;
        }

        $response = self::http(self::USERINFO, [
            'headers' => ['Authorization: Bearer ' . $token['access_token']],
        ]);
        $profile = json_decode((string) $response, true);
        if (!is_array($profile) || empty($profile['email'])) {
            return null;
        }

        $profile['email_verified'] = !empty($profile['email_verified']) || !empty($profile['verified_email']);
        return $profile;
    }

    private static function requestToken(string $code): ?array
    {
        $payload = http_build_query([
            'code'          => $code,
            'client_id'     => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);
        $response = self::http(self::TOKEN_ENDPOINT, [
            'method'  => 'POST',
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body'    => $payload,
        ]);
        $json = json_decode((string) $response, true);
        return is_array($json) ? $json : null;
    }

    private static function http(string $url, array $options = []): ?string
    {
        $method = $options['method'] ?? 'GET';
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }
            $result = curl_exec($ch);
            curl_close($ch);
            return is_string($result) ? $result : null;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body ?? '',
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        return is_string($result) ? $result : null;
    }
}
