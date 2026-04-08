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

    public function renderSettings(): void
    {
        echo '<div id="legaciti-settings" class="wrap"></div>';
    }

    public function enqueueAssets(string $hook): void
    {
        $screens = [
            'toplevel_page_legaciti-dashboard',
            'legaciti_page_legaciti-settings',
        ];

        if (! in_array($hook, $screens, true)) {
            return;
        }

        $distUrl = plugins_url('assets/dist/', LEGACITI_PLUGIN_FILE);
        $distPath = LEGACITI_PLUGIN_DIR . 'assets/dist/';

        if (str_ends_with($hook, 'legaciti-dashboard')) {
            $assetFile = $distPath . 'dashboard.asset.php';
            if (file_exists($assetFile)) {
                $asset = include $assetFile;
                wp_enqueue_script(
                    handle: 'legaciti-dashboard',
                    src: $distUrl . 'dashboard.js',
                    deps: $asset['dependencies'],
                    ver: $asset['version'],
                    args: true,
                );
                wp_enqueue_style(
                    handle: 'legaciti-dashboard',
                    src: $distUrl . 'dashboard.css',
                    deps: [],
                    ver: $asset['version'],
                );
            }
        }

        if (str_ends_with($hook, 'legaciti-settings')) {
            $assetFile = $distPath . 'settings.asset.php';
            if (file_exists($assetFile)) {
                $asset = include $assetFile;
                wp_enqueue_script(
                    handle: 'legaciti-settings',
                    src: $distUrl . 'settings.js',
                    deps: $asset['dependencies'],
                    ver: $asset['version'],
                    args: true,
                );
                wp_enqueue_style(
                    handle: 'legaciti-settings',
                    src: $distUrl . 'settings.css',
                    deps: [],
                    ver: $asset['version'],
                );
            }
        }
    }
}
