---
date: 2026-04-08
topic: "Legaciti WordPress Plugin - Custom Tables + REST API + Rewrite Rules"
status: validated
---

## Problem Statement

Build a WordPress plugin that syncs **People** and **Publications** from `api.legaciti.org`, caches them in custom tables, and exposes them via:

1. **Custom URL slugs** — e.g. `cesam-la.pt/asoares` serves a person profile page
2. **REST API** — `cesam-la.pt/wp-json/legaciti/v1/people`, `.../publications` as fallback when the SaaS API is down

All CRUD happens on `my.legaciti.org`. WordPress is a **read-only cache** with frontend rendering.

## Constraints

- **PHP 8.1+ minimum** — typed properties, enums, readonly, named arguments, match, union types
- **Custom tables with `leg_` prefix** — single source of truth, no CPTs
- **Read-only in WordPress** — WP never writes back to the API
- **API-down resilience** — custom tables serve as cache; site stays functional
- **PSR-4 autoloading** via Composer
- **No CPTs** — WordPress's native post storage is unnecessary overhead for synced data

## Approach

### Custom Tables Only (No CPTs)

Previous design used dual storage (tables + CPTs). This was over-engineered:

- CPTs are for content edited inside WordPress — we never edit here
- CPTs duplicate data into `wp_posts` + `wp_postmeta` — twice the writes, twice the sync complexity
- Custom REST endpoints give us full control over response shape, no WordPress post baggage

Instead: **Custom tables + Rewrite rules + Custom REST endpoints**.

### URL Routing via Rewrite Rules

Root-level slugs like `/asoares` are handled by WordPress rewrite rules:

1. Register rewrite rules that intercept matching URL patterns
2. On request, look up the slug in `leg_people` (or `leg_publications`)
3. If found → serve a plugin template (overridable by theme)
4. If not found → fall through to normal WordPress routing (pages, 404)

A **configurable URL prefix** in settings allows root-level (`/asoares`) or prefixed (`/people/asoares`) patterns to avoid page conflicts.

## Architecture

### High-Level Components

```
┌─────────────┐     ┌──────────────┐     ┌─────────────────┐     ┌──────────┐
│  WP-Cron /  │────▶│ SyncService  │────▶│   Repository    │────▶│ Custom   │
│  Manual AJAX│     │ (orchestrator│     │   (DB layer)    │     │ Tables   │
└─────────────┘     └──────────────┘     └────────┬────────┘     │ (leg_*)  │
                                                   │              └──────────┘
                                          ┌────────┴────────┐
                                          │                 │
                                    ┌─────▼─────┐    ┌─────▼──────┐
                                    │ Rewrite   │    │ REST API   │
                                    │ Router    │    │ Controllers│
                                    │ (/asoares)│    │ (/wp-json) │
                                    └───────────┘    └────────────┘
```

### Plugin File Structure

```
legaciti-for-wp/
├── legaciti-for-wp.php              # Bootstrap (entry point, plugin header)
├── composer.json                    # PSR-4 autoloading config
├── uninstall.php                    # Clean uninstall (drops tables, removes options)
├── src/
│   ├── Plugin.php                   # Main orchestrator (init, register hooks)
│   ├── Activation.php               # Create tables, flush rewrite rules
│   ├── Deactivation.php             # Clear cron jobs, flush rewrites
│   ├── Admin/
│   │   └── SettingsPage.php         # Admin settings page (API key, sync, URL config)
│   ├── API/
│   │   ├── Client.php               # HTTP client (wp_remote_get wrapper)
│   │   └── SyncService.php          # Orchestrates full sync pipeline
│   ├── Database/
│   │   ├── TableManager.php         # dbDelta for table creation/migrations
│   │   ├── PersonRepository.php     # CRUD for leg_people
│   │   ├── PublicationRepository.php# CRUD for leg_publications
│   │   └── RelationRepository.php   # CRUD for leg_person_publications
│   ├── Models/
│   │   ├── Person.php               # Readonly entity
│   │   ├── Publication.php          # Readonly entity
│   │   └── SyncResult.php           # Sync outcome value object
│   ├── RestApi/
│   │   ├── PeopleController.php     # /wp-json/legaciti/v1/people
│   │   └── PublicationsController.php # /wp-json/legaciti/v1/publications
│   ├── Routing/
│   │   └── Router.php               # Rewrite rules + template loading
│   └── Scheduling/
│       └── CronManager.php          # WP-Cron registration and execution
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── person-profile.css       # Frontend person profile styling
│   └── js/
│       └── admin.js                  # Manual sync button, settings UI AJAX
└── templates/
    ├── person-profile.php            # Person page template (theme-overridable)
    └── publication-profile.php       # Publication page template (theme-overridable)
```

