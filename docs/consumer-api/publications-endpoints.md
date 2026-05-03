# Consumer API — Publications (reference)

This file summarizes the **publications** endpoints used by **legaciti-for-wp** to list and sync catalog data from `api.legaciti.org`.

For the full publication/work surface (including works, DOI lookup, integration routes, and workspace visibility rules), see [publication-endpoints.md](./publication-endpoints.md).

## Authentication

Same as other Consumer API routes: send `X-API-Key` with your installation credential (see [consumer-auth.md](./consumer-auth.md)).

---

## List publications (catalog sync)

**Method:** `GET`  
**Path:** `/v1/publications`

Returns a paginated list of publication records visible to your installation.

### Common query parameters

| Parameter | Description |
|-----------|-------------|
| `page` | Page number (1-based) |
| `per_page` | Page size (plugin sync requests up to 100 per page) |
| `q` | Search |
| `sort` | e.g. `publication_date`, `cited_by_count`, `last_fetched_at` |
| `dir` | `asc` or `desc` |
| `publication_type`, `publication_year`, `min_cited_by` | Optional filters |

### Pagination

Responses may include `next_page_url`, and/or list metadata such as `pages` (total pages). The WordPress plugin walks pages until no further page is indicated.

### Example

```bash
curl -sS -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/v1/publications?page=1&per_page=50"
```

---

## Person-scoped publications

**Path:** `/v1/people/:orcid/publications`

Returns publications linked to one person (subset of the catalog). Documented in [persons-endpoints.md](./persons-endpoints.md).

---

## Integration listing (optional)

**Path:** `/integrations/v1/publications`

Installation-scoped listing with `publications.read` scope—filters align with `/v1/publications`. See [publication-endpoints.md](./publication-endpoints.md).
