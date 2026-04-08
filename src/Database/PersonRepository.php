<?php

declare(strict_types=1);

namespace LegacitiForWp\Database;

use LegacitiForWp\Models\Person;

final class PersonRepository
{
    private function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'leg_people';
    }

    public function upsert(array $data): int
    {
        global $wpdb;

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id FROM ' . $this->tableName() . ' WHERE external_id = %s',
                $data['external_id']
            )
        );

        $row = [
            'external_id' => $data['external_id'],
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'nickname' => $data['nickname'] ?? '',
            'email' => $data['email'] ?? null,
            'title' => $data['title'] ?? null,
            'bio' => $data['bio'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'raw_api_data' => isset($data['raw_api_data']) ? wp_json_encode($data['raw_api_data']) : null,
            'status' => 'active',
            'synced_at' => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($this->tableName(), $row, ['id' => $existing->id]);
            return (int) $existing->id;
        }

        $wpdb->insert($this->tableName(), $row);
        return (int) $wpdb->insert_id;
    }

    public function findById(int $id): ?Person
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        return $row ? Person::fromArray($row) : null;
    }

    public function findByExternalId(string $externalId): ?Person
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE external_id = %s', $externalId),
            ARRAY_A
        );

        return $row ? Person::fromArray($row) : null;
    }

    public function findByNickname(string $nickname): ?Person
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . $this->tableName() . " WHERE nickname = %s AND status = 'active'",
                $nickname
            ),
            ARRAY_A
        );

        return $row ? Person::fromArray($row) : null;
    }

    /**
     * @return list<Person>
     */
    public function findAllActive(int $page = 1, int $perPage = 20): array
    {
        global $wpdb;

        $offset = ($page - 1) * $perPage;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . $this->tableName() . " WHERE status = 'active' ORDER BY last_name, first_name LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return array_map(fn(array $row): Person => Person::fromArray($row), $rows ?: []);
    }

    public function countActive(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . $this->tableName() . " WHERE status = 'active'"
        );
    }

    public function searchActive(string $query, int $page = 1, int $perPage = 20): array
    {
        global $wpdb;

        $like = '%' . $wpdb->esc_like($query) . '%';
        $offset = ($page - 1) * $perPage;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . $this->tableName() . " WHERE status = 'active' AND (first_name LIKE %s OR last_name LIKE %s OR nickname LIKE %s) ORDER BY last_name, first_name LIMIT %d OFFSET %d",
                $like,
                $like,
                $like,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return array_map(fn(array $row): Person => Person::fromArray($row), $rows ?: []);
    }

    public function markInactiveExcept(array $activeExternalIds): int
    {
        global $wpdb;

        if (count($activeExternalIds) === 0) {
            return (int) $wpdb->query(
                "UPDATE " . $this->tableName() . " SET status = 'inactive' WHERE status = 'active'"
            );
        }

        $placeholders = implode(',', array_fill(0, count($activeExternalIds), '%s'));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . $this->tableName() . " SET status = 'inactive' WHERE status = 'active' AND external_id NOT IN ($placeholders)",
                ...$activeExternalIds
            )
        );
    }
}
