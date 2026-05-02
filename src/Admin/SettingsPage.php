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
            page_title: __('Settings', 'legaciti-for-wp'),
            menu_title: __('Settings', 'legaciti-for-wp'),
            capability: 'manage_options',
            menu_slug: 'legaciti-settings',
            callback: [$this, 'renderSettings'],
        );
    }

    public function renderDashboard(): void
    {
        echo '<div id="legaciti-dashboard" class="wrap"></div>';
    }

    public function renderPeople(): void
    {
        echo '<div id="legaciti-people" class="wrap"></div>';
    }

    public function renderSettings(): void
    {
        echo '<div id="legaciti-settings" class="wrap"></div>';
    }

    public function enqueueAssets(string $hook): void
    {
        $screens = [
            'toplevel_page_legaciti-dashboard',
            'legaciti_page_legaciti-people',
            'legaciti_page_legaciti-settings',
        ];

        if (! in_array($hook, $screens, true)) {
            return;
        }

        $distUrl = plugins_url('assets/dist/', LEGACITI_PLUGIN_FILE);
        $distPath = LEGACITI_PLUGIN_DIR . 'assets/dist/';

        if (str_ends_with($hook, 'legaciti-dashboard')) {
            ViteAssets::enqueueEntry($distPath, $distUrl, 'legaciti-dashboard', 'dashboard');
        }

        if (str_ends_with($hook, 'legaciti-people')) {
            if (ViteAssets::enqueueEntry($distPath, $distUrl, 'legaciti-people', 'people')) {
                $settings = get_option('legaciti_settings', []);
                $prefix = trim((string) ($settings['url_prefix'] ?? ''), '/');
                wp_localize_script(
                    handle: 'legaciti-people',
                    object_name: 'legacitiPeopleScreen',
                    data: [
                        'homeUrl' => untrailingslashit(home_url()),
                        'urlPrefix' => $prefix,
                    ],
                );
            }
        }

        if (str_ends_with($hook, 'legaciti-settings')) {
            ViteAssets::enqueueEntry($distPath, $distUrl, 'legaciti-settings', 'settings');
        }
    }
}
