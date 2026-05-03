<?php

declare(strict_types=1);

namespace LegacitiForWp\Admin;

final class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            page_title: __('Legaciti', 'legaciti-for-wp'),
            menu_title: __('Legaciti', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-dashboard',
            callback: [$this, 'renderDashboard'],
            icon_url: 'dashicons-database-import',
            position: 80,
        );

        add_submenu_page(
            parent_slug: 'legaciti-dashboard',
            page_title: __('Dashboard', 'legaciti-for-wp'),
            menu_title: __('Dashboard', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-dashboard',
            callback: [$this, 'renderDashboard'],
        );

        add_submenu_page(
            parent_slug: 'legaciti-dashboard',
            page_title: __('People', 'legaciti-for-wp'),
            menu_title: __('People', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-people',
            callback: [$this, 'renderPeople'],
        );

        add_submenu_page(
            parent_slug: 'legaciti-dashboard',
            page_title: __('Publications', 'legaciti-for-wp'),
            menu_title: __('Publications', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-publications',
            callback: [$this, 'renderPublications'],
        );

        add_submenu_page(
            parent_slug: 'legaciti-dashboard',
            page_title: __('Errors', 'legaciti-for-wp'),
            menu_title: __('Errors', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-errors',
            callback: [$this, 'renderErrors'],
        );

        add_submenu_page(
            parent_slug: 'legaciti-dashboard',
            page_title: __('Settings', 'legaciti-for-wp'),
            menu_title: __('Settings', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-settings',
            callback: [$this, 'renderSettings'],
        );
    }

    public function renderDashboard(): void
    {
        echo '<div class="wrap">';
        echo '<div id="legaciti-dashboard"></div>';
        echo '</div>';
    }

    public function renderPeople(): void
    {
        echo '<div class="wrap">';
        echo '<div id="legaciti-people"></div>';
        echo '</div>';
    }

    public function renderPublications(): void
    {
        echo '<div class="wrap">';
        echo '<div id="legaciti-publications"></div>';
        echo '</div>';
    }

    public function renderErrors(): void
    {
        echo '<div class="wrap">';
        echo '<p><strong>' . esc_html__('Error log — Legaciti', 'legaciti-for-wp') . '</strong></p>';
        echo '<div id="legaciti-errors"></div>';
        echo '</div>';
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap">';
        echo '<div id="legaciti-settings"></div>';
        echo '</div>';
    }

    public function enqueueAssets(string $hook): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Match `admin.php?page=legaciti-*` — reliable across WP versions (hook suffix varies).
        $page = isset($_GET['page']) && is_string($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        /** @var array<string, string> $pageToEntry menu slug => vite entry name */
        $pageToEntry = [
            'legaciti-dashboard' => 'dashboard',
            'legaciti-people' => 'people',
            'legaciti-publications' => 'publications',
            'legaciti-errors' => 'errors',
            'legaciti-settings' => 'settings',
        ];

        if (! isset($pageToEntry[$page])) {
            return;
        }

        $entryName = $pageToEntry[$page];

        if (wp_style_is('wp-components', 'registered')) {
            wp_enqueue_style('wp-components');
        }

        $distUrl = plugins_url('assets/dist/', LEGACITI_PLUGIN_FILE);
        $distPath = LEGACITI_PLUGIN_DIR . 'assets/dist/';

        $scriptDeps = wp_script_is('wp-api-fetch', 'registered') ? ['wp-api-fetch'] : [];

        $handle = 'legaciti-' . $entryName;
        $enqueued = ViteAssets::enqueueEntry($distPath, $distUrl, $handle, $entryName, $scriptDeps);

        if (! $enqueued) {
            add_action(
                'admin_notices',
                static function (): void {
                    echo '<div class="notice notice-error"><p>';
                    echo esc_html__(
                        'Legaciti: admin assets are missing. From the plugin folder, run: cd admin && pnpm install && pnpm run build',
                        'legaciti-for-wp'
                    );
                    echo '</p></div>';
                }
            );

            return;
        }

        if ($entryName === 'people') {
            $settings = get_option('legaciti_settings', []);
            $prefix = trim((string) ($settings['url_prefix'] ?? ''), '/');
            wp_localize_script(
                handle: 'legaciti-people',
                object_name: 'legacitiPeopleScreen',
                l10n: [
                    'homeUrl' => untrailingslashit(home_url()),
                    'urlPrefix' => $prefix,
                ],
            );
        }

        if ($entryName === 'publications') {
            wp_localize_script(
                handle: 'legaciti-publications',
                object_name: 'legacitiPublicationsScreen',
                l10n: [
                    'homeUrl' => untrailingslashit(home_url()),
                ],
            );
        }
    }
}
