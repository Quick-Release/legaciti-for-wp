<?php

declare(strict_types=1);

namespace LegacitiForWp\Api;

use LegacitiForWp\Database\PersonRepository;
use LegacitiForWp\Debug\PluginLog;
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
            $locked = new SyncResult(errors: ['Sync already in progress.']);
            PluginLog::info('sync', 'Full sync skipped — lock held', ['mode' => 'full']);

            return $locked;
        }

        try {
            $result = $this->doSync();
        } catch (\Throwable $e) {
            PluginLog::exception('sync', 'Full sync threw', $e, ['mode' => 'full']);
            $result = (new SyncResult())->withError($e->getMessage());
        } finally {
            $this->releaseLock();
        }

        $this->updateLastSyncTimestamp();
        $this->logSyncResult('full', $result);

        return $result;
    }

    /**
     * Fetch all people from the Legaciti Consumer API and upsert into WordPress (no publications).
     */
    public function syncPeopleOnly(): SyncResult
    {
        if (! $this->acquireLock()) {
            PluginLog::info('sync', 'People-only sync skipped — lock held', ['mode' => 'people']);

            return new SyncResult(errors: ['Sync already in progress.']);
        }

        try {
            $result = $this->doSyncPeopleOnly();
        } catch (\Throwable $e) {
            PluginLog::exception('sync', 'People-only sync threw', $e, ['mode' => 'people']);
            $result = (new SyncResult())->withError($e->getMessage());
        } finally {
            $this->releaseLock();
        }

        $this->updateLastSyncTimestamp();
        $this->logSyncResult('people', $result);

        return $result;
    }

    /**
     * Fetch all publications from the Legaciti Consumer API and upsert into WordPress (including author links when people exist locally).
     */
    public function syncPublicationsOnly(): SyncResult
    {
        if (! $this->acquireLock()) {
            PluginLog::info('sync', 'Publications-only sync skipped — lock held', ['mode' => 'publications']);

            return new SyncResult(errors: ['Sync already in progress.']);
        }

        try {
            $result = $this->doSyncPublicationsOnly();
        } catch (\Throwable $e) {
            PluginLog::exception('sync', 'Publications-only sync threw', $e, ['mode' => 'publications']);
            $result = (new SyncResult())->withError($e->getMessage());
        } finally {
            $this->releaseLock();
        }

        $this->updateLastSyncTimestamp();
        $this->logSyncResult('publications', $result);

        return $result;
    }

    private function doSync(): SyncResult
    {
        $result = new SyncResult();
        $activeExternalPersonIds = [];
        $activeExternalPublicationIds = [];

        $peoplePage = 1;
        $hasMorePeople = true;
        while ($hasMorePeople) {
            try {
                $response = $this->client->getPeople($peoplePage, 100);
                $people = $this->peopleListFromResponse($response);

                if (! is_array($people)) {
                    break;
                }

                foreach ($people as $personData) {
                    if (! is_array($personData)) {
                        continue;
                    }
                    $label = $this->personErrorLabel($personData);
                    try {
                        $normalized = $this->normalizePersonFromApi($personData);
                        $this->personRepo->upsert($normalized);
                        $activeExternalPersonIds[] = $normalized['external_id'];
                        $result = new SyncResult(
                            peopleSynced: $result->peopleSynced + 1,
                            publicationsSynced: $result->publicationsSynced,
                            relationsSynced: $result->relationsSynced,
                            peopleDeactivated: $result->peopleDeactivated,
                            publicationsDeactivated: $result->publicationsDeactivated,
                            errors: $result->errors,
                        );
                    } catch (\Throwable $e) {
                        $result = $result->withError("Person {$label}: {$e->getMessage()}");
                    }
                }

                $peoplePage++;
                $hasMorePeople = $this->peopleHasNextPage($response, $peoplePage);
            } catch (\Throwable $e) {
                $result = $result->withError("People page {$peoplePage}: {$e->getMessage()}");
                break;
            }
        }

        $pubPage = 1;
        $hasMorePubs = true;
        while ($hasMorePubs) {
            try {
                $response = $this->client->getPublications($pubPage, 100);
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
                $hasMorePubs = $this->publicationsHasNextPage($response, $pubPage);
            } catch (\Throwable $e) {
                $result = $result->withError("Publications page {$pubPage}: {$e->getMessage()}");
                break;
            }
        }

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

    private function doSyncPeopleOnly(): SyncResult
    {
        $result = new SyncResult();
        $activeExternalPersonIds = [];

        $peoplePage = 1;
        $hasMorePeople = true;
        while ($hasMorePeople) {
            try {
                $response = $this->client->getPeople($peoplePage, 100);
                $people = $this->peopleListFromResponse($response);

                if (! is_array($people)) {
                    break;
                }

                foreach ($people as $personData) {
                    if (! is_array($personData)) {
                        continue;
                    }
                    $label = $this->personErrorLabel($personData);
                    try {
                        $normalized = $this->normalizePersonFromApi($personData);
                        $this->personRepo->upsert($normalized);
                        $activeExternalPersonIds[] = $normalized['external_id'];
                        $result = new SyncResult(
                            peopleSynced: $result->peopleSynced + 1,
                            publicationsSynced: 0,
                            relationsSynced: 0,
                            peopleDeactivated: $result->peopleDeactivated,
                            publicationsDeactivated: 0,
                            errors: $result->errors,
                        );
                    } catch (\Throwable $e) {
                        $result = $result->withError("Person {$label}: {$e->getMessage()}");
                    }
                }

                $peoplePage++;
                $hasMorePeople = $this->peopleHasNextPage($response, $peoplePage);
            } catch (\Throwable $e) {
                $result = $result->withError("People page {$peoplePage}: {$e->getMessage()}");
                break;
            }
        }

        $peopleDeactivated = $this->personRepo->markInactiveExcept($activeExternalPersonIds);

        return new SyncResult(
            peopleSynced: $result->peopleSynced,
            publicationsSynced: 0,
            relationsSynced: 0,
            peopleDeactivated: $peopleDeactivated,
            publicationsDeactivated: 0,
            errors: $result->errors,
        );
    }

    private function doSyncPublicationsOnly(): SyncResult
    {
        $result = new SyncResult();
        $activeExternalPublicationIds = [];

        $pubPage = 1;
        $hasMorePubs = true;
        while ($hasMorePubs) {
            try {
                $response = $this->client->getPublications($pubPage, 100);
                $publications = $response['data'] ?? $response;

                if (! is_array($publications)) {
                    break;
                }

                foreach ($publications as $pubData) {
                    if (! is_array($pubData)) {
                        continue;
                    }
                    $extId = isset($pubData['external_id']) ? (string) $pubData['external_id'] : 'unknown';
                    try {
                        $pubId = $this->publicationRepo->upsert($pubData);
                        $activeExternalPublicationIds[] = $pubData['external_id'];

                        $relations = $pubData['people'] ?? [];
                        if (is_array($relations) && count($relations) > 0) {
                            $this->syncPublicationRelations($pubId, $relations);
                            $result = new SyncResult(
                                peopleSynced: 0,
                                publicationsSynced: $result->publicationsSynced + 1,
                                relationsSynced: $result->relationsSynced + count($relations),
                                peopleDeactivated: 0,
                                publicationsDeactivated: $result->publicationsDeactivated,
                                errors: $result->errors,
                            );
                        } else {
                            $result = new SyncResult(
                                peopleSynced: 0,
                                publicationsSynced: $result->publicationsSynced + 1,
                                relationsSynced: $result->relationsSynced,
                                peopleDeactivated: 0,
                                publicationsDeactivated: $result->publicationsDeactivated,
                                errors: $result->errors,
                            );
                        }
                    } catch (\Throwable $e) {
                        $result = $result->withError("Publication {$extId}: {$e->getMessage()}");
                    }
                }

                $pubPage++;
                $hasMorePubs = $this->publicationsHasNextPage($response, $pubPage);
            } catch (\Throwable $e) {
                $result = $result->withError("Publications page {$pubPage}: {$e->getMessage()}");
                break;
            }
        }

        $publicationsDeactivated = $this->publicationRepo->markInactiveExcept($activeExternalPublicationIds);

        return new SyncResult(
            peopleSynced: 0,
            publicationsSynced: $result->publicationsSynced,
            relationsSynced: $result->relationsSynced,
            peopleDeactivated: 0,
            publicationsDeactivated: $publicationsDeactivated,
            errors: $result->errors,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function publicationsHasNextPage(array $response, int $nextPage): bool
    {
        if (isset($response['pages']) && is_numeric($response['pages'])) {
            return $nextPage <= (int) $response['pages'];
        }

        return ($response['next_page_url'] ?? null) !== null;
    }

    /**
     * @param array<string, mixed> $response
     * @return list<array<string, mixed>>
     */
    private function peopleListFromResponse(array $response): array
    {
        if (isset($response['people']) && is_array($response['people'])) {
            $list = $response['people'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            $list = $response['data'];
        } elseif (array_is_list($response)) {
            $list = $response;
        } else {
            return [];
        }

        $out = [];
        foreach ($list as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function peopleHasNextPage(array $response, int $nextPage): bool
    {
        if (isset($response['pages']) && is_numeric($response['pages'])) {
            return $nextPage <= (int) $response['pages'];
        }

        return ($response['next_page_url'] ?? null) !== null;
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private function normalizePersonFromApi(array $p): array
    {
        if (isset($p['orcid_id']) && is_string($p['orcid_id']) && $p['orcid_id'] !== '') {
            return $this->mapConsumerApiPerson($p);
        }

        return $p;
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private function mapConsumerApiPerson(array $p): array
    {
        $orcid = (string) $p['orcid_id'];
        $displayName = '';

        if (isset($p['name']) && is_string($p['name'])) {
            $displayName = trim($p['name']);
        } else {
            $nameObj = is_array($p['name'] ?? null) ? $p['name'] : [];
            if ($nameObj !== []) {
                $displayName = $nameObj['en'] ?? $nameObj['pt'] ?? '';
                if ($displayName === '' || $displayName === null) {
                    $first = reset($nameObj);
                    $displayName = is_string($first) ? $first : '';
                }
            }
            $displayName = is_string($displayName) ? trim($displayName) : '';
        }

        [$first, $last] = $this->splitDisplayName($displayName);

        $bio = null;
        $bioObj = $p['biography'] ?? null;
        if (is_array($bioObj)) {
            $rawBio = $bioObj['en'] ?? $bioObj['pt'] ?? null;
            if (is_string($rawBio) && $rawBio !== '') {
                $bio = $rawBio;
            } elseif ($rawBio === null) {
                foreach ($bioObj as $val) {
                    if (is_string($val) && $val !== '') {
                        $bio = $val;
                        break;
                    }
                }
            }
        }

        $nickname = $orcid;
        if (isset($p['slug']) && is_string($p['slug']) && $p['slug'] !== '') {
            $nickname = $p['slug'];
        }

        $avatarUrl = null;
        if (isset($p['photo_url']) && is_string($p['photo_url']) && $p['photo_url'] !== '') {
            $avatarUrl = $p['photo_url'];
        }

        $peopleType = null;
        if (isset($p['people_type']) && is_string($p['people_type']) && $p['people_type'] !== '') {
            $peopleType = $p['people_type'];
        }

        return [
            'external_id' => $orcid,
            'first_name' => $first,
            'last_name' => $last,
            'nickname' => $nickname,
            'email' => null,
            'title' => $peopleType,
            'bio' => $bio,
            'avatar_url' => $avatarUrl,
            'raw_api_data' => $p,
        ];
    }

    private function splitDisplayName(string $name): array
    {
        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/u', $name, 2, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return ['', ''];
        }

        $first = (string) ($parts[0] ?? '');
        $last = (string) ($parts[1] ?? '');

        return [$first, $last];
    }

    /**
     * @param array<string, mixed> $personData
     */
    private function personErrorLabel(array $personData): string
    {
        if (isset($personData['orcid_id']) && is_string($personData['orcid_id'])) {
            return $personData['orcid_id'];
        }
        if (isset($personData['slug']) && is_string($personData['slug']) && $personData['slug'] !== '') {
            return $personData['slug'];
        }
        if (isset($personData['external_id']) && is_string($personData['external_id'])) {
            return $personData['external_id'];
        }

        return 'unknown';
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

    private function logSyncResult(string $mode, SyncResult $result): void
    {
        foreach ($result->errors as $err) {
            PluginLog::warning('sync', $err, ['mode' => $mode]);
        }
        if (! $result->hasErrors()) {
            PluginLog::info('sync', "Sync ({$mode}) completed", $result->toArray());
        } else {
            PluginLog::warning('sync', "Sync ({$mode}) finished with errors", $result->toArray());
        }
    }
}
