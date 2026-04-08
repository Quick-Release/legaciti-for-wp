<?php

declare(strict_types=1);

namespace LegacitiForWp\Database;

use LegacitiForWp\Models\Publication;

final class PublicationRepository
{
    private function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'leg_publications';
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
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'abstract' => $data['abstract'] ?? null,
            'publication_date' => $data['publication_date'] ?? null,
            'doi' => $data['doi'] ?? null,
            'journal' => $data['journal'] ?? null,
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

    public function findById(int $id): ?Publication
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->tableName() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        return $row ? Publication::fromArray($row) : null;
    }

    public function findBySlug(string $slug): ?Publication
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . $this->tableName() . " WHERE slug = %s AND status = 'active'",
                $slug
            ),
            ARRAY_A
        );

        return $row ? Publication::fromArray($row) : null;
    }

    /**
     * @return list<Publication>
     */
    public function findAllActive(int $page = 1, int $perPage = 20): array
    {
        global $wpdb;

        $offset = ($page - 1) * $perPage;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . $this->tableName() . " WHERE status = 'active' ORDER BY publication_date DESC, title LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return array_map(fn(array $row): Publication => Publication::fromArray($row), $rows ?: []);
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
                "SELECT * FROM " . $this->tableName() . " WHERE status = 'active' AND (title LIKE %s OR journal LIKE %s) ORDER BY publication_date DESC, title LIMIT %d OFFSET %d",
                $like,
                $like,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return array_map(fn(array $row): Publication => Publication::fromArray($row), $rows ?: []);
    }

    /**
     * @return list<Publication>
     */
    public function findByPersonId(int $personId): array
    {
        global $wpdb;

        $junctionTable = $wpdb->prefix . 'leg_person_publications';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.* FROM " . $this->tableName() . " p INNER JOIN $junctionTable j ON p.id = j.publication_id WHERE j.person_id = %d AND p.status = 'active' ORDER BY j.position ASC, p.publication_date DESC",
                $personId
            ),
            ARRAY_A
        );

        return array_map(fn(array $row): Publication => Publication::fromArray($row), $rows ?: []);
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
