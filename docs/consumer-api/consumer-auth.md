# Consumer API Authentication Guide

Practical authentication reference for calling `api.legaciti.org` endpoints successfully.

This document focuses on:

- Required headers
- Valid request examples
- How to distinguish missing keys from permission issues

## Base URL

```text
https://api.legaciti.org
```

## Required Header

All protected Consumer API and Integrations API endpoints require the same credential header:

```http
X-API-Key: YOUR_API_KEY_OR_INSTALLATION_CREDENTIAL
```

### Important

- The current API expects `X-API-Key`.
- Sending only `Authorization: Bearer ...` will not satisfy protected endpoints that require API key auth.

## Credential Types

Two common credential contexts exist:

1. Consumer API key (workspace/public API use cases).
2. Integration installation credential (WordPress/Drupal integration use cases).

Both are transmitted using `X-API-Key`.

For integrations, endpoint access also depends on granted scopes (for example, `people.read`).

## Header Matrix

| Endpoint family | Path examples | Required headers |
|---|---|---|
| Consumer API read/write routes | `/v1/publications`, `/v1/people` (paginated list), `/v1/people/:orcid`, `/api/ingest` | `X-API-Key` |
| Integrations API routes | `/integrations/v1/installation` | `X-API-Key` |
| JSON POST/PATCH routes | e.g. `/api/ingest` | `X-API-Key`, `Content-Type: application/json` |

## Valid Request Examples

### 1) Health Check (no auth)

```bash
curl -i https://api.legaciti.org/health
```

### 2) List People (requires integration credential + scope)

```bash
curl -i \
  -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/v1/people?page=1&per_page=50"
```

### 3) Get One Person

```bash
curl -i \
  -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/v1/people/0000-0002-1825-0097"
```

### 4) Get Person Publications

```bash
curl -i \
  -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/v1/people/0000-0002-1825-0097/publications?page=1&per_page=20"
```

### 5) Verify Installation Context (Integrations API)

```bash
curl -i \
  -H "X-API-Key: YOUR_INSTALLATION_CREDENTIAL" \
  "https://api.legaciti.org/integrations/v1/installation"
```

This is the fastest way to confirm your key maps to an active installation and inspect granted scopes.

### 6) Public Ingest Request (JSON body)

```bash
curl -i \
  -X POST \
  -H "X-API-Key: YOUR_CONSUMER_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"orcid_ids":["0000-0002-1825-0097"]}' \
  "https://api.legaciti.org/api/ingest"
```

## JavaScript Fetch Example

```js
const response = await fetch("https://api.legaciti.org/v1/people?page=1&per_page=50", {
  method: "GET",
  headers: {
    "X-API-Key": process.env.LEGACITI_API_KEY,
    "Accept": "application/json"
  }
});

if (!response.ok) {
  const body = await response.text();
  throw new Error(`Legaciti API failed: ${response.status} ${body}`);
}

const data = await response.json();
console.log(data);
```

## WordPress Example (wp_remote_get)

```php
$url = 'https://api.legaciti.org/v1/people?page=1&per_page=50';

$response = wp_remote_get($url, [
  'timeout' => 20,
  'headers' => [
    'X-API-Key' => get_option('legaciti_api_key'),
    'Accept' => 'application/json',
  ],
]);

$code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);

if ($code >= 400) {
  error_log('Legaciti API error: ' . $code . ' body=' . $body);
}
```

## Troubleshooting: 401 vs 403

Use response body `code` to identify the exact cause.

| HTTP | Body code | Meaning | Action |
|---|---|---|---|
| `401` | `missing_api_key` | Request did not include `X-API-Key` (or header got stripped by proxy/plugin) | Ensure `X-API-Key` is sent end-to-end |
| `401` | `invalid_installation_credential` | Header exists, but key is invalid for installation auth | Regenerate/correct credential |
| `403` | `installation_scope_denied` | Key is valid, but lacks required scope (for example `people.read`) | Update installation scopes |
| `403` | `inactive_installation` / `inactive_installation_credential` | Installation or credential is not active | Activate installation/credential |
| `403` | `origin_not_verified` | Installation origin verification not completed | Complete origin verification |

## Quick Validation Checklist

1. Confirm header key is exactly `X-API-Key` (case-insensitive name, exact value).
2. Confirm the outbound request really contains the header (debug logs / HTTP capture).
3. Call `/integrations/v1/installation` with the same key.
4. Confirm installation is active and includes required scope (`people.read` for people endpoints).
5. Retry `/v1/people` with `page=1&per_page=1`.

## Known Pitfall

If your client sends only:

```http
Authorization: Bearer YOUR_TOKEN
```

you can receive:

```json
{"error":"Missing API key","code":"missing_api_key"}
```

for protected endpoints expecting `X-API-Key`.
