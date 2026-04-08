<?php

declare(strict_types=1);

namespace LegacitiForWp\Database;

use LegacitiForWp\Models\Person;
use LegacitiForWp\Models\Publication;

final class RelationRepository
{
    private function junctionTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'leg_person_publications';
    }

    private function peopleTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'leg_people';
    }

    private function publicationsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'leg_publications';
    }

    public function syncForPublication(int $publicationId, array $relations): void
    {
        global $wpdb;

        $wpdb->delete($this->junctionTable(), ['publication_id' => $publicationId]);

        foreach ($relations as $relation) {
            $wpdb->insert($this->junctionTable(), [
                'person_id' => $relation['person_id'],
                'publication_id' => $publicationId,
                'role' => $relation['role'] ?? null,
                'position' => $relation['position'] ?? null,
            ]);
        }
    }

    /**
     * @return list<Publication>
     */
    public function getPublicationsForPerson(int $personId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, j.role, j.position FROM {$this->publicationsTable()} p INNER JOIN {$this->junctionTable()} j ON p.id = j.publication_id WHERE j.person_id = %d AND p.status = 'active' ORDER BY j.position ASC, p.publication_date DESC",
                $personId
            ),
            ARRAY_A
        );

        return array_map(fn(array $row): Publication => Publication::fromArray($row), $rows ?: []);
    }

    /**
     * @return list<array{person: Person, role: string|null, position: int|null}>
     */
    public function getPeopleForPublication(int $publicationId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, j.role, j.position FROM {$this->peopleTable()} p INNER JOIN {$this->junctionTable()} j ON p.id = j.person_id WHERE j.publication_id = %d AND p.status = 'active' ORDER BY j.position ASC, p.last_name, p.first_name",
                $publicationId
            ),
            ARRAY_A
        );

        return array_map(function (array $row): array {
            $role = $row['role'] ?? null;
            $position = isset($row['position']) ? (int) $row['position'] : null;
            unset($row['role'], $row['position']);

            return [
                'person' => Person::fromArray($row),
                'role' => $role,
                'position' => $position,
            ];
        }, $rows ?: []);
    }
}
