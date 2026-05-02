<?php

declare(strict_types=1);

namespace LegacitiForWp;

use LegacitiForWp\Database\TableManager;
use LegacitiForWp\Debug\PluginLog;

if (! defined('LEGACITI_PLUGIN_FILE')) {
    define('LEGACITI_PLUGIN_FILE', dirname(__DIR__) . '/legaciti-for-wp.php');
}

if (! defined('LEGACITI_PLUGIN_DIR')) {
    define('LEGACITI_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (! defined('LEGACITI_PLUGIN_VERSION')) {
    define('LEGACITI_PLUGIN_VERSION', '0.1.0');
}

final class Activation
{
    public function __construct(
        private readonly TableManager $tableManager,
    ) {
    }

    public function activate(): void
    {
        $this->tableManager->createTables();
        $this->tableManager->setDefaultOptions();

        try {
            PluginLog::info('activation', 'Legaciti plugin activated', [
                'db_version' => get_option('legaciti_db_version'),
            ]);
        } catch (\Throwable $e) {
            // Avoid blocking activation if logging fails (e.g. missing table edge case).
        }

        flush_rewrite_rules();
    }
}
