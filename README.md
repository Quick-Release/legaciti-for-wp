# legaciti-for-wp

WordPress plugin that syncs people and publications from the Legaciti API into **custom database tables** (`leg_*`), exposes them via the plugin’s **REST API**, and serves public profile pages through **rewrite rules** and templates.

**Custom post types are not used.** Synced content is not mirrored into `wp_posts`; see the design note in `thoughts/shared/designs/2026-04-08-legaciti-plugin-design.md` and the updated plan in `thoughts/shared/plans/2026-04-08-legaciti-plugin.md`.

## DDEV DNS Fix (cURL error 6)

If the People page shows `Could not resolve host: api.legaciti.org`, the WordPress runtime inside DDEV cannot resolve the API hostname.

1. Create `./.ddev/docker-compose.dns.yaml`:

```yaml
services:
  web:
    dns:
      - 1.1.1.1
      - 8.8.8.8
```

2. Restart DDEV:

```bash
ddev restart
```

3. Verify DNS from inside the web container:

```bash
ddev ssh
getent hosts api.legaciti.org
```

4. Re-run **Check connectivity** on the plugin People page.

Notes:
- In corporate/VPN environments, use your organization DNS servers instead of public resolvers.
- If the API is private/local, set `api_base_url` in plugin settings to a host reachable from the DDEV web container.
