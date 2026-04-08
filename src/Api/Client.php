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

    public function getPeople(int $page = 1): array
    {
        return $this->get('/people', ['page' => $page]);
    }

    public function getPublications(int $page = 1): array
    {
        return $this->get('/publications', ['page' => $page]);
    }
}
