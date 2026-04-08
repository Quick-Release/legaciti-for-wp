<?php

declare(strict_types=1);

namespace LegacitiForWp\Database;

final class TableManager
{
    private const DB_VERSION = '1.0.0';

    private function getTableNames(): array
    {
        global $wpdb;

        return [
            'people' => $wpdb->prefix . 'leg_people',
            'publications' => $wpdb->prefix . 'leg_publications',
            'person_publications' => $wpdb->prefix . 'leg_person_publications',
        ];
    }

    public function createTables(): void
    {
        global $wpdb;

        $tables = $this->getTableNames();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE {$tables['people']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            external_id varchar(255) NOT NULL,
            first_name varchar(255) NOT NULL,
            last_name varchar(255) NOT NULL,
            nickname varchar(255) NOT NULL,
            email varchar(255) DEFAULT NULL,
            title varchar(255) DEFAULT NULL,
            bio longtext DEFAULT NULL,
            avatar_url varchar(500) DEFAULT NULL,
            raw_api_data longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            synced_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY external_id (external_id),
            UNIQUE KEY nickname (nickname),
            KEY status (status)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE {$tables['publications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            external_id varchar(255) NOT NULL,
            title varchar(500) NOT NULL,
            slug varchar(255) NOT NULL,
            abstract longtext DEFAULT NULL,
            publication_date date DEFAULT NULL,
            doi varchar(255) DEFAULT NULL,
            journal varchar(500) DEFAULT NULL,
            raw_api_data longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            synced_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY external_id (external_id),
            UNIQUE KEY slug (slug),
            KEY status (status)
        ) $charsetCollate;";

        $sql[] = "CREATE TABLE {$tables['person_publications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            person_id bigint(20) unsigned NOT NULL,
            publication_id bigint(20) unsigned NOT NULL,
            role varchar(100) DEFAULT NULL,
            position int(11) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY person_publication (person_id, publication_id),
            KEY person_id (person_id),
            KEY publication_id (publication_id)
        ) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('legaciti_db_version', self::DB_VERSION);
    }

    public function dropTables(): void
    {
        global $wpdb;

        foreach ($this->getTableNames() as $tableName) {
            $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $tableName));
        }
    }

    public function setDefaultOptions(): void
    {
        if (get_option('legaciti_settings') === false) {
            add_option('legaciti_settings', [
                'api_base_url' => 'https://api.legaciti.org',
                'api_key' => '',
                'sync_frequency' => 'daily',
                'url_prefix' => '',
            ]);
        }

        if (get_option('legaciti_db_version') === false) {
            add_option('legaciti_db_version', self::DB_VERSION);
        }
    }
}
