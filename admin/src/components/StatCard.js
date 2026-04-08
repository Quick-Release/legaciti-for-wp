import { Card, CardBody, CardHeader } from '@wordpress/components';

export default function StatCard({ title, value, description }) {
    return (
        <Card>
            <CardHeader>
                <span style={{ fontSize: '13px', fontWeight: 500, color: '#757575' }}>
                    {title}
                </span>
            </CardHeader>
            <CardBody>
                <div style={{ fontSize: '32px', fontWeight: 600, lineHeight: 1.2 }}>
                    {value}
                </div>
                {description && (
                    <p style={{ color: '#757575', margin: '8px 0 0', fontSize: '13px' }}>
                        {description}
                    </p>
                )}
            </CardBody>
        </Card>
    );
}
