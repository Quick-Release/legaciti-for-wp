import { render, useState, useEffect } from '@wordpress/element';
import {
    Card,
    CardBody,
    CardHeader,
    TextControl,
    SelectControl,
    CheckboxControl,
    Button,
    Spinner,
    Notice,
    Flex,
    FlexItem,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

function SettingsApp() {
    const [settings, setSettings] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [saveNotice, setSaveNotice] = useState(null);
    const [syncResult, setSyncResult] = useState(null);

    useEffect(() => {
        apiFetch({ path: '/legaciti/v1/settings' })
            .then((response) => {
                setSettings(response);
                setLoading(false);
            })
            .catch(() => {
                setSettings({
                    api_base_url: 'https://api.legaciti.org',
                    api_key: '',
                    sync_frequency: 'daily',
                    url_prefix: '',
                    remove_on_uninstall: false,
                });
                setLoading(false);
            });
    }, []);

    const handleSave = () => {
        setSaving(true);
        setSaveNotice(null);

        apiFetch({
            path: '/legaciti/v1/settings',
            method: 'POST',
            data: settings,
        })
            .then(() => {
                setSaveNotice({ status: 'success', message: 'Settings saved.' });
            })
            .catch((err) => {
                setSaveNotice({ status: 'error', message: err.message || 'Failed to save.' });
            })
            .finally(() => {
                setSaving(false);
            });
    };

    const handleSync = () => {
        setSyncing(true);
        setSyncResult(null);

        apiFetch({
            path: '/legaciti/v1/sync',
            method: 'POST',
        })
            .then((response) => {
                setSyncResult(response);
            })
            .catch((err) => {
                setSyncResult({ success: false, data: { errors: [err.message || 'Sync failed.'] } });
            })
            .finally(() => {
                setSyncing(false);
            });
    };

    if (loading) {
        return (
            <div style={{ padding: '20px', textAlign: 'center' }}>
                <Spinner style={{ width: '40px', height: '40px' }} />
            </div>
        );
    }

    const updateSetting = (key, value) => {
        setSettings((prev) => ({ ...prev, [key]: value }));
    };

    return (
        <div style={{ padding: '20px 0' }}>
            {saveNotice && (
                <Notice
                    status={saveNotice.status}
                    isDismissible
                    onRemove={() => setSaveNotice(null)}
                >
                    {saveNotice.message}
                </Notice>
            )}

            <Card>
                <CardHeader><h3>API Configuration</h3></CardHeader>
                <CardBody>
                    <TextControl
                        label="API Base URL"
                        value={settings.api_base_url}
                        onChange={(val) => updateSetting('api_base_url', val)}
                        placeholder="https://api.legaciti.org"
                    />
                    <TextControl
                        label="API Key"
                        type="password"
                        value={settings.api_key}
                        onChange={(val) => updateSetting('api_key', val)}
                        autoComplete="off"
                    />
                    <SelectControl
                        label="Sync Frequency"
                        value={settings.sync_frequency}
                        onChange={(val) => updateSetting('sync_frequency', val)}
                        options={[
                            { label: 'Hourly', value: 'hourly' },
                            { label: 'Twice Daily', value: 'twicedaily' },
                            { label: 'Daily', value: 'daily' },
                            { label: 'Manual Only', value: 'manual' },
                        ]}
                    />
                    <TextControl
                        label="People URL Prefix"
                        value={settings.url_prefix}
                        onChange={(val) => updateSetting('url_prefix', val)}
                        placeholder="Leave empty for root-level slugs (/asoares)"
                    />
                    <p style={{ color: '#757575', marginTop: '-10px', fontSize: '13px' }}>
                        People profile URLs. Empty = <code>/asoares</code>, set "people" ={' '}
                        <code>/people/asoares</code>. Requires flush of rewrite rules after change.
                    </p>
                </CardBody>
            </Card>

            <Card style={{ marginTop: '20px' }}>
                <CardHeader><h3>Uninstall</h3></CardHeader>
                <CardBody>
                    <CheckboxControl
                        label="Delete all tables and settings when the plugin is uninstalled."
                        checked={settings.remove_on_uninstall}
                        onChange={(val) => updateSetting('remove_on_uninstall', val)}
                    />
                </CardBody>
            </Card>

            <div style={{ marginTop: '20px' }}>
                <Button variant="primary" onClick={handleSave} isBusy={saving}>
                    {saving ? 'Saving...' : 'Save Settings'}
                </Button>
            </div>

            <Card style={{ marginTop: '30px' }}>
                <CardHeader><h3>Manual Sync</h3></CardHeader>
                <CardBody>
                    <Flex align="center" gap={3}>
                        <FlexItem>
                            <Button variant="secondary" onClick={handleSync} isBusy={syncing}>
                                {syncing ? 'Syncing...' : 'Sync Now'}
                            </Button>
                        </FlexItem>
                        <FlexItem>
                            {settings.last_sync && (
                                <span style={{ color: '#757575' }}>
                                    Last sync: {settings.last_sync}
                                </span>
                            )}
                        </FlexItem>
                    </Flex>

                    {syncResult && (
                        <div style={{ marginTop: '15px' }}>
                            <Notice
                                status={syncResult.success ? 'success' : 'error'}
                                isDismissible
                                onRemove={() => setSyncResult(null)}
                            >
                                {syncResult.success ? 'Sync completed.' : 'Sync failed.'}
                            </Notice>
                            {syncResult.data && (
                                <pre style={{
                                    background: '#f6f7f7',
                                    padding: '12px',
                                    maxHeight: '300px',
                                    overflow: 'auto',
                                    fontSize: '12px',
                                }}>
                                    {JSON.stringify(syncResult.data, null, 2)}
                                </pre>
                            )}
                        </div>
                    )}
                </CardBody>
            </Card>
        </div>
    );
}

render(<SettingsApp />, document.getElementById('legaciti-settings'));
