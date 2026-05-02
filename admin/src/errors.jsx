import '@wordpress/components/build-style/style.css';
import { render, useState, useEffect, useCallback, Fragment } from '@wordpress/element';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Notice,
  Flex,
  FlexItem,
  TextControl,
  SelectControl,
} from '@wordpress/components';
import apiFetch from './setup-api-fetch';

const PER_PAGE = 50;

const LEVELS = [
  { label: 'All levels', value: '' },
  { label: 'Error', value: 'error' },
  { label: 'Warning', value: 'warning' },
  { label: 'Info', value: 'info' },
  { label: 'Debug', value: 'debug' },
];

function formatContext(value) {
  if (value === null || value === undefined) {
    return '—';
  }
  if (typeof value === 'object') {
    try {
      return JSON.stringify(value, null, 2);
    } catch {
      return String(value);
    }
  }
  return String(value);
}

function ErrorsApp() {
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(0);
  const [level, setLevel] = useState('');
  const [sourceInput, setSourceInput] = useState('');
  const [source, setSource] = useState('');
  const [q, setQ] = useState('');
  const [qInput, setQInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [clearing, setClearing] = useState(false);
  const [notice, setNotice] = useState(null);
  const [expanded, setExpanded] = useState({});

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(PER_PAGE),
    });
    if (level) {
      params.set('level', level);
    }
    if (source !== '') {
      params.set('source', source);
    }
    if (q.trim()) {
      params.set('q', q.trim());
    }
    apiFetch({ path: `/legaciti/v1/admin/error-logs?${params.toString()}` })
      .then((response) => {
        setItems(response.data || []);
        setTotal(response.total ?? 0);
        setTotalPages(response.total_pages ?? 0);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message || 'Failed to load logs.');
        setLoading(false);
      });
  }, [page, level, source, q]);

  useEffect(() => {
    load();
  }, [load]);

  const applySearch = () => {
    setPage(1);
    setQ(qInput.trim());
    setSource(sourceInput.trim());
  };

  const toggleRow = (id) => {
    setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  const handleClear = (levelOnly) => {
    if (!window.confirm('Delete all log entries' + (levelOnly ? ' at this level?' : ' (entire table)?'))) {
      return;
    }
    setClearing(true);
    setNotice(null);
    const path =
      levelOnly && level
        ? `/legaciti/v1/admin/error-logs?level=${encodeURIComponent(level)}`
        : '/legaciti/v1/admin/error-logs';
    apiFetch({ path, method: 'DELETE' })
      .then((res) => {
        setNotice({
          status: 'success',
          message: res.truncated
            ? 'All logs cleared.'
            : `Cleared ${res.deleted || 0} row(s) at the selected level.`,
        });
        setPage(1);
        load();
      })
      .catch((err) => {
        setNotice({ status: 'error', message: err.message || 'Failed to clear.' });
      })
      .finally(() => {
        setClearing(false);
      });
  };

  const exportJson = () => {
    const blob = new Blob([JSON.stringify(items, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `legaciti-error-logs-page-${page}.json`;
    a.click();
    URL.revokeObjectURL(a.href);
  };

  return (
    <div style={{ padding: '12px 0' }}>
      <h1 className="wp-heading-inline" style={{ marginBottom: '16px' }}>
        Error log
      </h1>
      <p className="description" style={{ marginBottom: '20px' }}>
        Development / debug log for the Legaciti plugin (REST, API client, sync, router, PHP fatals in plugin
        code, etc.). When you are done, drop the <code>…_leg_error_logs</code> table (WordPress table prefix +
        <code>leg_error_logs</code>).
      </p>

      {notice && (
        <Notice
          status={notice.status}
          isDismissible
          onRemove={() => setNotice(null)}
          style={{ marginBottom: '12px' }}
        >
          {notice.message}
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
            <FlexItem style={{ flex: '0 1 160px', minWidth: '120px' }}>
              <SelectControl
                label="Level"
                value={level}
                options={LEVELS}
                onChange={(v) => {
                  setLevel(v);
                  setPage(1);
                }}
              />
            </FlexItem>
            <FlexItem style={{ flex: '1 1 160px', minWidth: '140px' }}>
              <TextControl
                label="Source contains"
                value={sourceInput}
                onChange={setSourceInput}
                placeholder="e.g. sync"
              />
            </FlexItem>
            <FlexItem style={{ flex: '1 1 200px', minWidth: '160px' }}>
              <TextControl
                label="Message contains"
                value={qInput}
                onChange={setQInput}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    applySearch();
                  }
                }}
                placeholder="Search message"
              />
            </FlexItem>
            <FlexItem>
              <Button variant="primary" onClick={applySearch}>
                Apply
              </Button>
            </FlexItem>
            <FlexItem>
              <Button variant="secondary" onClick={load} disabled={loading}>
                Refresh
              </Button>
            </FlexItem>
            <FlexItem>
              <Button variant="secondary" onClick={exportJson} disabled={loading || items.length === 0}>
                Export page (JSON)
              </Button>
            </FlexItem>
            <FlexItem>
              <Button
                variant="secondary"
                isDestructive
                onClick={() => handleClear(true)}
                disabled={clearing || !level}
                title="Requires a level filter"
              >
                Clear level
              </Button>
            </FlexItem>
            <FlexItem>
              <Button variant="primary" isDestructive onClick={() => handleClear(false)} disabled={clearing}>
                Clear all
              </Button>
            </FlexItem>
          </Flex>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <Flex justify="space-between" align="center" style={{ width: '100%' }}>
            <span>
              <strong>{total}</strong> {total === 1 ? 'entry' : 'entries'}
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
                  <th scope="col" style={{ width: '150px' }}>
                    Time
                  </th>
                  <th scope="col" style={{ width: '80px' }}>
                    Level
                  </th>
                  <th scope="col" style={{ width: '100px' }}>
                    Source
                  </th>
                  <th scope="col">Message</th>
                  <th scope="col" style={{ width: '80px' }}>
                    Detail
                  </th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={5} style={{ padding: '16px' }}>
                      No log entries yet.
                    </td>
                  </tr>
                ) : (
                  items.map((row) => (
                    <Fragment key={row.id}>
                      <tr>
                        <td>{row.created_at || '—'}</td>
                        <td>
                          <code>{row.level}</code>
                        </td>
                        <td>
                          <code>{row.source}</code>
                        </td>
                        <td className="column-primary" style={{ wordBreak: 'break-word' }}>
                          {row.message}
                        </td>
                        <td>
                          <Button variant="link" onClick={() => toggleRow(row.id)}>
                            {expanded[row.id] ? 'Hide' : 'Show'}
                          </Button>
                        </td>
                      </tr>
                      {expanded[row.id] && (
                        <tr className="legaciti-error-detail">
                          <td colSpan={5} style={{ background: '#f6f7f7', padding: '12px 16px' }}>
                            <div style={{ fontSize: '12px', marginBottom: '8px' }}>
                              <strong>ID</strong> {row.id}
                              {row.user_id != null && (
                                <>
                                  {' '}
                                  · <strong>User</strong> {row.user_id}
                                </>
                              )}
                              {row.request_method && (
                                <>
                                  {' '}
                                  · <strong>{row.request_method}</strong>
                                </>
                              )}
                            </div>
                            {row.request_uri && (
                              <div
                                style={{
                                  fontSize: '12px',
                                  wordBreak: 'break-all',
                                  marginBottom: '8px',
                                  color: '#50575e',
                                }}
                              >
                                {row.request_uri}
                              </div>
                            )}
                            {row.ip && (
                              <div style={{ fontSize: '12px', marginBottom: '8px' }}>
                                <strong>IP</strong> {row.ip}
                              </div>
                            )}
                            {row.exception_type && (
                              <p style={{ margin: '0 0 8px' }}>
                                <strong>Exception:</strong> {row.exception_type}
                                {row.file && (
                                  <span style={{ color: '#50575e' }}>
                                    {' '}
                                    in {row.file}:{row.line}
                                  </span>
                                )}
                              </p>
                            )}
                            <p style={{ margin: '0 0 4px', fontWeight: 600 }}>Context (JSON)</p>
                            <pre
                              style={{
                                maxHeight: '200px',
                                overflow: 'auto',
                                fontSize: '12px',
                                margin: '0 0 12px',
                                padding: '8px',
                                background: '#fff',
                                border: '1px solid #c3c4c7',
                              }}
                            >
                              {formatContext(row.context)}
                            </pre>
                            {row.stack && (
                              <>
                                <p style={{ margin: '0 0 4px', fontWeight: 600 }}>Stack</p>
                                <pre
                                  style={{
                                    maxHeight: '240px',
                                    overflow: 'auto',
                                    fontSize: '11px',
                                    margin: 0,
                                    padding: '8px',
                                    background: '#fff',
                                    border: '1px solid #c3c4c7',
                                    whiteSpace: 'pre-wrap',
                                    wordBreak: 'break-word',
                                  }}
                                >
                                  {row.stack}
                                </pre>
                              </>
                            )}
                          </td>
                        </tr>
                      )}
                    </Fragment>
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

render(<ErrorsApp />, document.getElementById('legaciti-errors'));
