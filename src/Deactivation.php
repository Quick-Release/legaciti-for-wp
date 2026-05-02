<?php

declare(strict_types=1);

namespace LegacitiForWp;

use LegacitiForWp\Debug\PluginLog;
use wp_clear_scheduled_hook;

final class Deactivation
{
    public function deactivate(): void
    {
        try {
            PluginLog::info('deactivation', 'Legaciti plugin deactivated', []);
        } catch (\Throwable $e) {
        }

        wp_clear_scheduled_hook('legaciti_sync_event');

        flush_rewrite_rules();
    }
}
