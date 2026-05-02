/**
 * Bundled @wordpress/api-fetch is a separate instance from window.wp.apiFetch, so core’s
 * inline middleware (root URL + nonce) never runs. Without createRootURLMiddleware,
 * `path: '/legaciti/v1/...'` is fetched as `/legaciti/v1/...` on the site origin — not
 * `/wp-json/...` — which returns HTML and triggers “not a valid JSON response”.
 *
 * Mirrors wp_default_packages_inline_scripts() for wp-api-fetch in script-loader.php.
 */
import apiFetch from '@wordpress/api-fetch';

function configureBundledApiFetch() {
  if (typeof window === 'undefined') {
    return;
  }

  const s = window.wpApiSettings;
  const root = s?.root ? String(s.root) : '';
  const nonce = s?.nonce != null ? String(s.nonce) : '';
  const nonceEndpoint =
    s?.nonceEndpoint || `${window.location.origin}/wp-admin/admin-ajax.php?action=rest-nonce`;

  if (!root) {
    return;
  }

  apiFetch.use(apiFetch.createRootURLMiddleware(root));

  const nonceMiddleware = apiFetch.createNonceMiddleware(nonce);
  apiFetch.nonceMiddleware = nonceMiddleware;
  apiFetch.use(nonceMiddleware);
  apiFetch.nonceEndpoint = nonceEndpoint;
}

configureBundledApiFetch();

export default apiFetch;
