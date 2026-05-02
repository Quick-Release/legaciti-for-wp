/**
 * BrowserSync: watches built admin assets and connects browsers (including tabs opened
 * directly at DDEV) via PHP-injected client script when LEGACITI_BROWSER_SYNC=1.
 *
 * script.domain / socket.domain force the live-reload client + WebSocket to use the
 * BrowserSync host (localhost:port), not the WordPress host (wp-plugin.ddev.site).
 *
 * @see LegacitiForWp\Admin\BrowserSyncDev
 */
const proxy = process.env.LEGACITI_WP_PROXY || 'https://wp-plugin.ddev.site';

module.exports = {
  proxy,
  files: ['../assets/dist/**/*'],
  open: false,
  reloadDelay: 150,
  reloadDebounce: 50,
  notify: false,
  /** We inject the client from PHP so https://wp-plugin.ddev.site/... tabs participate. */
  snippet: false,
  startPath: '/wp-admin/admin.php?page=legaciti-settings',
  cors: true,
  script: {
    domain: 'https://localhost:{port}',
  },
  socket: {
    domain: 'https://localhost:{port}',
  },
};
