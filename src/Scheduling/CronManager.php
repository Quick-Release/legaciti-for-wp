<?php

declare(strict_types=1);

namespace LegacitiForWp\Scheduling;

use LegacitiForWp\Api\SyncService;

final class CronManager
{
    public function __construct(
        private readonly SyncService $syncService,
    ) {
    }

    public function register(): void
    {
        add_action('legaciti_sync_event', [$this, 'handleSyncEvent']);
        add_action('wp_ajax_legaciti_manual_sync', [$this, 'handleManualSync']);

        add_filter('cron_schedules', [$this, 'addCronIntervals']);

        $settings = get_option('legaciti_settings', []);
        $frequency = $settings['sync_frequency'] ?? 'daily';

        if ($frequency !== 'manual' && ! wp_next_scheduled('legaciti_sync_event')) {
            wp_schedule_event(time(), $frequency, 'legaciti_sync_event');
        }
    }

    public function addCronIntervals(array $schedules): array
    {
        $schedules['twicedaily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Twice Daily', 'legaciti-for-wp'),
        ];

        return $schedules;
    }

    public function handleSyncEvent(): void
    {
        $this->syncService->sync();
    }

    public function handleManualSync(): void
    {
        check_ajax_referer('legaciti_manual_sync', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }

        $result = $this->syncService->sync();

        wp_send_json_success($result->toArray());
    }
}
