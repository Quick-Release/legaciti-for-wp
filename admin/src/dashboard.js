import { render, useState, useEffect } from '@wordpress/element';
import { Card, CardBody, CardHeader, Spinner, Flex, FlexItem, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import StatCard from './components/StatCard';

function DashboardApp() {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        apiFetch({ path: '/legaciti/v1/dashboard' })
            .then((response) => {
                setStats(response);
                setLoading(false);
            })
            .catch((err) => {
                setError(err.message || 'Failed to load dashboard data.');
                setLoading(false);
            });
    }, []);

    if (loading) {
        return (
            <div style={{ padding: '20px', textAlign: 'center' }}>
                <Spinner style={{ width: '40px', height: '40px' }} />
            </div>
        );
    }

    if (error) {
        return (
            <Notice status="error" isDismissible={false}>
                {error}
            </Notice>
        );
    }

    return (
        <div style={{ padding: '20px 0' }}>
            <Flex gap={4} wrap>
                <FlexItem style={{ flex: '1 1 200px', minWidth: '200px' }}>
                    <StatCard
                        title="Total People"
                        value={stats?.total_people ?? 0}
                        description="Active people synced from Legaciti"
                    />
                </FlexItem>
                <FlexItem style={{ flex: '1 1 200px', minWidth: '200px' }}>
                    <StatCard
                        title="Total Publications"
                        value={stats?.total_publications ?? 0}
                        description="Active publications synced from Legaciti"
                    />
                </FlexItem>
                <FlexItem style={{ flex: '1 1 200px', minWidth: '200px' }}>
                    <StatCard
                        title="Last Sync"
                        value={stats?.last_sync ?? 'Never'}
                        description="Last successful sync with api.legaciti.org"
                    />
                </FlexItem>
            </Flex>

            <Card style={{ marginTop: '20px' }}>
                <CardHeader>
                    <h3>Workspace Statistics</h3>
                </CardHeader>
                <CardBody>
                    <p style={{ color: '#757575' }}>
                        Additional statistics from api.legaciti.org will appear here once the API schema is configured.
                    </p>
                </CardBody>
            </Card>
        </div>
    );
}

render(<DashboardApp />, document.getElementById('legaciti-dashboard'));
