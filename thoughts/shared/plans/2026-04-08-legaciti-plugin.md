---
date: 2026-04-08
topic: "Legaciti WordPress Plugin Implementation Plan"
status: current
design_ref: "../designs/2026-04-08-legaciti-plugin-design.md"
---

# Legaciti WordPress Plugin — Implementation Plan

This document describes the **shipped architecture**. Historical drafts assumed custom post types (CPTs) as a projection layer; that approach was **rejected** (see design doc). The plugin stores synced data only in `leg_*` tables, exposes it via the **WordPress REST API**, and serves public profile URLs through **rewrite rules** and PHP templates—not CPTs.

## Overview

- **Storage:** `leg_people`, `leg_publications`, and `leg_person_publications` are the single source of truth. There are **no** `register_post_type()` calls and **no** mirror posts in `wp_posts`.
- **Sync:** Remote API → repositories upsert into custom tables. Optional WP-Cron + manual sync from admin.
- **Admin:** Top-level Legaciti menu, settings (API URL, key, sync frequency, URL prefix), React-based admin UI where present.
- **Public:** `Router` registers rewrites; person URLs resolve by **nickname** (and optional configurable prefix); publications use `/publication/{slug}/`.
- **API:** Custom REST routes under `rest_api_init` (people, publications, dashboard, settings/sync)—not the core WP REST CPT endpoints.

**Stack:** PHP 8.1+, PSR-4 namespace `LegacitiForWp`, Composer autoload.

---

## Step 1 — Composer + PSR-4

- `composer.json` maps `LegacitiForWp\\` → `src/`, PHP `^8.1`, `vendor/autoload.php` loaded from the main plugin file.

---

## Step 2 — Plugin bootstrap

- **`legaciti-for-wp.php`:** Plugin header, PHP version guard, autoload, constants, `Plugin::init()`.
- **`Plugin.php`:** Wires activation/deactivation, `TableManager`, repositories, `Client`, `SyncService`, `CronManager`, `Router`, `SettingsPage`, and REST controllers (`PeopleController`, `PublicationsController`, `DashboardController`, `SettingsController`). **No CPT registration.**
- **`Activation`:** Creates tables via `TableManager::createTables()`, default options, schedules cron, flush rewrite rules.
- **`Deactivation`:** Clears scheduled sync hook.

---

## Step 3 — Database layer

**`TableManager`:** `createTables()` / `dropTables()` with `dbDelta`. Schema (high level):

- **`{prefix}leg_people`:** `external_id` (unique), `first_name`, `last_name`, `nickname` (unique), `email`, `title`, `bio`, `avatar_url`, `raw_api_data`, `status`, `synced_at`, timestamps. **No `slug` or `wp_post_id`**—public URLs use `nickname`.
- **`{prefix}leg_publications`:** `external_id` (unique), `title`, `slug` (unique), `abstract`, `publication_date`, `doi`, `journal`, `raw_api_data`, `status`, `synced_at`, timestamps.
- **`{prefix}leg_person_publications`:** junction `person_id`, `publication_id`, `role`, `position`; unique `(person_id, publication_id)`.

**Repositories:** Upsert/find/search/mark inactive patterns; `PersonRepository::findByNickname()` supports routing.

---

## Step 4 — Models

- Readonly `Person` and `Publication` entities with `fromArray()` hydration (see `src/Models/`).

---

## Step 5 — API client

- **`src/Api/Client.php`** (not `src/API/`): HTTP via `wp_remote_get`, auth from settings, pagination-aware people/publications fetch.

---

## Step 6 — REST API (not CPTs)

- Controllers under `src/RestApi/` register routes on `rest_api_init` for dashboard data, people, publications, and settings-triggered sync.
- **Do not** expect `/wp-json/wp/v2/leg_person`—those would only exist if CPTs were registered; they are **not** part of this plugin.

---

## Step 7 — SyncService

- **`SyncService::sync()`:** Lock, paginated API fetch, repository upserts, relation sync, deactivate rows missing from API, update last-sync timestamp, unlock.
- **No “projector” step:** nothing writes to `wp_posts` or post meta for Legaciti entities.

---

## Step 8 — Cron + manual sync

- **`CronManager`:** Schedules `legaciti_sync_event`, admin AJAX/manual sync path as implemented.

---

## Step 9 — Settings

- **`SettingsPage`** and related admin assets; options in `legaciti_settings` (includes `url_prefix` for rewrite prefix when set).

---

## Step 10 — Public routing and templates

- **`Router`:** Rewrite rules and `template_include` to load `templates/person-profile.php` or `templates/publication-profile.php` after resolving records by nickname/slug.
- Theme overrides: `legaciti/person-profile.php` and `legaciti/publication-profile.php` via `locate_template()`.

**There is no CPT archive/single in the theme hierarchy** for Legaciti post types because none are registered.

---

## Step 11 — Uninstall

- **`uninstall.php`:** Clears cron and options; **drops custom tables only when** `legaciti_settings.remove_on_uninstall` is enabled.
- **No** bulk deletion of CPT posts—none are created.

---

## Cross-cutting concerns

- **Security:** Nonces, capabilities, `$wpdb->prepare()` / `%i` where used, sanitized settings.
- **Performance:** Indexed columns, sync lock, paginated API consumption.

For deeper rationale (why CPTs were avoided), see **`thoughts/shared/designs/2026-04-08-legaciti-plugin-design.md`**.
