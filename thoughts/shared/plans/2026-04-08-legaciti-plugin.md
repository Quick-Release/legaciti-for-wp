---
date: 2026-04-08
topic: "Legaciti WordPress Plugin Implementation Plan"
status: draft
design_ref: "../designs/2026-04-08-legaciti-plugin-design.md"
---

# Legaciti WordPress Plugin — Implementation Plan

## Overview

Build a greenfield WordPress plugin using dual storage: `leg_*` custom tables as source of truth, CPTs as projection layer. PHP 8.1+ with PSR-4 autoloading under `LegacitiForWp` namespace. Sync flow: API → tables → CPTs. WP-Cron scheduling + AJAX manual sync. Admin settings under top-level Legaciti menu.

---

## Step 1 — Composer.json + PSR-4 Autoloading Setup

**Files to create:**
- `composer.json`

**What to implement:**
- Composer config with PSR-4 autoloading: `"LegacitiForWp\\": "src/"`
- Require PHP `^8.1`
- Run `composer install` to generate `vendor/autoload.php`

**Dependencies:** None (foundation step)

**Verification:**
- `composer.json` contains the `LegacitiForWp` PSR-4 mapping
- `composer dump-autoload` generates `vendor/autoload.php`
- A test class under `LegacitiForWp\` can be instantiated

---

## Step 2 — Plugin Bootstrap (legaciti-for-wp.php) with Plugin.php Orchestrator

**Files to create:**
- `legaciti-for-wp.php` (entry point with plugin header)
- `src/Plugin.php` (main orchestrator)
- `src/Activation.php` (activation hook handler)
- `src/Deactivation.php` (deactivation hook handler)

**What to implement:**
- `legaciti-for-wp.php`:
  - WordPress plugin header comment
  - PHP version guard (8.1+)
  - Require `vendor/autoload.php`
  - Hook `plugins_loaded` to initialize `Plugin`
- `Plugin.php`:
  - Wire up all components (Activation, Deactivation, CPTs, Settings, Cron)
  - Singleton or static `init()` pattern
- `Activation.php`:
  - Register activation hook
  - Call `TableManager::installTables()`
  - Flush rewrite rules
- `Deactivation.php`:
  - Register deactivation hook
  - Clear WP-Cron scheduled events

**Dependencies:** Step 1 (Composer autoload)

**Verification:**
- Plugin appears in WordPress admin plugin list
- Activation runs without errors
- `LegacitiForWp\Plugin` class loads via autoload

---

## Step 3 — Database Layer (TableManager + Repositories)

**Files to create:**
- `src/Database/TableManager.php`
- `src/Database/PersonRepository.php`
- `src/Database/PublicationRepository.php`
- `src/Database/RelationRepository.php`

**What to implement:**

### TableManager
- `installTables()`: Use `dbDelta()` to create three tables:
  - `{wp_prefix}leg_people` — columns: id, external_id, first_name, last_name, email, title, bio, avatar_url, slug, wp_post_id, raw_api_data, status, synced_at, created_at, updated_at
  - `{wp_prefix}leg_publications` — columns: id, external_id, title, slug, abstract, publication_date, doi, journal, wp_post_id, raw_api_data, status, synced_at, created_at, updated_at
  - `{wp_prefix}leg_person_publications` — columns: id, person_id, publication_id, role, position (unique constraint on person_id + publication_id)
- `dropTables()`: For uninstall
- Store schema version in `legaciti_db_version` option
- Use `$wpdb->get_charset_collate()` for charset
- Include `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` before dbDelta

### PersonRepository
- `upsert(array $data): int` — insert or update by external_id, return table ID
- `findById(int $id): ?Person`
- `findByExternalId(string $externalId): ?Person`
- `findByWpPostId(int $postId): ?Person`
- `findAll(): array`
- `updateWpPostId(int $id, int $wpPostId): void`
- `markInactive(array $activeExternalIds): int` — set status='inactive' for records NOT in the given list

### PublicationRepository
- Same pattern as PersonRepository, with publication-specific columns

### RelationRepository
- `syncForPublication(int $publicationId, array $relations): void` — delete existing + re-insert
- `getPublicationsForPerson(int $personId): array`
- `getPeopleForPublication(int $publicationId): array`

**Dependencies:** Step 2 (Plugin bootstrap, activation hook)

**Verification:**
- Plugin activation creates all three tables
- `legaciti_db_version` option is set
- Repositories perform CRUD operations correctly
- Use `$wpdb->prefix` for multisite compatibility

---

## Step 4 — Models (Person, Publication as Readonly Entities)

**Files to create:**
- `src/Models/Person.php`
- `src/Models/Publication.php`

**What to implement:**
- Use PHP 8.1 constructor property promotion with `readonly` properties
- `Person`: id, external_id, first_name, last_name, email, title, bio, avatar_url, slug, wp_post_id, raw_api_data, status, synced_at, created_at, updated_at
- `Publication`: id, external_id, title, slug, abstract, publication_date, doi, journal, wp_post_id, raw_api_data, status, synced_at, created_at, updated_at
- Static factory methods: `fromArray(array $data): self`
- `declare(strict_types=1)` in all files

**Dependencies:** Step 3 (DB layer provides row data)

**Verification:**
- Models can be instantiated from database row arrays
- All properties are typed and readonly
- `fromArray()` hydrates correctly

---

## Step 5 — API Client (HTTP Wrapper)

**Files to create:**
- `src/API/Client.php`

**What to implement:**
- Constructor takes settings (base_url, api_key) — no direct `get_option` calls
- `getPeople(array $params = []): array` — fetch people from API with pagination
- `getPublications(array $params = []): array` — fetch publications with pagination
- `get(string $endpoint, array $params = []): array` — generic GET wrapper
- Use `wp_remote_get()` with:
  - Authorization header with API key
  - Timeout configuration
  - User-Agent header
- Error handling:
  - `is_wp_error()` check on response
  - HTTP status code validation
  - JSON decode with error checking
  - Return typed arrays or throw exceptions
- `declare(strict_types=1)`

**Dependencies:** Step 2 (autoloading), settings from Step 9

**Verification:**
- Client constructs with base URL and API key
- GET requests include proper headers
- Error responses are handled gracefully
- Response data maps to repository upsert format

---

## Step 6 — Custom Post Types Registration

**Files to create:**
- `src/CustomPostTypes/PersonPostType.php`
- `src/CustomPostTypes/PublicationPostType.php`

**What to implement:**

### PersonPostType
- `register(): void` — hooks into `init`
- CPT slug: `leg_person`
- `public: true`, `show_in_rest: true`, `has_archive: true`
- `supports: ['title', 'editor', 'thumbnail']`
- `rewrite: ['slug' => 'people']`
- `capability_type: 'post'`
- Labels: People/Person with full label set
- `menu_icon: 'dashicons-id'`
- Disable admin UI for manual creation (`create_posts` capability set to `do_not_allow`)

### PublicationPostType
- Same pattern with `leg_publication` slug
- `rewrite: ['slug' => 'publications']`
- `supports: ['title', 'editor', 'thumbnail', 'excerpt']`
- `menu_icon: 'dashicons-book'`

**Dependencies:** Step 2 (bootstrap hooks)

**Verification:**
- CPTs appear in WordPress REST API (`/wp-json/wp/v2/leg_person`, `/wp-json/wp/v2/leg_publication`)
- Archive URLs work (`/people/`, `/publications/`)
- Manual creation is disabled in admin

---

## Step 7 — SyncService (The Pipeline)

**Files to create:**
- `src/API/SyncService.php`

**What to implement:**
- Constructor receives: Client, PersonRepository, PublicationRepository, RelationRepository (via dependency injection)
- `sync(): SyncResult` — main entry point:
  1. Acquire sync lock (transient `legaciti_sync_lock`, 30 min TTL)
  2. Fetch all people from API → upsert into `leg_people`
  3. For each person, call PersonProjector::project()
  4. Fetch all publications from API → upsert into `leg_publications`
  5. For each publication, call PublicationProjector::project()
  6. Sync junction table relations
  7. Handle deletions: mark records inactive that weren't in API response
  8. Update `legaciti_settings.last_sync` timestamp
  9. Release sync lock
  10. Return SyncResult with counts and errors
- Pagination handling: loop through API pages until no more results
- Error accumulation: collect individual failures, don't abort entire sync
- `SyncResult` value object: people_synced, publications_synced, relations_synced, errors[]

**Dependencies:** Step 3 (Repositories), Step 5 (Client), Step 6 (CPTs), Step 8 (Cron triggers it), Step 9 (Settings for timestamp)

**Verification:**
- Full sync from empty state populates all three tables
- CPT posts created for each table record
- Sync lock prevents concurrent execution
- Partial API failures don't abort entire sync
- SyncResult contains accurate counts

---

## Step 8 — CronManager (WP-Cron Scheduling)

**Files to create:**
- `src/Scheduling/CronManager.php`

**What to implement:**
- `register(): void` — hook into plugin init
- `scheduleEvent(): void` — schedule `legaciti_sync_event` based on settings frequency
- `unscheduleEvent(): void` — clear scheduled event
- `handleSyncEvent(): void` — callback that calls `SyncService::sync()`
- Add custom cron interval via `cron_schedules` filter if needed
- Called from Activation (schedule) and Deactivation (unschedule)
- AJAX handler for manual sync:
  - `wp_ajax_legaciti_manual_sync` — verify nonce, check `manage_options` capability
  - Return JSON response with SyncResult

**Dependencies:** Step 2 (activation/deactivation hooks), Step 7 (SyncService)

**Verification:**
- `wp_next_scheduled('legaciti_sync_event')` returns a timestamp after activation
- Manual sync AJAX endpoint returns sync results
- Cron event is cleared after deactivation

---

## Step 9 — Settings Page (Admin UI)

**Files to create:**
- `src/Admin/SettingsPage.php`
- `assets/css/admin.css`
- `assets/js/admin.js`

**What to implement:**

### SettingsPage
- Top-level menu: "Legaciti" with "Settings" sub-page
- `add_menu_page()` for top-level, `add_submenu_page()` for settings
- Settings stored as serialized array in `legaciti_settings` option
- Fields:
  - API Base URL (text, sanitized with `esc_url`)
  - API Key (password input, sanitized)
  - Sync Frequency (select: hourly, twice daily, daily, manual only)
  - Last Sync (readonly display)
  - Manual Sync button (triggers AJAX via admin.js)
  - Sync Log (readonly textarea, recent results)
- Register settings with `register_setting()`, `add_settings_section()`, `add_settings_field()`
- Sanitization callback for all fields
- Nonce verification on save

### admin.js
- AJAX call for manual sync button
- Show loading state during sync
- Display sync results

### admin.css
- Basic styling for settings page layout

**Dependencies:** Step 2 (bootstrap hooks), Step 8 (AJAX handler for manual sync)

**Verification:**
- Settings page accessible under Legaciti > Settings
- Fields save and persist correctly
- Manual Sync button triggers AJAX and shows results
- Last Sync timestamp updates after sync

---

## Step 10 — CPT Projector (Creates/Updates WP Posts from Table Data)

**Files to create:**
- `src/Projectors/PersonProjector.php`
- `src/Projectors/PublicationProjector.php`

**What to implement:**

### PersonProjector
- `project(Person $person): int` — create or update WP post from Person model
- If `$person->wp_post_id` exists, update; otherwise create new post
- `wp_insert_post()` / `wp_update_post()` with:
  - `post_type: 'leg_person'`
  - `post_title`: full name (first + last)
  - `post_content`: bio
  - `post_name`: slug
  - `post_status: 'publish'`
- Update post meta:
  - `_leg_external_id`
  - `_leg_table_id`
  - `_leg_first_name`
  - `_leg_last_name`
  - `_leg_email`
  - `_leg_title`
  - `_leg_avatar_url`
- Return the WP post ID
- Update `wp_post_id` in custom table via PersonRepository

### PublicationProjector
- Same pattern for publications
- Post meta: `_leg_external_id`, `_leg_table_id`, `_leg_publication_date`, `_leg_doi`, `_leg_journal`
- `post_content`: abstract

### Inactive record handling
- When records are marked inactive, set CPT post status to `draft`

**Dependencies:** Step 3 (Repositories), Step 6 (CPTs registered)

**Verification:**
- CPT posts exist for all active table rows
- Post meta matches table data exactly
- `wp_post_id` in custom table matches actual post ID
- Updating a record updates the CPT post (not creates duplicate)
- Inactive records have draft status

---

## Step 11 — Uninstall Cleanup

**Files to create:**
- `uninstall.php`

**What to implement:**
- Check `WP_UNINSTALL_PLUGIN` constant
- Drop tables: `leg_people`, `leg_publications`, `leg_person_publications`
- Delete all CPT posts of type `leg_person` and `leg_publication` (use `wp_delete_post`)
- Remove options: `legaciti_settings`, `legaciti_db_version`
- Clear scheduled WP-Cron events
- Delete all post meta associated with CPT posts
- Use `$wpdb->query()` for table drops with proper prefix

**Dependencies:** All previous steps (must clean up everything)

**Verification:**
- After uninstall, no `leg_*` tables remain
- No `legaciti_*` options remain
- No scheduled cron events remain
- No CPT posts remain

---

## Step 12 — Templates (Optional Default Overrides)

**Files to create:**
- `templates/archive-leg-person.php`
- `templates/single-leg-person.php`
- `templates/archive-leg-publication.php`
- `templates/single-leg-publication.php`

**What to implement:**
- Default templates that use repository methods to fetch data
- `single-leg-person.php`: Display person details + list of their publications (via `RelationRepository::getPublicationsForPerson()`)
- `single-leg-publication.php`: Display publication details + list of authors (via `RelationRepository::getPeopleForPublication()`)
- Archive templates: List views with basic formatting
- Template loading via `"template_include"` filter — check if theme has override first, fall back to plugin templates

**Dependencies:** Step 6 (CPTs), Step 10 (Projectors)

**Verification:**
- Templates load and render with sample data
- Person single shows related publications
- Publication single shows related people
- Theme can override by placing same-named files in theme directory

---

## Cross-Step Concerns

### Error Handling
- All repository operations use `$wpdb->prepare()` for SQL injection prevention
- API Client catches `WP_Error` and HTTP failures
- SyncService accumulates errors without aborting
- Admin settings sanitize all inputs

### PHP 8.1+ Features Used
- `declare(strict_types=1)` in all files
- `readonly` properties on Models and value objects
- Constructor property promotion
- Named arguments for WordPress function calls
- Union types for return types (`Person|null`, `array|false`)
- Enums: `SyncStatus`, `RecordStatus` (active/inactive)
- Match expressions where appropriate

### Security
- Nonce verification on all admin forms and AJAX
- Capability checks (`manage_options`) on settings and sync
- Sanitization on all inputs
- `$wpdb->prepare()` on all queries
- API key stored securely, not exposed in frontend

### Performance
- Sync runs asynchronously via WP-Cron
- Custom tables indexed for common queries
- Batch upserts where possible
- Sync lock prevents concurrent execution
- Pagination handling for large API responses
