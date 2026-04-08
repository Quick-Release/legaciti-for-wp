<?php

declare(strict_types=1);

namespace LegacitiForWp\Api;

use LegacitiForWp\Database\PersonRepository;
use LegacitiForWp\Database\PublicationRepository;
use LegacitiForWp\Database\RelationRepository;
use LegacitiForWp\Models\SyncResult;

final class SyncService
{
    private const LOCK_TTL = 1800;

    public function __construct(
        private readonly Client $client,
        private readonly PersonRepository $personRepo,
        private readonly PublicationRepository $publicationRepo,
        private readonly RelationRepository $relationRepo,
    ) {
    }

    public function sync(): SyncResult
    {
        if (! $this->acquireLock()) {
            return new SyncResult(errors: ['Sync already in progress.']);
        }

        try {
            $result = $this->doSync();
        } catch (\Throwable $e) {
            $result = (new SyncResult())->withError($e->getMessage());
        } finally {
            $this->releaseLock();
        }

        $this->updateLastSyncTimestamp();

        return $result;
    }

    private function doSync(): SyncResult
    {
        $result = new SyncResult();
        $activeExternalPersonIds = [];
        $activeExternalPublicationIds = [];

        $peoplePage = 1;
        do {
            try {
                $response = $this->client->getPeople($peoplePage);
                $people = $response['data'] ?? $response;

                if (! is_array($people)) {
                    break;
                }

                foreach ($people as $personData) {
                    try {
                        $this->personRepo->upsert($personData);
                        $activeExternalPersonIds[] = $personData['external_id'];
                        $result = new SyncResult(
                            peopleSynced: $result->peopleSynced + 1,
                            publicationsSynced: $result->publicationsSynced,
                            relationsSynced: $result->relationsSynced,
                            peopleDeactivated: $result->peopleDeactivated,
                            publicationsDeactivated: $result->publicationsDeactivated,
                            errors: $result->errors,
                        );
                    } catch (\Throwable $e) {
                        $result = $result->withError("Person {$personData['external_id']}: {$e->getMessage()}");
                    }
                }

                $peoplePage++;
                $hasMore = ($response['next_page_url'] ?? null) !== null;
            } catch (\Throwable $e) {
                $result = $result->withError("People page {$peoplePage}: {$e->getMessage()}");
                break;
            }
        } while ($hasMore ?? false);

        $pubPage = 1;
        do {
            try {
                $response = $this->client->getPublications($pubPage);
                $publications = $response['data'] ?? $response;

                if (! is_array($publications)) {
                    break;
                }

                foreach ($publications as $pubData) {
                    try {
                        $pubId = $this->publicationRepo->upsert($pubData);
                        $activeExternalPublicationIds[] = $pubData['external_id'];

                        $relations = $pubData['people'] ?? [];
                        if (is_array($relations) && count($relations) > 0) {
                            $this->syncPublicationRelations($pubId, $relations);
                            $result = new SyncResult(
                                peopleSynced: $result->peopleSynced,
                                publicationsSynced: $result->publicationsSynced + 1,
                                relationsSynced: $result->relationsSynced + count($relations),
                                peopleDeactivated: $result->peopleDeactivated,
                                publicationsDeactivated: $result->publicationsDeactivated,
                                errors: $result->errors,
                            );
                        } else {
                            $result = new SyncResult(
                                peopleSynced: $result->peopleSynced,
                                publicationsSynced: $result->publicationsSynced + 1,
                                relationsSynced: $result->relationsSynced,
                                peopleDeactivated: $result->peopleDeactivated,
                                publicationsDeactivated: $result->publicationsDeactivated,
                                errors: $result->errors,
                            );
                        }
                    } catch (\Throwable $e) {
                        $result = $result->withError("Publication {$pubData['external_id']}: {$e->getMessage()}");
                    }
                }

                $pubPage++;
                $hasMore = ($response['next_page_url'] ?? null) !== null;
            } catch (\Throwable $e) {
                $result = $result->withError("Publications page {$pubPage}: {$e->getMessage()}");
                break;
            }
        } while ($hasMore ?? false);

        $peopleDeactivated = $this->personRepo->markInactiveExcept($activeExternalPersonIds);
        $publicationsDeactivated = $this->publicationRepo->markInactiveExcept($activeExternalPublicationIds);

        return new SyncResult(
            peopleSynced: $result->peopleSynced,
            publicationsSynced: $result->publicationsSynced,
            relationsSynced: $result->relationsSynced,
            peopleDeactivated: $peopleDeactivated,
            publicationsDeactivated: $publicationsDeactivated,
            errors: $result->errors,
        );
    }

    private function syncPublicationRelations(int $publicationId, array $peopleRelations): void
    {
        $syncData = [];
        foreach ($peopleRelations as $rel) {
            $person = $this->personRepo->findByExternalId($rel['external_id'] ?? $rel['person_external_id'] ?? '');
            if ($person !== null) {
                $syncData[] = [
                    'person_id' => $person->id,
                    'role' => $rel['role'] ?? null,
                    'position' => $rel['position'] ?? $rel['order'] ?? null,
                ];
            }
        }

        $this->relationRepo->syncForPublication($publicationId, $syncData);
    }

    private function acquireLock(): bool
    {
        if (get_transient('legaciti_sync_lock') !== false) {
            return false;
        }

        set_transient('legaciti_sync_lock', time(), self::LOCK_TTL);
        return true;
    }

    private function releaseLock(): void
    {
        delete_transient('legaciti_sync_lock');
    }

    private function updateLastSyncTimestamp(): void
    {
        $settings = get_option('legaciti_settings', []);
        $settings['last_sync'] = current_time('mysql');
        update_option('legaciti_settings', $settings);
    }
}
