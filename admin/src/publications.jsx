import '@wordpress/components/build-style/style.css';
import { render, useState, useEffect, useCallback } from '@wordpress/element';
import {
  Card,
  CardBody,
  CardHeader,
  TextControl,
  SelectControl,
  Button,
  Spinner,
  Notice,
  Flex,
  FlexItem,
} from '@wordpress/components';
import apiFetch from './setup-api-fetch';

const PER_PAGE = 20;

function SortArrowPair({ active, direction }) {
  const upOpacity = active && direction === 'asc' ? 1 : 0.35;
  const downOpacity = active && direction === 'desc' ? 1 : 0.35;

  return (
    <span
      aria-hidden
      style={{
        display: 'inline-flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        marginLeft: '4px',
        color: '#50575e',
        flexShrink: 0,
      }}
    >
      <svg width="11" height="8" viewBox="0 0 24 24" style={{ opacity: upOpacity }}>
        <path fill="currentColor" d="M12 8l-6 6h12l-6-6z" />
      </svg>
      <svg width="11" height="8" viewBox="0 0 24 24" style={{ opacity: downOpacity, marginTop: '-5px' }}>
        <path fill="currentColor" d="M12 16l6-6H6l6 6z" />
      </svg>
    </span>
  );
}

function SortableColumnHeader({ label, column, sortBy, sortDir, onSort, isPrimary }) {
  const active = sortBy === column;
  const ariaSort = active ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none';
  const hint = active
    ? `Sorted ${sortDir === 'asc' ? 'A–Z' : 'Z–A'}. Click to reverse.`
    : `Sort by ${label}`;

  return (
    <th
      scope="col"
      aria-sort={ariaSort}
      className={isPrimary ? 'column-primary' : undefined}
    >
      <button
        type="button"
        onClick={() => onSort(column)}
        title={hint}
        aria-label={hint}
        style={{
          background: 'transparent',
          border: 'none',
          padding: '8px 10px',
          margin: '-8px -10px',
          cursor: 'pointer',
          font: 'inherit',
          fontWeight: 600,
          color: 'inherit',
          display: 'inline-flex',
          alignItems: 'center',
          gap: '2px',
          textAlign: 'left',
          width: '100%',
          boxSizing: 'border-box',
        }}
      >
        <span>{label}</span>
        <SortArrowPair active={active} direction={sortDir} />
      </button>
    </th>
  );
}

function publicationProfileUrl(slug) {
  const screen = window.legacitiPublicationsScreen;
  if (!screen?.homeUrl || !slug) {
    return '#';
  }
  const base = screen.homeUrl.replace(/\/$/, '');
  return `${base}/publication/${slug}/`;
}

