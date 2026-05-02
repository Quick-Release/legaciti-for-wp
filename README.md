# legaciti-for-wp

WordPress plugin that syncs people and publications from the Legaciti API into **custom database tables** (`leg_*`), exposes them via the plugin’s **REST API**, and serves public profile pages through **rewrite rules** and templates.

**Custom post types are not used.** Synced content is not mirrored into `wp_posts`; see the design note in `thoughts/shared/designs/2026-04-08-legaciti-plugin-design.md` and the updated plan in `thoughts/shared/plans/2026-04-08-legaciti-plugin.md`.
