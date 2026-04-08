---
date: 2026-04-08
topic: "Legaciti WordPress Plugin - API Sync with Custom Tables and CPTs"
status: draft
---

## Problem Statement

Build a WordPress plugin that syncs **People** and **Publications** data from an external API, stores it in custom database tables with a `leg_` prefix, and surfaces it through Custom Post Types for WordPress-native display (templates, archives, URLs, REST API). All content editing happens externally — WordPress is a read-only consumer with local caching for resilience.

**Requirements:**
- PHP 8.1+ with modern language features
- Custom tables with `leg_` prefix as source of truth
- Two Custom Post Types: Person and Publication
- Many-to-many relationship between people and publications
- Admin settings page for API key configuration
- Scheduled sync with manual trigger option
- Graceful degradation when API is unavailable

## Constraints

- **PHP 8.1+ minimum** — use typed properties, enums, readonly, named arguments, match, union types
- **WordPress coding standards** — proper hook usage, Settings API, dbDelta, WP-Cron
- **Custom table prefix `leg_`** — separate from WordPress core tables
- **Read-only in WordPress** — all edits happen in the external API; WP never writes back
- **API-down resilience** — custom tables serve as cache; site stays functional
- **PSR-4 autoloading** via Composer — no manual require_once

## Approach

### Dual Storage: Custom Tables as Source of Truth, CPTs as Projection Layer

The core architectural decision is a **projection pattern**:

- **Custom tables** (`leg_people`, `leg_publications`, `leg_person_publications`) = **source of truth**, synced from API
- **CPTs** (`leg_person`, `leg_publication`) = **WordPress integration layer**, derived from custom tables

Data flows one direction: **API → Custom Tables → CPTs**

This eliminates confusion about which system owns the data. Custom tables always win. CPTs exist purely to leverage WordPress's template hierarchy, URL routing, REST API, and SEO features.

## Architecture

### High-Level Components

```
┌─────────────┐     ┌──────────────┐     ┌─────────────────┐     ┌──────────┐
│  WP-Cron /  │────▶│ SyncService  │────▶│   Repository    │────▶│ Custom   │
│  Manual AJAX│     │ (orchestrator│     │   (DB layer)    │     │ Tables   │
└─────────────┘     └──────┬───────┘     └────────┬────────┘     │ (leg_*)  │
                           │                      │              └──────────┘
                    ┌──────▼───────┐       ┌──────▼────────┐
                    │  API Client  │       │ CPT Projector  │
                    │ (wp_remote)  │       │ (posts + meta) │
                    └──────────────┘       └───────────────┘
```

### Plugin File Structure

```
legaciti-for-wp/
├── legaciti-for-wp.php              # Bootstrap (entry point, plugin header)
├── composer.json                    # PSR-4 autoloading config
├── uninstall.php                    # Clean uninstall (drops tables, removes options)
├── src/
│   ├── Plugin.php                   # Main orchestrator (init, register hooks, DI)
│   ├── Activation.php               # Create tables, register CPTs, flush rewrites
│   ├── Deactivation.php             # Clear cron jobs
│   ├── Admin/
│   │   └── SettingsPage.php         # Admin settings page (API key, sync config)
│   ├── API/
│   │   ├── Client.php               # HTTP client (wp_remote_get wrapper)
│   │   └── SyncService.php          # Orchestrates full sync pipeline
│   ├── CustomPostTypes/
│   │   ├── PersonPostType.php       # Registers leg_person CPT
│   │   └── PublicationPostType.php  # Registers leg_publication CPT
│   ├── Database/
│   │   ├── TableManager.php         # dbDelta for table creation/migrations
│   │   ├── PersonRepository.php     # CRUD for leg_people
│   │   ├── PublicationRepository.php# CRUD for leg_publications
│   │   └── RelationRepository.php   # CRUD for leg_person_publications
│   ├── Models/
│   │   ├── Person.php               # Readonly entity
│   │   └── Publication.php          # Readonly entity
│   └── Scheduling/
│       └── CronManager.php          # WP-Cron registration and execution
├── assets/
│   ├── css/admin.css
│   └── js/admin.js                  # Manual sync button, settings UI AJAX
└── templates/                       # Optional default template overrides
    ├── archive-leg-person.php
    ├── single-leg-person.php
    ├── archive-leg-publication.php
    └── single-leg-publication.php
```

