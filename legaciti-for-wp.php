<?php
/**
 * Plugin Name: Legaciti for WP
 * Plugin URI:  https://legaciti.org
 * Description: Syncs People and Publications from api.legaciti.org with local caching and REST API fallback.
 * Version:     0.1.0
 * Author:      Legaciti
 * License:     proprietary
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="error"><p>';
        echo esc_html__('Legaciti requires PHP 8.1 or higher.', 'legaciti-for-wp');
        echo '</p></div>';
    });
    return;
}

$autoloader = __DIR__ . '/vendor/autoload.php';

if (! file_exists($autoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="error"><p>';
        echo esc_html__('Legaciti: Please run composer install.', 'legaciti-for-wp');
        echo '</p></div>';
    });
    return;
}

require $autoloader;

use LegacitiForWp\Plugin;

Plugin::init();
