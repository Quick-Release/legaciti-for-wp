<?php

declare(strict_types=1);

namespace LegacitiForWp\Models;

final readonly class Publication
{
    public function __construct(
        public int $id,
        public string $externalId,
        public string $title,
        public string $slug,
        public ?string $abstract,
        public ?string $publicationDate,
        public ?string $doi,
        public ?string $journal,
        public string $status,
        public ?string $rawApiData,
        public ?string $syncedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            externalId: (string) ($data['external_id'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            abstract: $data['abstract'] ?? null,
            publicationDate: $data['publication_date'] ?? null,
            doi: $data['doi'] ?? null,
            journal: $data['journal'] ?? null,
            status: (string) ($data['status'] ?? 'active'),
            rawApiData: $data['raw_api_data'] ?? null,
            syncedAt: $data['synced_at'] ?? null,
            createdAt: (string) ($data['created_at'] ?? ''),
            updatedAt: (string) ($data['updated_at'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'title' => $this->title,
            'slug' => $this->slug,
            'abstract' => $this->abstract,
            'publication_date' => $this->publicationDate,
            'doi' => $this->doi,
            'journal' => $this->journal,
            'status' => $this->status,
            'synced_at' => $this->syncedAt,
        ];
    }
}
