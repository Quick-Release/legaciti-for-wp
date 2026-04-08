<?php

declare(strict_types=1);

namespace LegacitiForWp\Admin;

use LegacitiForWp\Api\SyncService;

final class SettingsPage
{
    public function __construct(
        private readonly SyncService $syncService,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            page_title: __('Legaciti', 'legaciti-for-wp'),
            menu_title: __('Legaciti', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-settings',
            callback: [$this, 'renderPage'],
            icon_url: 'dashicons-database-import',
            position: 80,
        );
    }

    public function registerSettings(): void
    {
        register_setting('legaciti_settings_group', 'legaciti_settings', [
            'sanitize_callback' => [$this, 'sanitizeSettings'],
        ]);

        add_settings_section(
            id: 'legaciti_api_section',
            title: __('API Configuration', 'legaciti-for-wp'),
            callback: '__return_null',
            page: 'legaciti-settings',
        );

        add_settings_field(
            id: 'legaciti_api_base_url',
            title: __('API Base URL', 'legaciti-for-wp'),
            callback: [$this, 'renderApiBaseUrlField'],
            page: 'legaciti-settings',
            section: 'legaciti_api_section',
        );

        add_settings_field(
            id: 'legaciti_api_key',
            title: __('API Key', 'legaciti-for-wp'),
            callback: [$this, 'renderApiKeyField'],
            page: 'legaciti-settings',
            section: 'legaciti_api_section',
        );

        add_settings_field(
            id: 'legaciti_sync_frequency',
            title: __('Sync Frequency', 'legaciti-for-wp'),
            callback: [$this, 'renderSyncFrequencyField'],
            page: 'legaciti-settings',
            section: 'legaciti_api_section',
        );

        add_settings_field(
            id: 'legaciti_url_prefix',
            title: __('People URL Prefix', 'legaciti-for-wp'),
            callback: [$this, 'renderUrlPrefixField'],
            page: 'legaciti-settings',
            section: 'legaciti_api_section',
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_legaciti-settings') {
            return;
        }

        wp_enqueue_style(
            handle: 'legaciti-admin-css',
            src: LEGACITI_PLUGIN_DIR . 'assets/css/admin.css',
            deps: [],
            ver: LEGACITI_PLUGIN_VERSION,
        );

        wp_enqueue_script(
            handle: 'legaciti-admin-js',
            src: LEGACITI_PLUGIN_DIR . 'assets/js/admin.js',
            deps: ['jquery'],
            ver: LEGACITI_PLUGIN_VERSION,
            args: true,
        );

        wp_localize_script('legaciti-admin-js', 'legacitiAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('legaciti_manual_sync'),
        ]);
    }

    public function renderPage(): void
    {
        $settings = get_option('legaciti_settings', []);
        $lastSync = $settings['last_sync'] ?? __('Never', 'legaciti-for-wp');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Legaciti Settings', 'legaciti-for-wp'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('legaciti_settings_group');
                do_settings_sections('legaciti-settings');
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php echo esc_html__('Sync Status', 'legaciti-for-wp'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('Last Sync', 'legaciti-for-wp'); ?></th>
                    <td><?php echo esc_html($lastSync); ?></td>
                </tr>
            </table>

            <p>
                <button type="button" id="legaciti-manual-sync" class="button button-primary">
                    <?php echo esc_html__('Sync Now', 'legaciti-for-wp'); ?>
                </button>
                <span id="legaciti-sync-status"></span>
            </p>

            <div id="legaciti-sync-result" style="display:none; margin-top: 15px;">
                <h3><?php echo esc_html__('Sync Result', 'legaciti-for-wp'); ?></h3>
                <pre id="legaciti-sync-log" style="background:#f0f0f1; padding:10px; max-height:300px; overflow:auto;"></pre>
            </div>
        </div>
        <?php
    }

    public function renderApiBaseUrlField(): void
    {
        $settings = get_option('legaciti_settings', []);
        $value = esc_attr($settings['api_base_url'] ?? 'https://api.legaciti.org');
        printf(
            '<input type="url" name="legaciti_settings[api_base_url]" value="%s" class="regular-text" placeholder="https://api.legaciti.org" />',
            $value
        );
    }

    public function renderApiKeyField(): void
    {
        $settings = get_option('legaciti_settings', []);
        $value = esc_attr($settings['api_key'] ?? '');
        printf(
            '<input type="password" name="legaciti_settings[api_key]" value="%s" class="regular-text" autocomplete="off" />',
            $value
        );
    }

    public function renderSyncFrequencyField(): void
    {
        $settings = get_option('legaciti_settings', []);
        $current = $settings['sync_frequency'] ?? 'daily';
        $options = [
            'hourly' => __('Hourly', 'legaciti-for-wp'),
            'twicedaily' => __('Twice Daily', 'legaciti-for-wp'),
            'daily' => __('Daily', 'legaciti-for-wp'),
            'manual' => __('Manual Only', 'legaciti-for-wp'),
        ];

        echo '<select name="legaciti_settings[sync_frequency]">';
        foreach ($options as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($current, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function renderUrlPrefixField(): void
    {
        $settings = get_option('legaciti_settings', []);
        $value = esc_attr($settings['url_prefix'] ?? '');
        printf(
            '<input type="text" name="legaciti_settings[url_prefix]" value="%s" class="regular-text" placeholder="Leave empty for root-level slugs (/asoares)" />',
            $value
        );
        echo '<p class="description">People profile URLs. Empty = <code>/asoares</code>, set "people" = <code>/people/asoares</code>. Requires flush of rewrite rules after change.</p>';
    }

    public function sanitizeSettings(array $input): array
    {
        return [
            'api_base_url' => esc_url_raw($input['api_base_url'] ?? 'https://api.legaciti.org'),
            'api_key' => sanitize_text_field($input['api_key'] ?? ''),
            'sync_frequency' => in_array($input['sync_frequency'] ?? 'daily', ['hourly', 'twicedaily', 'daily', 'manual'], true)
                ? $input['sync_frequency']
                : 'daily',
            'url_prefix' => sanitize_text_field($input['url_prefix'] ?? ''),
            'last_sync' => get_option('legaciti_settings', [])['last_sync'] ?? '',
        ];
    }
}
