<?php

declare(strict_types=1);

namespace LegacitiForWp;

use wp_clear_scheduled_hook;

final class Deactivation
{
    public function deactivate(): void
    {
        wp_clear_scheduled_hook('legaciti_sync_event');

        flush_rewrite_rules();
    }
}
