<?php

declare(strict_types=1);

namespace LegacitiForWp\Api;

use LegacitiForWp\Debug\PluginLog;

final class Client
{
    /**
     * Build common auth/request headers expected by the Consumer/Integrations APIs.
     *
     * @return array<string, string>
     */
    private function buildHeaders(string $apiKey): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($apiKey !== '') {
            $headers['X-API-Key'] = $apiKey;
        }

        return $headers;
    }

    /**
     * @return array{code: string|null, preview: string}
     */
    private function parseErrorBody(string $body): array
    {
        $preview = strlen($body) > 2000 ? substr($body, 0, 2000) . '…' : $body;

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['code' => null, 'preview' => $preview];
        }

        if (! is_array($decoded)) {
            return ['code' => null, 'preview' => $preview];
        }

        $apiCode = $decoded['code'] ?? null;

        return [
            'code' => is_string($apiCode) ? $apiCode : null,
            'preview' => $preview,
        ];
    }

    private function explainAuthFailure(int $httpCode, ?string $apiCode): string
    {
        if ($httpCode === 401 && $apiCode === 'missing_api_key') {
            return 'Missing API key header. Ensure outbound requests include X-API-Key.';
        }

        if ($httpCode === 401 && $apiCode === 'invalid_installation_credential') {
            return 'Invalid installation credential (X-API-Key).';
        }

        if ($httpCode === 403 && $apiCode === 'installation_scope_denied') {
            return 'Credential is valid but missing required scope (for example people.read).';
        }

        if ($httpCode === 403 && in_array($apiCode, ['inactive_installation', 'inactive_installation_credential'], true)) {
            return 'Installation or credential is inactive.';
        }

        if ($httpCode === 403 && $apiCode === 'origin_not_verified') {
            return 'Installation origin is not verified yet.';
        }

        return 'Authentication/authorization failed.';
    }

    private function getSettings(): array
    {
        return get_option('legaciti_settings', [
            'api_base_url' => 'https://api.legaciti.org',
            'api_key' => '',
        ]);
    }

    public function get(string $endpoint, array $params = []): array
    {
        $settings = $this->getSettings();
        $baseUrl = rtrim($settings['api_base_url'] ?? 'https://api.legaciti.org', '/');
        $url = $baseUrl . '/' . ltrim($endpoint, '/');

        if (count($params) > 0) {
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => $this->buildHeaders((string) ($settings['api_key'] ?? '')),
        ]);

        if (is_wp_error($response)) {
            $explained = $this->explainTransportError($response);
            $msg = 'API request failed: ' . $explained;
            PluginLog::error('api_client', $msg, [
                'endpoint' => $endpoint,
                'url' => $url,
                'wp_error_code' => $response->get_error_code(),
            ]);

            throw new \RuntimeException($msg);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            $parsedError = $this->parseErrorBody($body);
            PluginLog::error('api_client', "API returned HTTP {$code}", [
                'endpoint' => $endpoint,
                'url' => $url,
                'code' => $code,
                'api_code' => $parsedError['code'],
                'body_preview' => $parsedError['preview'],
            ]);

            throw new \RuntimeException("API returned HTTP {$code}");
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            PluginLog::exception('api_client', 'Invalid JSON from API', $e, [
                'endpoint' => $endpoint,
                'url' => $url,
                'body_preview' => strlen($body) > 1000 ? substr($body, 0, 1000) . '…' : $body,
            ]);
            throw $e;
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Probe the remote API with arbitrary credentials (does not read saved settings).
     *
     * @return array{valid: bool, message: string}
     */
    public function validateCredentials(string $baseUrl, string $apiKey): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            return ['valid' => false, 'message' => 'API base URL is required.'];
        }

        if ($apiKey === '') {
            return ['valid' => false, 'message' => 'API key is required.'];
        }

        $url = $baseUrl . '/integrations/v1/installation';

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => $this->buildHeaders($apiKey),
        ]);

        if (is_wp_error($response)) {
            $err = $this->explainTransportError($response);
            PluginLog::warning('api_client', 'Credential check: transport error', ['message' => $err]);

            return ['valid' => false, 'message' => $err];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            PluginLog::info('api_client', 'Credential check passed', ['http' => $code]);

            return ['valid' => true, 'message' => 'API key is valid. Connection OK.'];
        }

        $body = wp_remote_retrieve_body($response);
        $preview = strlen($body) > 500 ? substr($body, 0, 500) . '…' : $body;
        PluginLog::warning('api_client', 'Credential check failed', [
            'http' => $code,
            'body_preview' => $preview,
        ]);

        if ($code === 401 || $code === 403) {
            $body = wp_remote_retrieve_body($response);
            $parsedError = $this->parseErrorBody($body);
            $detail = $this->explainAuthFailure($code, $parsedError['code']);

            return ['valid' => false, 'message' => $detail . ' (HTTP ' . $code . ').'];
        }

        if ($code === 404) {
            return ['valid' => false, 'message' => 'API endpoint not found—check the API base URL (HTTP 404).'];
        }

        return ['valid' => false, 'message' => 'Could not validate (HTTP ' . $code . ').'];
    }

    /**
     * Probe reachability of the Consumer API using saved Settings (same URL and key as sync).
     *
     * @return array{
     *   ok: bool,
     *   level: string,
     *   message: string,
     *   http_code: int|null,
     *   url: string,
     *   used_api_key: bool
     * }
     */
    public function checkConnectivityFromSettings(): array
    {
        $settings = $this->getSettings();
        $baseUrl = rtrim($settings['api_base_url'] ?? 'https://api.legaciti.org', '/');
        $key = (string) ($settings['api_key'] ?? '');
        $url = $baseUrl . '/v1/people?' . http_build_query([
            'page' => 1,
            'per_page' => 1,
        ]);

        $headers = $this->buildHeaders($key);
        $usedKey = $key !== '';

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => $this->explainTransportError($response),
                'http_code' => null,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return [
                'ok' => true,
                'level' => 'success',
                'message' => 'Connection OK. The API answered with HTTP ' . $code . ' (saved key can read /v1/people).',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code === 401 || $code === 403) {
            $body = wp_remote_retrieve_body($response);
            $parsedError = $this->parseErrorBody($body);

            $hint = $usedKey
                ? ' ' . $this->explainAuthFailure($code, $parsedError['code'])
                : ' Save an API key under Legaciti → Settings—the route requires authentication.';

            return [
                'ok' => true,
                'level' => 'warning',
                'message' => 'Host is reachable (HTTP ' . $code . ').' . $hint,
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code === 404) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Connected but got HTTP 404 for /v1/people. Fix the API base URL under Settings.',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code === 429) {
            return [
                'ok' => true,
                'level' => 'warning',
                'message' => 'Server responded with HTTP 429 (rate limited). Network path is fine—retry later.',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code >= 500) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Server error HTTP ' . $code . '. The host answered; the API may be unavailable.',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        return [
            'ok' => false,
            'level' => 'error',
            'message' => 'Unexpected HTTP ' . $code . ' from the API.',
            'http_code' => $code,
            'url' => $url,
            'used_api_key' => $usedKey,
        ];
    }

    /**
     * List people from the Consumer API (paginated).
     *
     * @return array{people?: list<array<string, mixed>>, data?: list<array<string, mixed>>, page?: int, pages?: int, per_page?: int, total?: int, next_page_url?: string|null}
     */
    public function getPeople(int $page = 1, int $perPage = 100): array
    {
        $perPage = min(100, max(1, $perPage));

        return $this->get('v1/people', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * List publications from the Consumer API (paginated).
     *
     * @return array<string, mixed>
     */
    public function getPublications(int $page = 1, int $perPage = 100): array
    {
        $perPage = min(100, max(1, $perPage));

        return $this->get('v1/publications', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Probe reachability of the publications Consumer API route using saved Settings.
     *
     * @return array{
     *   ok: bool,
     *   level: string,
     *   message: string,
     *   http_code: int|null,
     *   url: string,
     *   used_api_key: bool
     * }
     */
    public function checkPublicationsConnectivityFromSettings(): array
    {
        $settings = $this->getSettings();
        $baseUrl = rtrim($settings['api_base_url'] ?? 'https://api.legaciti.org', '/');
        $key = (string) ($settings['api_key'] ?? '');
        $url = $baseUrl . '/v1/publications?' . http_build_query([
            'page' => 1,
            'per_page' => 1,
        ]);

        $headers = $this->buildHeaders($key);
        $usedKey = $key !== '';

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => $this->explainTransportError($response),
                'http_code' => null,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return [
                'ok' => true,
                'level' => 'success',
                'message' => 'Connection OK. The API answered with HTTP ' . $code . ' (saved key can read /v1/publications).',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code === 401 || $code === 403) {
            $body = wp_remote_retrieve_body($response);
            $parsedError = $this->parseErrorBody($body);

            $hint = $usedKey
                ? ' ' . $this->explainAuthFailure($code, $parsedError['code'])
                : ' Save an API key under Legaciti → Settings—the route requires authentication.';

            return [
                'ok' => true,
                'level' => 'warning',
                'message' => 'Host is reachable (HTTP ' . $code . ').' . $hint,
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code === 404) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Connected but got HTTP 404 for /v1/publications. Fix the API base URL under Settings.',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code === 429) {
            return [
                'ok' => true,
                'level' => 'warning',
                'message' => 'Server responded with HTTP 429 (rate limited). Network path is fine—retry later.',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        if ($code >= 500) {
            return [
                'ok' => false,
                'level' => 'error',
                'message' => 'Server error HTTP ' . $code . '. The host answered; the API may be unavailable.',
                'http_code' => $code,
                'url' => $url,
                'used_api_key' => $usedKey,
            ];
        }

        return [
            'ok' => false,
            'level' => 'error',
            'message' => 'Unexpected HTTP ' . $code . ' from the API.',
            'http_code' => $code,
            'url' => $url,
            'used_api_key' => $usedKey,
        ];
    }

    /**
     * Map WordPress HTTP transport errors to messages that help with common dev/prod issues (DNS, Docker, etc.).
     */
    private function explainTransportError(\WP_Error $error): string
    {
        $raw = $error->get_error_message();
        $low = strtolower($raw);

        if (str_contains($low, 'could not resolve host') || preg_match('/\bcurl error\s*6\b/i', $raw) !== 0) {
            return $raw
                . ' (DNS: this server cannot resolve the API hostname. If WordPress runs in Docker, fix container DNS'
                . ' (e.g. /etc/resolv.conf, docker-compose dns:, or use a base URL reachable inside the container'
                . ' such as host.docker.internal or your LAN IP).)';
        }

        if (preg_match('/\bcurl error\s*7\b/i', $raw) !== 0 || str_contains($low, 'connection refused')) {
            return $raw . ' (Connection refused: wrong host/port or nothing listening on the API URL.)';
        }

        if (preg_match('/\bcurl error\s*28\b/i', $raw) !== 0 || str_contains($low, 'timed out') || str_contains($low, 'timeout')) {
            return $raw . ' (Timeout: firewall, VPN, or API unavailable.)';
        }

        if (str_contains($low, 'ssl') || str_contains($low, 'certificate')) {
            return $raw . ' (TLS: certificate or HTTPS misconfiguration on this server.)';
        }

        return $raw;
    }
}
