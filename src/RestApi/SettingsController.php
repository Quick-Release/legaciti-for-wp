<?php

declare(strict_types=1);

namespace LegacitiForWp\RestApi;

use LegacitiForWp\Api\SyncService;

final class SettingsController
{
    private const NAMESPACE = 'legaciti/v1';

    public function __construct(
        private readonly SyncService $syncService,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSettings'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'updateSettings'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
                'args' => [
                    'api_base_url' => [
                        'type' => 'string',
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'api_key' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'sync_frequency' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'url_prefix' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'remove_on_uninstall' => [
                        'type' => 'boolean',
                        'sanitize_callback' => function ($val): bool {
                            return ! empty($val);
                        },
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/sync', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'triggerSync'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
            ],
        ]);
    }

    public function getSettings(): \WP_REST_Response
    {
        $settings = get_option('legaciti_settings', []);

        return new \WP_REST_Response([
            'api_base_url' => $settings['api_base_url'] ?? 'https://api.legaciti.org',
            'api_key' => $settings['api_key'] ?? '',
            'sync_frequency' => $settings['sync_frequency'] ?? 'daily',
            'url_prefix' => $settings['url_prefix'] ?? '',
            'remove_on_uninstall' => ! empty($settings['remove_on_uninstall']),
            'last_sync' => $settings['last_sync'] ?? null,
        ]);
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $existing = get_option('legaciti_settings', []);

        $updated = array_merge($existing, [
            'api_base_url' => $request->get_param('api_base_url') ?? $existing['api_base_url'] ?? 'https://api.legaciti.org',
            'api_key' => $request->get_param('api_key') ?? $existing['api_key'] ?? '',
            'sync_frequency' => in_array($request->get_param('sync_frequency'), ['hourly', 'twicedaily', 'daily', 'manual'], true)
                ? $request->get_param('sync_frequency')
                : ($existing['sync_frequency'] ?? 'daily'),
            'url_prefix' => $request->get_param('url_prefix') ?? $existing['url_prefix'] ?? '',
            'remove_on_uninstall' => $request->get_param('remove_on_uninstall') ?? ($existing['remove_on_uninstall'] ?? false),
        ]);

        update_option('legaciti_settings', $updated);

        return new \WP_REST_Response(['saved' => true]);
    }

    public function triggerSync(): \WP_REST_Response
    {
        $result = $this->syncService->sync();

        return new \WP_REST_Response($result->toArray());
    }
}
