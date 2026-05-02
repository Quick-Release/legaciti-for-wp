<?php

declare(strict_types=1);

namespace LegacitiForWp\Api;

final class Client
{
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
            'headers' => [
                'Authorization' => 'Bearer ' . ($settings['api_key'] ?? ''),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('API request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("API returned HTTP {$code}");
        }

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

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

        $url = $baseUrl . '/people?' . http_build_query(['page' => 1]);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['valid' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return ['valid' => true, 'message' => 'API key is valid. Connection OK.'];
        }

        if ($code === 401 || $code === 403) {
            return ['valid' => false, 'message' => 'Invalid API key or insufficient permissions (HTTP ' . $code . ').'];
        }

        if ($code === 404) {
            return ['valid' => false, 'message' => 'API endpoint not found—check the API base URL (HTTP 404).'];
        }

        return ['valid' => false, 'message' => 'Could not validate (HTTP ' . $code . ').'];
    }

    public function getPeople(int $page = 1): array
    {
        return $this->get('/people', ['page' => $page]);
    }

    public function getPublications(int $page = 1): array
    {
        return $this->get('/publications', ['page' => $page]);
    }
}
