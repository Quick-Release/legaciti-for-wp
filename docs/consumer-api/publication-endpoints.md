# Publication & Work Endpoints (Consumer API)

This page lists all current Consumer API endpoints used to retrieve stored publication records.

Scope:

- Includes both `publications` and `works` resources.
- Excludes unverified works.
- Applies the workspace-membership rule below.

## Required Inclusion Rule

A publication/work must be returned **only** when it is linked to at least one **internal** workspace member.

Do not return a record when:

- It is linked only to external members.
- It is linked to no members.

Internal member definition (workspace-aware):

- A linked person has an active membership in `person_entity_memberships` with status treated as internal (`member` or `cesam`, depending on migration stage), and `ends_at IS NULL`.

## Visibility Baseline (in addition to internal-member rule)

Records must also satisfy:

- `visible = 1`
- `deleted_at IS NULL`

## Endpoints

## 1) List publications

**Method:** `GET`

**Path:** `/v1/publications`

**Purpose:** Returns paginated publication records.

**Filters:**

- `page`, `per_page`
- `q`
- `sort` (`last_fetched_at`, `publication_date`, `cited_by_count`)
- `dir` (`asc`, `desc`)
- `publication_type`
- `publication_year`
- `min_cited_by`

## 2) Get one publication by DOI

**Method:** `GET`

**Path:** `/v1/doi/:doi`

**Purpose:** Returns one publication record by DOI.

**Notes:**

- DOI is URL-decoded and normalized before lookup.
- Result must satisfy visibility + internal-member rule.

## 3) List works (verified works only)

**Method:** `GET`

**Path:** `/v1/works`

**Purpose:** Returns paginated work records stored in `works`.

**Filters:**

- `page`, `per_page`
- `q`
- `sort` (`created_at`, `publication_date`, `publication_year`)
- `dir` (`asc`, `desc`)
- `work_type`
- `publication_year`

**Unverified works:**

- Not included.
- There is no public "unverified works" listing endpoint in Consumer API.

## 4) Get one work by ID (verified works only)

**Method:** `GET`

**Path:** `/v1/works/:id`

**Purpose:** Returns one work record by ID.

**Notes:**

- Result must satisfy visibility + internal-member rule.

## 5) Integration listing: publications

**Method:** `GET`

**Path:** `/integrations/v1/publications`

**Auth:** Installation credential with `publications.read`

**Purpose:** Installation-scoped publication listing for integrations.

**Filters:** same as `/v1/publications`.

## 6) Integration listing: works

**Method:** `GET`

**Path:** `/integrations/v1/works`

**Auth:** Installation credential with `works.read`

**Purpose:** Installation-scoped work listing for integrations.

**Filters:** same as `/v1/works`.

## 7) Person publication listing (subset)

**Method:** `GET`

**Path:** `/v1/people/:orcid/publications`

**Auth:** Installation credential with `people.read`

**Purpose:** Returns publications linked to one person (subset of total publications).

**Notes:**

- Useful for person-specific retrieval, not full catalog export.
- Returned records must satisfy visibility + internal-member rule.

## Implementation Note

To enforce the internal-member rule consistently, publication/work endpoints should apply an existence check against linked people plus active internal membership for the current workspace (for example via `EXISTS` on `publication_people`/`work_people` joined to `person_entity_memberships`).
