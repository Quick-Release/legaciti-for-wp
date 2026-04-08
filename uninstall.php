<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('legaciti_sync_event');

$settings = get_option('legaciti_settings', []);
$shouldRemove = ! empty($settings['remove_on_uninstall']);

delete_option('legaciti_settings');
delete_option('legaciti_db_version');

if ($shouldRemove) {
    global $wpdb;

    $tables = [
        $wpdb->prefix . 'leg_people',
        $wpdb->prefix . 'leg_publications',
        $wpdb->prefix . 'leg_person_publications',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