### Namespace: `LegacitiForWp`

All classes live under `LegacitiForWp\`, mapped via PSR-4 from `src/`.

## Components

### Database Schema (3 custom tables)

**`{wp_prefix}leg_people`**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED, PK, AUTO_INCREMENT | Internal ID |
| external_id | VARCHAR(255), UNIQUE INDEX | API's ID for this person |
| first_name | VARCHAR(255) | |
| last_name | VARCHAR(255) | |
| nickname | VARCHAR(255), UNIQUE INDEX | URL slug (e.g. "asoares") |
| email | VARCHAR(255), NULLABLE | |
| title | VARCHAR(255), NULLABLE | Job title / role |
| bio | LONGTEXT, NULLABLE | |
| avatar_url | VARCHAR(500), NULLABLE | |
| raw_api_data | LONGTEXT JSON, NULLABLE | Full API response backup |
| status | ENUM('active','inactive'), DEFAULT 'active' | |
| synced_at | DATETIME, NULLABLE | Last successful sync |
| created_at | DATETIME | |
| updated_at | DATETIME | |

**`{wp_prefix}leg_publications`**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED, PK, AUTO_INCREMENT | Internal ID |
| external_id | VARCHAR(255), UNIQUE INDEX | API's ID |
| title | VARCHAR(500) | |
| slug | VARCHAR(255), UNIQUE INDEX | URL-safe identifier |
| abstract | LONGTEXT, NULLABLE | |
| publication_date | DATE, NULLABLE | |
| doi | VARCHAR(255), NULLABLE | |
| journal | VARCHAR(500), NULLABLE | |
| raw_api_data | LONGTEXT JSON, NULLABLE | Full API response backup |
| status | ENUM('active','inactive'), DEFAULT 'active' | |
| synced_at | DATETIME, NULLABLE | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

**`{wp_prefix}leg_person_publications`** (junction table)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| person_id | BIGINT UNSIGNED, INDEXED | FK → leg_people.id |
| publication_id | BIGINT UNSIGNED, INDEXED | FK → leg_publications.id |
| role | VARCHAR(100), NULLABLE | e.g. "author", "co-author", "editor" |
| position | INT, NULLABLE | Authorship order |
| **UNIQUE** | (person_id, publication_id) | Prevents duplicates |

### URL Routing (Rewrite Rules)

**Router** registers rewrite rules on `init`:

- **Root-level** (default): `({nickname})` → checks `leg_people.nickname`
- **With prefix**: `(people/{nickname})` → same lookup, prefix configurable in settings

On `template_include` filter:
1. Check if current request matches a person or publication slug
2. If person found → render `templates/person-profile.php` (or theme override)
3. If publication found → render `templates/publication-profile.php` (or theme override)
4. If neither → return original template (let WordPress handle normally)

Theme override: themes can place `person-profile.php` or `publication-profile.php` in their directory to override the plugin defaults.

### REST API Endpoints

Custom controllers registered on `rest_api_init`:

**`GET /wp-json/legaciti/v1/people`**
- List all active people (paginated)
- Supports `?search=` for name filtering
- Response shape matches the SaaS API (so consumers can swap URLs seamlessly)

**`GET /wp-json/legaciti/v1/people/{nickname}`**
- Single person by nickname
- Includes related publications (via junction table)

**`GET /wp-json/legaciti/v1/publications`**
- List all active publications (paginated)
- Supports `?search=` and `?person_id=` filtering

**`GET /wp-json/legaciti/v1/publications/{slug}`**
- Single publication by slug
- Includes related people (via junction table)

All endpoints return data directly from custom tables — no WordPress post objects involved.

### Settings Page

Top-level **"Legaciti"** admin menu with settings sub-page:

- **API Base URL** (text input, default: `https://api.legaciti.org`)
- **API Key** (password input, sanitized)
- **Sync Frequency** (select: hourly, twice daily, daily, manual only)
- **URL Prefix** (text input, default: empty = root-level slugs)
- **Last Sync Timestamp** (readonly display)
- **Manual Sync** button (triggers immediate sync via AJAX)
- **Sync Log** (recent sync results and errors)

