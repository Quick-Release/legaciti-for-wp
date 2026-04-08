<?php

declare(strict_types=1);

namespace LegacitiForWp\Models;

final readonly class Person
{
    public function __construct(
        public int $id,
        public string $externalId,
        public string $firstName,
        public string $lastName,
        public string $nickname,
        public ?string $email,
        public ?string $title,
        public ?string $bio,
        public ?string $avatarUrl,
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
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            nickname: (string) ($data['nickname'] ?? ''),
            email: $data['email'] ?? null,
            title: $data['title'] ?? null,
            bio: $data['bio'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            status: (string) ($data['status'] ?? 'active'),
            rawApiData: $data['raw_api_data'] ?? null,
            syncedAt: $data['synced_at'] ?? null,
            createdAt: (string) ($data['created_at'] ?? ''),
            updatedAt: (string) ($data['updated_at'] ?? ''),
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->externalId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'nickname' => $this->nickname,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'title' => $this->title,
            'bio' => $this->bio,
            'avatar_url' => $this->avatarUrl,
            'status' => $this->status,
            'synced_at' => $this->syncedAt,
        ];
    }
}
