<?php

declare(strict_types=1);

namespace LegacitiForWp\Admin;

/**
 * Loads the BrowserSync client from the dev machine (e.g. https://localhost:3000) so you can
 * use the real DDEV URL in the browser and still get reload when Vite rebuilds assets.
 *
 * Enable with environment (e.g. DDEV web_environment) or wp-config constants:
 *   LEGACITI_BROWSER_SYNC=1
 *   LEGACITI_BROWSER_SYNC_ORIGIN=https://localhost:3000   (optional; must match BrowserSync)
 */
final class BrowserSyncDev
{
    public function register(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueueClient'], 999);
        add_filter('script_loader_tag', [$this, 'makeScriptAsync'], 10, 2);
    }

    private function isEnabled(): bool
    {
        if (defined('LEGACITI_BROWSER_SYNC') && constant('LEGACITI_BROWSER_SYNC')) {
            return true;
        }

        $env = getenv('LEGACITI_BROWSER_SYNC');

        return $env === '1' || strtolower((string) $env) === 'true';
    }

    private function clientOrigin(): string
    {
        if (defined('LEGACITI_BROWSER_SYNC_ORIGIN')) {
            $v = constant('LEGACITI_BROWSER_SYNC_ORIGIN');
            if (is_string($v) && $v !== '') {
                return rtrim($v, '/');
            }
        }

        $fromEnv = getenv('LEGACITI_BROWSER_SYNC_ORIGIN');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        return 'https://localhost:3000';
    }

    public function enqueueClient(): void
    {
        $origin = esc_url_raw($this->clientOrigin());
        if ($origin === '') {
            return;
        }

        $src = $origin . '/browser-sync/browser-sync-client.js';
        wp_enqueue_script('legaciti-browser-sync', $src, [], null, true);
    }

    public function makeScriptAsync(string $tag, string $handle): string
    {
        if ($handle !== 'legaciti-browser-sync' || str_contains($tag, ' async')) {
            return $tag;
        }

        return str_replace('<script ', '<script async ', $tag);
    }
}