Settings stored as single serialized array in `wp_options` under `legaciti_settings`.

### Sync Pipeline

1. **WP-Cron fires** → `legaciti_sync_event` hook
2. **SyncService** acquires sync lock (transient, 30-min TTL)
3. **Client** fetches people from API → **PersonRepository** upserts into `leg_people`
4. **Client** fetches publications → **PublicationRepository** upserts into `leg_publications`
5. **RelationRepository** syncs junction table (delete + re-insert per publication)
6. Handle deletions: records in tables but not in API response → set `status = inactive`
7. Update last sync timestamp in settings
8. Release sync lock
9. Return `SyncResult` with counts and errors

### PHP 8.1 Patterns

- **Enums**: `RecordStatus` (active, inactive), `PersonRole` (author, co_author, editor)
- **Readonly properties**: All model/entity classes
- **Named arguments**: WordPress function calls
- **Union types**: Repository return types (`Person|null`)
- **Match expressions**: Status-based routing
- **Null-safe operator**: Chained calls on potentially null objects
- **`declare(strict_types=1)`**: All files

## Data Flow

### Sync Flow (API → WordPress)

```
api.legaciti.org
       │
       ▼
API Client (wp_remote_get + API key)
       │
       ▼
SyncService (orchestrates, pagination, lock)
       │
       ├──────────────────────────┐
       ▼                          ▼
PersonRepository           PublicationRepository
(upsert leg_people)        (upsert leg_publications)
       │                          │
       └──────────┬───────────────┘
                  ▼
        RelationRepository
        (sync leg_person_publications)
```

### Frontend Request Flow

```
GET /asoares
       │
       ▼
Router::template_include()
       │
       ▼
PersonRepository::findByNickname('asoares')
       │
       ├─ Found → person-profile.php (with related publications)
       └─ Not found → normal WordPress routing
```

### REST API Fallback Flow

```
GET /wp-json/legaciti/v1/people/asoares
       │
       ▼
PeopleController::get_item()
       │
       ▼
PersonRepository::findByNickname('asoares')
       │
       ▼
JSON response (same shape as api.legaciti.org)
```

### API-Down Flow

```
Request → data in custom table? → Yes → Serve from table (cache hit)
                                → No  → 404 (data never synced)
```

No user-facing errors when API is down. Custom tables serve stale data indefinitely.

## Error Handling

- **API is down**: Serve from custom tables. Log failure. No user-facing errors.
- **API returns partial data**: Upsert what we have, skip failures, log warnings.
- **Sync lock timeout**: 30-minute transient auto-expires.
- **Individual record failure**: Skip, continue syncing, accumulate errors.
- **Manual sync AJAX**: Returns immediate feedback with error count.

## Open Questions

1. **API structure**: We need the actual endpoint URLs, auth method, response format, pagination. Placeholder client will be built assuming REST + JSON + Bearer token.
2. **Do publications also get custom slugs?** Currently designed for both people and publications. Confirm if publications need frontend pages too.
3. **Image handling**: Store external URLs only, or sideload into WordPress media library?
4. **Deletion behavior**: Flag as inactive (current) or hard delete?
5. **API rate limits**: Affects sync chunking strategy.
6. **Webhooks vs polling**: Polling by default. Does the API support webhooks?
