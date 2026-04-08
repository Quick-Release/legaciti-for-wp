<?php

declare(strict_types=1);

namespace LegacitiForWp\Models;

final readonly class SyncResult
{
    /**
     * @param int $peopleSynced
     * @param int $publicationsSynced
     * @param int $relationsSynced
     * @param int $peopleDeactivated
     * @param int $publicationsDeactivated
     * @param list<string> $errors
     */
    public function __construct(
        public int $peopleSynced = 0,
        public int $publicationsSynced = 0,
        public int $relationsSynced = 0,
        public int $peopleDeactivated = 0,
        public int $publicationsDeactivated = 0,
        public array $errors = [],
    ) {
    }

    public function withError(string $error): self
    {
        return new self(
            peopleSynced: $this->peopleSynced,
            publicationsSynced: $this->publicationsSynced,
            relationsSynced: $this->relationsSynced,
            peopleDeactivated: $this->peopleDeactivated,
            publicationsDeactivated: $this->publicationsDeactivated,
            errors: [...$this->errors, $error],
        );
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function toArray(): array
    {
        return [
            'people_synced' => $this->peopleSynced,
            'publications_synced' => $this->publicationsSynced,
            'relations_synced' => $this->relationsSynced,
            'people_deactivated' => $this->peopleDeactivated,
            'publications_deactivated' => $this->publicationsDeactivated,
            'errors' => $this->errors,
        ];
    }
}
