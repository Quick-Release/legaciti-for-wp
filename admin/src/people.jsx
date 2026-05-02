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

function profileUrlForNickname(nickname) {
  const screen = window.legacitiPeopleScreen;
  if (!screen?.homeUrl) {
    return '#';
  }
  const base = screen.homeUrl.replace(/\/$/, '');
  const pre = screen.urlPrefix || '';
  if (pre) {
    return `${base}/${pre}/${nickname}/`;
  }
  return `${base}/${nickname}/`;
}

function PeopleApp() {
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(0);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

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

    apiFetch({ path: `/legaciti/v1/admin/people?${params.toString()}` })
      .then((response) => {
        setItems(response.data || []);
        setTotal(response.total ?? 0);
        setTotalPages(response.total_pages ?? 0);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message || 'Failed to load people.');
        setLoading(false);
      });
  }, [page, search, status]);

  useEffect(() => {
    load();
  }, [load]);

  const handleSearch = () => {
    setPage(1);
    setSearch(searchInput.trim());
  };

  const statusOptions = [
    { label: 'All statuses', value: '' },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
  ];

  return (
    <div style={{ padding: '12px 0' }}>
      <h1 className="wp-heading-inline" style={{ marginBottom: '16px' }}>
        People
      </h1>
      <p className="description" style={{ marginBottom: '20px' }}>
        Everyone synced from Legaciti into this site (including inactive records).
      </p>

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
                placeholder="Name, nickname, or email"
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
              <strong>{total}</strong> {total === 1 ? 'person' : 'people'}
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
                  <th scope="col" className="column-primary">
                    Name
                  </th>
                  <th scope="col">Nickname</th>
                  <th scope="col">Email</th>
                  <th scope="col">Title</th>
                  <th scope="col">Status</th>
                  <th scope="col">Synced</th>
                  <th scope="col">Public profile</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={7} style={{ padding: '16px' }}>
                      No people synced yet. Run a sync from Settings.
                    </td>
                  </tr>
                ) : (
                  items.map((row) => (
                    <tr key={row.id}>
                      <td className="column-primary">
                        <strong>{row.full_name || '—'}</strong>
                      </td>
                      <td>{row.nickname || '—'}</td>
                      <td>{row.email || '—'}</td>
                      <td>{row.title || '—'}</td>
                      <td>
                        <span
                          className={
                            row.status === 'active'
                              ? 'legaciti-status-active'
                              : 'legaciti-status-inactive'
                          }
                          style={{
                            fontWeight: 600,
                            color: row.status === 'active' ? '#00a32a' : '#787c82',
                          }}
                        >
                          {row.status === 'active' ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td>{row.synced_at || '—'}</td>
                      <td>
                        {row.status === 'active' && row.nickname ? (
                          <a
                            href={profileUrlForNickname(row.nickname)}
                            target="_blank"
                            rel="noreferrer"
                          >
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

render(<PeopleApp />, document.getElementById('legaciti-people'));
