---
description: Key facts about the Legaciti WordPress plugin project
label: project
limit: 5000
read_only: false
---
## Legaciti WordPress Plugin

### Architecture
- WordPress plugin consuming external API for People + Publications data
- **Custom tables only** (leg_*) as single source of truth — NO CPTs
- One-directional sync: API → Custom Tables
- Read-only in WordPress, all editing on my.legaciti.org
- Rewrite rules for frontend URLs (not CPTs)
- Custom REST API endpoints at /wp-json/legaciti/v1/* as fallback

### Database
- 3 custom tables: `leg_people`, `leg_publications`, `leg_person_publications` (junction)
- Created via dbDelta on activation, schema version in `legaciti_db_version` option

### URL Routing
- People: root-level slugs via rewrite rules (e.g. `/asoares`) — configurable prefix in settings
- Publications: fixed prefix `/publication/slug`
- Router resolves slugs from custom tables, serves plugin templates (theme-overridable)

### Key Classes
- Plugin.php (orchestrator), SyncService (pipeline), Client (HTTP), Repositories (DB layer)
- CronManager (WP-Cron), SettingsPage (admin), TableManager (schema)
- Router (rewrite rules + template_include), PeopleController/PublicationsController (REST)
- Namespace: `LegacitiForWp`, PSR-4 from src/

### PHP Version: 8.1+
- Enums (RecordStatus), readonly properties, named arguments, strict_types
- Constructor property promotion, union types

### Settings
- Stored in `legaciti_settings` option (API key, base URL, sync frequency, URL prefix)
- Admin page under top-level "Legaciti" menu with manual sync button

### REST API
- GET /wp-json/legaciti/v1/people (list, paginated, searchable)
- GET /wp-json/legaciti/v1/people/{nickname} (single with publications)
- GET /wp-json/legaciti/v1/publications (list, paginated, searchable)
- GET /wp-json/legaciti/v1/publications/{slug} (single with authors)

### Open Questions
- API endpoint structure/format unknown (placeholder client built)
- Deletion behavior (flag vs delete) — currently flags as inactive
- Image handling (sideload vs URL) — currently stores URL only
- Webhooks vs polling
