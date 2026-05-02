# Consumer API — Persons Endpoints

Complete reference for all endpoints that retrieve person/researcher data through the public Consumer API.

## Overview

The Consumer API provides three endpoints for fetching person (researcher) data. All endpoints require **installation credentials** with the `people.read` scope.

| Method | Endpoint | Description |
|--------|----------|-------------|
| **GET** | `/v1/people` | List people (paginated) |
| **GET** | `/v1/people/:orcid` | Fetch a single person by ORCID ID |
| **GET** | `/v1/people/:orcid/publications` | List all visible publications for a person |

---

## Endpoint: List People

### Request

```
GET /v1/people
```

### Query Parameters

| Parameter | Type | Default | Max | Description |
|-----------|------|---------|-----|-------------|
| `q` | string | `` | — | Search term (queries name and ORCID ID) |
| `page` | integer | `1` | — | 1-based page number for pagination |
| `per_page` | integer | `20` | `100` | Items per page |

### Response

```json
{
  "people": [
    {
      "orcid_id": "0000-0002-1825-0097",
      "name": "John Doe",
      "photo_url": "https://cdn.example.com/photos/jdoe.jpg",
      "slug": "jdoe",
      "people_type": "researcher"
    }
  ],
  "page": 1,
  "per_page": 20,
  "total": 150,
  "pages": 8,
  "q": "john"
}
```

### Response Fields

#### Person Object
| Field | Type | Description |
|-------|------|-------------|
| `orcid_id` | string | ORCID identifier (e.g., `0000-0001-2345-6789`) |
| `name` | string | Display name |
| `photo_url` | string | URL of the person's photo |
| `slug` | string | URL-safe identifier for the person |
| `people_type` | string | Category for the person (e.g., `researcher`) |

Older responses may use a localized `name` object and `biography` object instead of the flat shape above; clients should accept either.

#### List Metadata
| Field | Type | Description |
|-------|------|-------------|
| `page` | integer | Current page (1-based) |
| `per_page` | integer | Items per page |
| `total` | integer | Total count of matching people |
| `pages` | integer | Total number of pages |
| `q` | string | Search query used (empty string if none) |

### Example Requests

```bash
# List first page of all people
curl -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/v1/people?page=1&per_page=50"

# Search for "alice"
curl -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/v1/people?q=alice"

# Get 50 items per page, page 2
curl -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/v1/people?page=2&per_page=50"
```

---

## Endpoint: Get Single Person

### Request

```
GET /v1/people/:orcid
```

### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `orcid` | string | ORCID identifier (e.g., `0000-0001-2345-6789`) |

### Response

```json
{
  "orcid_id": "0000-0001-2345-6789",
  "name": {
    "en": "John Doe",
    "pt": "João Silva"
  },
  "biography": {
    "en": "Researcher in artificial intelligence and machine learning",
    "pt": null
  },
  "last_fetched_at": 1704067200,
  "sync_status": "complete",
  "publication_count": 42
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `orcid_id` | string | ORCID identifier |
| `name` | object | Person's name by language code (`en`, `pt`, etc.) |
| `biography` | object | Person's biography by language code |
| `last_fetched_at` | integer | Unix timestamp of last ORCID sync |
| `sync_status` | string | Sync status: `pending`, `processing`, `complete`, or `failed` |
| `publication_count` | integer | Total number of visible publications |

### Example Request

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://api.legaciti.org/v1/people/0000-0001-2345-6789
```

---

## Endpoint: List Person's Publications

### Request

```
GET /v1/people/:orcid/publications
```

### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `orcid` | string | ORCID identifier (e.g., `0000-0001-2345-6789`) |

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | `1` | 1-based page number |
| `per_page` | integer | `20` | Items per page (max `100`) |
| `sort` | string | `last_fetched_at` | Sort field: `last_fetched_at`, `publication_date`, or `cited_by_count` |
| `dir` | string | `desc` | Sort direction: `asc` or `desc` |

### Response