### Namespace: `LegacitiForWp`

All classes live under the `LegacitiForWp` namespace, mapped via PSR-4 from `src/`.

## Components

### Database Schema (3 custom tables)

**`{wp_prefix}leg_people`**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED, PK, AUTO_INCREMENT | Internal ID |
| external_id | VARCHAR(255), UNIQUE INDEX | API's ID for this person |
| first_name | VARCHAR(255) | |
| last_name | VARCHAR(255) | |
| email | VARCHAR(255), NULLABLE | |
| title | VARCHAR(255), NULLABLE | Job title / role |
| bio | LONGTEXT, NULLABLE | |
| avatar_url | VARCHAR(500), NULLABLE | |
| slug | VARCHAR(255), UNIQUE INDEX | URL-safe identifier |
| wp_post_id | BIGINT UNSIGNED, NULLABLE | Links to CPT post |
| raw_api_data | LONGTEXT JSON, NULLABLE | Full API response backup |
| status | VARCHAR(20), DEFAULT 'active' | active / inactive |
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
| wp_post_id | BIGINT UNSIGNED, NULLABLE | Links to CPT post |
| raw_api_data | LONGTEXT JSON, NULLABLE | Full API response backup |
| status | VARCHAR(20), DEFAULT 'active' | active / inactive |
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

Table creation via `dbDelta` on plugin activation. Schema version stored in `legaciti_db_version` option for future migrations.

### Custom Post Types

**`leg_person`** — Person CPT
- `public`: true, `show_in_rest`: true
- `supports`: title, editor (bio), thumbnail
- `rewrite`: `people/{slug}`
- `has_archive`: true
- Post meta: `_leg_external_id`, `_leg_table_id`, `_leg_first_name`, `_leg_last_name`, `_leg_email`, `_leg_title`, `_leg_avatar_url`
- Admin UI: disabled for manual creation (posts created only via sync)

**`leg_publication`** — Publication CPT
- `public`: true, `show_in_rest`: true
- `supports`: title, editor (abstract), thumbnail
- `rewrite`: `publications/{slug}`
- `has_archive`: true
- Post meta: `_leg_external_id`, `_leg_table_id`, `_leg_publication_date`, `_leg_doi`, `_leg_journal`
- Admin UI: disabled for manual creation

Relationships are **not stored in post meta** — they live in `leg_person_publications` and are queried directly from templates via repository methods.

### Settings Page

Top-level **"Legaciti"** admin menu with settings sub-page:

- **API Base URL** (text input)
- **API Key** (password input, sanitized)
- **Sync Frequency** (select: hourly, twice daily, daily, manual only)
- **Last Sync Timestamp** (readonly display)
- **Manual Sync** button (triggers immediate sync via AJAX)
- **Sync Log** (recent sync results and errors)

Settings stored as single serialized array in `wp_options` under `legaciti_settings`.

### Sync Pipeline

1. **WP-Cron fires** → `legaciti_sync_event` hook
2. **SyncService** acquires sync lock (transient, 30-min TTL, prevents concurrent syncs)
3. **Client** fetches people from API → **PersonRepository** upserts into `leg_people`
4. **CPT projection**: for each person, create/update `leg_person` post + post meta, store `wp_post_id` back in custom table
5. **Client** fetches publications → **PublicationRepository** upserts into `leg_publications`
6. **CPT projection**: for each publication, create/update `leg_publication` post + post meta
7. **RelationRepository** syncs junction table (delete + re-insert per publication for accuracy)
8. Handle deletions: records in tables but not in API response get `status` set to `inactive`; corresponding CPT posts moved to `draft` status
9. Update last sync timestamp in settings option
10. Release sync lock

