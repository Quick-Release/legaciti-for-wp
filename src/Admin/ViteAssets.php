<?php

declare(strict_types=1);

namespace LegacitiForWp\Admin;

final class ViteAssets
{
    /** Built by `pnpm run build` (IIFE bundles — classic scripts, no ES module chunks). */
    private const ENTRY_JS = [
        'dashboard' => 'assets/legaciti-dashboard.js',
        'people' => 'assets/legaciti-people.js',
        'errors' => 'assets/legaciti-errors.js',
        'settings' => 'assets/legaciti-settings.js',
    ];

    private const ENTRY_CSS = [
        'dashboard' => 'assets/legaciti-dashboard.css',
        'people' => 'assets/legaciti-people.css',
        'errors' => 'assets/legaciti-errors.css',
        'settings' => 'assets/legaciti-settings.css',
    ];

    /**
     * @param list<string> $scriptDeps WordPress scripts that must run first (e.g. wp-api-fetch for REST nonce).
     *
     * @return bool True when bundle files exist and the script was registered
     */
    public static function enqueueEntry(
        string $distPath,
        string $distUrl,
        string $scriptHandle,
        string $entryName,
        array $scriptDeps = ['wp-api-fetch']
    ): bool {
        if (! isset(self::ENTRY_JS[$entryName])) {
            return false;
        }

        $jsRelative = self::ENTRY_JS[$entryName];
        $jsAbsolute = $distPath . $jsRelative;

        if (! is_readable($jsAbsolute)) {
            return false;
        }

        wp_register_script(
            $scriptHandle,
            $distUrl . $jsRelative,
            $scriptDeps,
            self::fileVersion($jsAbsolute),
            true
        );
        wp_enqueue_script($scriptHandle);

        wp_localize_script(
            $scriptHandle,
            'wpApiSettings',
            [
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'nonceEndpoint' => admin_url('admin-ajax.php?action=rest-nonce'),
            ]
        );

        $cssRelative = self::ENTRY_CSS[$entryName] ?? null;
        if ($cssRelative !== null) {
            $cssAbsolute = $distPath . $cssRelative;
            if (is_readable($cssAbsolute)) {
                wp_enqueue_style(
                    $scriptHandle . '-bundle-css',
                    $distUrl . $cssRelative,
                    [],
                    self::fileVersion($cssAbsolute)
                );
            }
        }

        return true;
    }

    private static function fileVersion(string $absolutePath): string
    {
        if (is_readable($absolutePath)) {
            $mtime = filemtime($absolutePath);

            return $mtime !== false ? (string) $mtime : '0';
        }

        return '0';
    }
}