```json
{
  "publications": [
    {
      "doi": "10.1234/example.doi",
      "title": {
        "en": "Deep Learning Approaches to Natural Language Processing"
      },
      "doi_url": "https://doi.org/10.1234/example.doi",
      "publication_date": "2023-12-31",
      "publication_year": 2023,
      "publication_type": "journal-article",
      "cited_by_count": 15,
      "last_fetched_at": 1704067200
    }
  ],
  "page": 1,
  "per_page": 20,
  "total": 42,
  "pages": 3
}
```

### Response Fields

#### Publication Object
| Field | Type | Description |
|-------|------|-------------|
| `doi` | string | Digital Object Identifier (normalized, lowercase) |
| `title` | object | Publication title, keyed by language code (`en`, etc.) |
| `doi_url` | string | Full URL for the DOI (https://doi.org/{doi}) |
| `publication_date` | string | Publication date in ISO 8601 format (YYYY-MM-DD) |
| `publication_year` | integer | Publication year |
| `publication_type` | string | Type of publication (e.g., `journal-article`, `book`, `conference-paper`, `dissertation`) |
| `cited_by_count` | integer | Number of times cited (from OpenAlex or Crossref) |
| `last_fetched_at` | integer | Unix timestamp when this publication was last enriched |

#### List Metadata
| Field | Type | Description |
|-------|------|-------------|
| `page` | integer | Current page (1-based) |
| `per_page` | integer | Items per page |
| `total` | integer | Total publications for this person |
| `pages` | integer | Total number of pages |

### Example Requests

```bash
# List first page of person's publications
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://api.legaciti.org/v1/people/0000-0001-2345-6789/publications

# Sort by citation count (descending), 50 per page
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.legaciti.org/v1/people/0000-0001-2345-6789/publications?sort=cited_by_count&per_page=50"

# Sort by publication date (ascending)
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://api.legaciti.org/v1/people/0000-0001-2345-6789/publications?sort=publication_date&dir=asc"
```

---

## Complete List of Retrieved Fields

### Person Fields (Available in all person endpoints)

**Identifiers**
- `orcid_id` — ORCID identifier

**Profile Data**
- `name` — Person's name (multilingual object: `en`, `pt`, etc.)
- `biography` — Person's biography (multilingual object or null)

**Sync Metadata**
- `last_fetched_at` — Unix timestamp of last ORCID profile sync
- `sync_status` — Status of ORCID synchronization

**Statistics**
- `publication_count` — Total visible publications count

### Publication Fields (Available in person's publications endpoint)

**Identifiers**
- `doi` — Digital Object Identifier (normalized)

**Metadata**
- `title` — Publication title (multilingual object)
- `publication_date` — Date in ISO 8601 format
- `publication_year` — Year as integer
- `publication_type` — Type classification (e.g., `journal-article`)

**Links**
- `doi_url` — Full DOI URL

**Metrics**
- `cited_by_count` — Citation count

**Sync Metadata**
- `last_fetched_at` — Unix timestamp of last enrichment

---

## Authentication & Authorization

All endpoints require **installation credentials** with the `people.read` scope.

```bash
Authorization: Bearer YOUR_INSTALLATION_TOKEN
```

---

## Response Codes

| Code | Description |
|------|-------------|
| `200` | Success |
| `400` | Invalid query parameters (e.g., `per_page` exceeds max) |
| `401` | Missing or invalid authorization |
| `403` | Insufficient permissions (`people.read` scope required) |
| `404` | Person not found (for single person endpoint) |
| `429` | Rate limit exceeded |
| `500` | Server error |

---

## Rate Limiting

Rate limits are applied per installation credential. See rate limit headers in responses:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1704153600
```

---

## Pagination Best Practices

- Start with page `1`
- Use `total` and `pages` to determine total results
- Limit `per_page` to `100` for performance
- Cache responses where possible to minimize API calls

```bash
# Example: Fetch all people in batches
page=1
while [ $page -le $total_pages ]; do
  curl -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
    "https://api.legaciti.org/v1/people?page=$page&per_page=100"
  ((page++))
done
```

---

## Related Documentation

- [Installation Credentials Guide](./installation-credentials.md)
- [Authentication & Scopes](./authentication.md)
- [Error Handling](./errors.md)