### PHP 8.1 Patterns

- **Enums**: `SyncStatus` (synced, pending, failed), `PersonRole` (author, co_author, editor), `RecordStatus` (active, inactive)
- **Readonly properties**: All model/entity classes and configuration objects
- **Named arguments**: WordPress function calls for readability
- **Union types**: Repository return types (e.g., `Person|null`)
- **Match expressions**: Status-based routing logic
- **Null-safe operator**: Chained calls on potentially null objects
- **`declare(strict_types=1)`**: All files

## Data Flow

### Sync Flow (API → WordPress)

```
External API
    │
    ▼
API Client (wp_remote_get with API key from settings)
    │
    ▼
SyncService (orchestrates, handles pagination, lock)
    │
    ├─────────────────────────────────┐
    ▼                                 ▼
PersonRepository                PublicationRepository
(upsert leg_people)             (upsert leg_publications)
    │                                 │
    ▼                                 ▼
PersonPostType::project()       PublicationPostType::project()
(wp_insert_post + post meta)    (wp_insert_post + post meta)
    │                                 │
    └──────────┬──────────────────────┘
               ▼
      RelationRepository
      (sync leg_person_publications)
```

### Read Flow (WordPress Frontend)

```
Template Request (single-leg-person.php)
    │
    ▼
Post Object (from CPT, standard WP query)
    │
    ▼
Template calls Repository::getByWpPostId()
    │
    ▼
Custom Table (leg_people) → returns Person model
    │
    ▼
Template calls RelationRepository::getPublicationsForPerson()
    │
    ▼
JOIN query → returns array of Publication models
```

### API-Down Flow (Cache Serving)

```
Request → CPT post exists? → Yes → Serve from custom table (cache hit)
                           → No  → 404 (data never synced)
```

No user-facing errors when API is down. Custom tables serve stale data indefinitely until API recovers.

## Error Handling

- **API is down / unreachable**: Serve from custom tables (they ARE the cache). Log the failure. No user-facing errors. Sync transient records failure.
- **API returns partial data**: Upsert what we have, skip failures, accumulate warnings into sync log.
- **Sync lock timeout**: 30-minute transient auto-expires if sync crashes mid-way.
- **Individual record failure**: Skip it, continue syncing others, collect errors for sync log display.
- **Manual sync AJAX**: Returns immediate feedback (success/failure with error count and last few error messages).
- **Database errors**: Wrapped in try/catch with wpdb error logging. Individual record failures don't abort entire sync.

## Testing Strategy

- **Unit tests**: Repository classes, model hydration, sync service logic (mocked API responses)
- **Integration tests**: Table creation via dbDelta, CPT registration, settings CRUD, WP-Cron scheduling
- **Test framework**: WP_UnitTestCase (WordPress's PHPUnit wrapper)
- **Key test scenarios**:
  - Full sync from empty state
  - Incremental sync (updates only)
  - API-down resilience (stale data served)
  - Concurrent sync prevention (lock behavior)
  - Junction table accuracy (many-to-many integrity)
  - CPT projection consistency with custom table data

## Open Questions

1. **External API structure**: We need the actual endpoint URLs, authentication method (API key in header vs query param), response format (JSON shape), and pagination strategy. This will finalize the Client implementation.
2. **Deletion behavior**: When a person/publication is removed from the API, should we (a) delete the WordPress CPT post + custom table row, or (b) flag as inactive? Current design uses option (b) — safer, prevents broken links. Confirm preference.
3. **Template responsibility**: Should the plugin provide default templates, or just register CPTs and let the theme handle rendering? Current design includes default templates that themes can override.
4. **Image handling**: If people have avatars or publications have cover images, should we sideload them into the WordPress media library, or just store the external URL? Storing URL is simpler but loses control if the external image is moved.
5. **API rate limits**: Does the API have rate limits we need to respect? This affects sync chunking strategy.
6. **Webhooks vs polling**: Should we poll on a schedule (current design), or does the API support webhooks for push-based sync?
