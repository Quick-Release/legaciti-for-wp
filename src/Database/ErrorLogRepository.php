<?php

declare(strict_types=1);

namespace LegacitiForWp\Database;

final class ErrorLogRepository
{
    private function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'leg_error_logs';
    }

    /**
     * @param array{
     *   level?: string,
     *   source?: string,
     *   message?: string,
     *   context?: array<string, mixed>,
     *   exception_type?: string|null,
     *   file?: string|null,
     *   line?: int|null,
     *   stack?: string|null,
     *   user_id?: int|null,
     *   request_uri?: string|null,
     *   request_method?: string|null,
     *   ip?: string|null,
     * } $row
     */
    public function insert(array $row): int
    {
        global $wpdb;

        $context = $row['context'] ?? [];
        $ctxJson = wp_json_encode(
            is_array($context) ? $context : [],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        $data = [
            'level' => isset($row['level']) && is_string($row['level']) ? substr($row['level'], 0, 20) : 'info',
            'source' => isset($row['source']) && is_string($row['source']) ? substr($row['source'], 0, 100) : 'unknown',
            'message' => isset($row['message']) && is_string($row['message']) ? $row['message'] : '',
            'context' => $ctxJson !== false ? $ctxJson : '{}',
            'exception_type' => isset($row['exception_type']) && is_string($row['exception_type'])
                ? substr($row['exception_type'], 0, 255)
                : null,
            'file' => isset($row['file']) && is_string($row['file']) ? substr($row['file'], 0, 500) : null,
            'line' => isset($row['line']) && is_int($row['line']) ? $row['line'] : null,
            'stack' => isset($row['stack']) && is_string($row['stack']) ? $row['stack'] : null,
            'user_id' => isset($row['user_id']) && is_int($row['user_id']) ? $row['user_id'] : null,
            'request_uri' => isset($row['request_uri']) && is_string($row['request_uri']) ? $row['request_uri'] : null,
            'request_method' => isset($row['request_method']) && is_string($row['request_method'])
                ? substr($row['request_method'], 0, 10)
                : null,
            'ip' => isset($row['ip']) && is_string($row['ip']) ? substr($row['ip'], 0, 45) : null,
        ];

        $wpdb->insert($this->tableName(), $data);

        return (int) $wpdb->insert_id;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function findPage(
        int $page,
        int $perPage,
        ?string $level,
        ?string $source,
        string $searchMessage = ''
    ): array {
        global $wpdb;

        $page = max(1, $page);
        $perPage = min(200, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $table = $this->tableName();

        $where = ['1=1'];
        $args = [];

        if ($level !== null && $level !== '' && in_array($level, ['error', 'warning', 'info', 'debug'], true)) {
            $where[] = 'level = %s';
            $args[] = $level;
        }

        if ($source !== null && $source !== '') {
            $where[] = 'source LIKE %s';
            $args[] = '%' . $wpdb->esc_like($source) . '%';
        }

        if ($searchMessage !== '') {
            $where[] = 'message LIKE %s';
            $args[] = '%' . $wpdb->esc_like($searchMessage) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$whereSql}";
        $total = $args === []
            ? (int) $wpdb->get_var($countSql)
            : (int) $wpdb->get_var($wpdb->prepare($countSql, ...$args));

        $args[] = $perPage;
        $args[] = $offset;

        $sql = "SELECT * FROM {$table} WHERE {$whereSql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->hydrateRow($r);
        }

        return ['rows' => $out, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function hydrateRow(array $r): array
    {
        $ctx = $r['context'] ?? '';
        $decoded = null;
        if (is_string($ctx) && $ctx !== '') {
            $decoded = json_decode($ctx, true);
        }

        return [
            'id' => (int) ($r['id'] ?? 0),
            'level' => (string) ($r['level'] ?? ''),
            'source' => (string) ($r['source'] ?? ''),
            'message' => (string) ($r['message'] ?? ''),
            'context' => is_array($decoded) ? $decoded : $ctx,
            'exception_type' => $r['exception_type'] ?? null,
            'file' => $r['file'] ?? null,
            'line' => isset($r['line']) && $r['line'] !== null ? (int) $r['line'] : null,
            'stack' => $r['stack'] ?? null,
            'user_id' => isset($r['user_id']) && $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'request_uri' => $r['request_uri'] ?? null,
            'request_method' => $r['request_method'] ?? null,
            'ip' => $r['ip'] ?? null,
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }

    public function deleteAll(?string $level = null): int
    {
        global $wpdb;

        $table = $this->tableName();

        if ($level !== null && $level !== '' && in_array($level, ['error', 'warning', 'info', 'debug'], true)) {
            return (int) $wpdb->delete($table, ['level' => $level], ['%s']);
        }

        return (int) $wpdb->query("DELETE FROM {$table}");
    }
}