function PublicationsApp() {
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [orderBy, setOrderBy] = useState('publication_date');
  const [orderDir, setOrderDir] = useState('desc');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [syncing, setSyncing] = useState(false);
  const [syncMessage, setSyncMessage] = useState(null);
  const [checkBusy, setCheckBusy] = useState(false);
  const [connectivity, setConnectivity] = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(PER_PAGE),
    });
    if (search) {
      params.set('search', search);
    }
    if (status === 'active' || status === 'inactive') {
      params.set('status', status);
    }
    params.set('orderby', orderBy);
    params.set('order', orderDir);

    apiFetch({ path: `/legaciti/v1/admin/publications?${params.toString()}` })
      .then((response) => {
        setItems(response.data || []);
        setTotal(response.total ?? 0);
        setTotalPages(response.total_pages ?? 0);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message || 'Failed to load publications.');
        setLoading(false);
      });
  }, [page, search, status, orderBy, orderDir]);

  useEffect(() => {
    load();
  }, [load]);

  const handleSearch = () => {
    setPage(1);
    setSearch(searchInput.trim());
  };

  const handleSortColumn = (column) => {
    setPage(1);
    if (orderBy === column) {
      setOrderDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setOrderBy(column);
      setOrderDir(column === 'publication_date' ? 'desc' : 'asc');
    }
  };

  const handleCheckConnectivity = () => {
    setCheckBusy(true);
    setConnectivity(null);
    setError(null);

    apiFetch({ path: '/legaciti/v1/admin/publications/connectivity' })
      .then((result) => {
        setConnectivity({
          level: result.level || (result.ok ? 'success' : 'error'),
          message: result.message || 'No details.',
          httpCode: result.http_code,
          url: result.url,
          usedApiKey: result.used_api_key,
        });
      })
      .catch((err) => {
        setConnectivity({
          level: 'error',
          message: err.message || 'Request failed.',
          httpCode: null,
          url: null,
          usedApiKey: null,
        });
      })
      .finally(() => {
        setCheckBusy(false);
      });
  };

  const handleSync = () => {
    setSyncing(true);
    setSyncMessage(null);
    setError(null);

    apiFetch({
      path: '/legaciti/v1/admin/publications/sync',
      method: 'POST',
    })
      .then((result) => {
        const parts = [];
        if (typeof result.publications_synced === 'number') {
          parts.push(
            `${result.publications_synced} publication${result.publications_synced === 1 ? '' : 's'} updated`,
          );
        }
        if (typeof result.relations_synced === 'number' && result.relations_synced > 0) {
          parts.push(`${result.relations_synced} author link${result.relations_synced === 1 ? '' : 's'} synced`);
        }
        if (typeof result.publications_deactivated === 'number' && result.publications_deactivated > 0) {
          parts.push(`${result.publications_deactivated} marked inactive (removed from API)`);
        }
        if (Array.isArray(result.errors) && result.errors.length > 0) {
          setError(result.errors.join(' '));
          setSyncMessage(null);
        } else {
          setSyncMessage(parts.length > 0 ? parts.join('. ') + '.' : 'Sync finished.');
        }
        load();
      })
      .catch((err) => {
        setError(err.message || 'Sync failed.');
      })
      .finally(() => {
        setSyncing(false);
      });
  };

  const statusOptions = [
    { label: 'All statuses', value: '' },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
  ];

  return (
    <div style={{ padding: '12px 0' }}>
      <Flex gap={3} align="center" style={{ marginBottom: '16px', flexWrap: 'wrap' }}>
        <h1 className="wp-heading-inline" style={{ margin: 0 }}>
          Publications
        </h1>
        <Button variant="secondary" onClick={handleSync} isBusy={syncing} disabled={syncing || checkBusy || loading}>
          Sync
        </Button>
        <Button
          variant="secondary"
          onClick={handleCheckConnectivity}
          isBusy={checkBusy}
          disabled={checkBusy || syncing}
        >
          Check connectivity
        </Button>
      </Flex>
      <p className="description" style={{ marginBottom: '20px' }}>
        Publications synced from api.legaciti.org into this site&apos;s database (including inactive rows when they
        disappear upstream). Sync uses your saved installation credentials under Settings. Author links are stored when
        the related people exist locally—sync People first if links are missing.
      </p>

      {connectivity && (
        <Notice
          status={connectivity.level === 'success' ? 'success' : connectivity.level === 'warning' ? 'warning' : 'error'}
          isDismissible
          onRemove={() => setConnectivity(null)}
          style={{ marginBottom: '12px' }}
        >
          <p style={{ margin: '0 0 8px' }}>{connectivity.message}</p>
          {connectivity.url && (
            <p style={{ margin: 0, fontSize: '12px', color: '#50575e', wordBreak: 'break-all' }}>
              <strong>Tested URL</strong> {connectivity.url}
            </p>
          )}
          <p style={{ margin: '8px 0 0', fontSize: '12px', color: '#50575e' }}>
            {connectivity.httpCode != null && (
              <>
                <strong>HTTP</strong> {connectivity.httpCode}
                {connectivity.usedApiKey != null && ' · '}
              </>
            )}
            {connectivity.usedApiKey != null && (
              <span>
                <strong>Used saved API key</strong> {connectivity.usedApiKey ? 'yes' : 'no'}
              </span>
            )}
          </p>
        </Notice>
      )}

      {syncMessage && (
        <Notice status="success" isDismissible onRemove={() => setSyncMessage(null)}>
          {syncMessage}
        </Notice>
      )}

      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      <Card style={{ marginBottom: '16px' }}>
        <CardBody>
          <Flex gap={4} align="flex-end" wrap>
            <FlexItem style={{ flex: '1 1 220px', minWidth: '200px' }}>
              <TextControl
                label="Search"
                value={searchInput}
                onChange={setSearchInput}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    handleSearch();
                  }
                }}
                placeholder="Title, journal, slug, DOI, external id"
              />
            </FlexItem>
            <FlexItem style={{ flex: '0 1 200px', minWidth: '160px' }}>
              <SelectControl
                label="Status"
                value={status}
                options={statusOptions}
                onChange={(val) => {
                  setPage(1);
                  setStatus(val);
                }}
              />
            </FlexItem>
            <FlexItem>
              <Button variant="primary" onClick={handleSearch}>
                Apply
              </Button>
            </FlexItem>
          </Flex>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <Flex justify="space-between" align="center" style={{ width: '100%' }}>
            <span>
              <strong>{total}</strong> {total === 1 ? 'publication' : 'publications'}
            </span>
            {loading && <Spinner />}
          </Flex>
        </CardHeader>
        <CardBody style={{ padding: 0 }}>
          {loading && items.length === 0 ? (
            <div style={{ padding: '24px', textAlign: 'center' }}>
              <Spinner style={{ width: '40px', height: '40px' }} />
            </div>
          ) : (
            <table className="wp-list-table widefat fixed striped">
              <thead>
                <tr>
                  <SortableColumnHeader
                    label="Title"
                    column="title"
                    sortBy={orderBy}
                    sortDir={orderDir}
                    onSort={handleSortColumn}
                    isPrimary
                  />
                  <SortableColumnHeader
                    label="Slug"
                    column="slug"
                    sortBy={orderBy}
                    sortDir={orderDir}
                    onSort={handleSortColumn}
                  />
                  <SortableColumnHeader
                    label="Journal"
                    column="journal"
                    sortBy={orderBy}
                    sortDir={orderDir}
                    onSort={handleSortColumn}
                  />
                  <SortableColumnHeader
                    label="Date"
                    column="publication_date"
                    sortBy={orderBy}
                    sortDir={orderDir}
                    onSort={handleSortColumn}
                  />
                  <SortableColumnHeader
                    label="DOI"
                    column="doi"
                    sortBy={orderBy}
                    sortDir={orderDir}
                    onSort={handleSortColumn}
                  />
                  <th scope="col">Status</th>
                  <th scope="col">Public page</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={7} style={{ padding: '16px' }}>
                      No publications synced yet. Click Sync above or run a full sync from Settings.
                    </td>
                  </tr>
                ) : (
                  items.map((row) => (
                    <tr key={row.id}>
                      <td className="column-primary">
                        <strong>{row.title || '—'}</strong>
                      </td>
                      <td>{row.slug || '—'}</td>
                      <td>{row.journal || '—'}</td>
                      <td>{row.publication_date || '—'}</td>
                      <td style={{ wordBreak: 'break-all' }}>{row.doi || '—'}</td>
                      <td>{row.status || '—'}</td>
                      <td>
                        {row.status === 'active' && row.slug ? (
                          <a href={publicationProfileUrl(row.slug)} target="_blank" rel="noreferrer">
                            View
                          </a>
                        ) : (
                          <span style={{ color: '#787c82' }}>—</span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          )}
        </CardBody>
      </Card>

      {totalPages > 1 && (
        <div style={{ marginTop: '16px', display: 'flex', gap: '8px', alignItems: 'center' }}>
          <Button
            variant="secondary"
            disabled={page <= 1 || loading}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            Previous
          </Button>
          <span style={{ color: '#50575e' }}>
            Page {page} of {totalPages}
          </span>
          <Button
            variant="secondary"
            disabled={page >= totalPages || loading}
            onClick={() => setPage((p) => p + 1)}
          >
            Next
          </Button>
        </div>
      )}
    </div>
  );
}

render(<PublicationsApp />, document.getElementById('legaciti-publications'));
