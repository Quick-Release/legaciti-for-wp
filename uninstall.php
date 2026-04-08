<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'leg_people',
    $wpdb->prefix . 'leg_publications',
    $wpdb->prefix . 'leg_person_publications',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

delete_option('legaciti_settings');
delete_option('legaciti_db_version');

wp_clear_scheduled_hook('legaciti_sync_event');
