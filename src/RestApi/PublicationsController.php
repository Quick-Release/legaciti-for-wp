<?php

declare(strict_types=1);

namespace LegacitiForWp\RestApi;

use LegacitiForWp\Api\Client;
use LegacitiForWp\Api\SyncService;
use LegacitiForWp\Database\PublicationRepository;
use LegacitiForWp\Database\RelationRepository;
use LegacitiForWp\Debug\PluginLog;

final class PublicationsController
{
    private const NAMESPACE = 'legaciti/v1';
    private const ROUTE = '/publications';

    public function __construct(
        private readonly PublicationRepository $publicationRepo,
        private readonly RelationRepository $relationRepo,
        private readonly SyncService $syncService,
        private readonly Client $client,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getItems'],
                'permission_callback' => '__return_true',
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'default' => 20,
                        'sanitize_callback' => 'absint',
                    ],
                    'search' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/publications', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getAdminItems'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'default' => 20,
                        'sanitize_callback' => 'absint',
                    ],
                    'search' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'status' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'orderby' => [
                        'default' => 'publication_date',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'order' => [
                        'default' => 'desc',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/publications/sync', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'postAdminPublicationsSync'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/publications/connectivity', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getAdminConnectivity'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, self::ROUTE . '/(?P<slug>[a-zA-Z0-9_-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getItem'],
                'permission_callback' => '__return_true',
                'args' => [
                    'slug' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    public function postAdminPublicationsSync(): \WP_REST_Response
    {
        PluginLog::info('publications_admin', 'Publications-only sync triggered (REST)');

        $result = $this->syncService->syncPublicationsOnly();

        return new \WP_REST_Response($result->toArray());
    }

    public function getAdminConnectivity(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->client->checkPublicationsConnectivityFromSettings());
    }

    public function getAdminItems(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = min(100, max(1, (int) $request->get_param('per_page')));
        $search = (string) $request->get_param('search');
        $statusParam = (string) $request->get_param('status');

        $status = null;
        if ($statusParam === 'active' || $statusParam === 'inactive') {
            $status = $statusParam;
        }

        $orderbyRaw = (string) $request->get_param('orderby');
        $orderby = in_array($orderbyRaw, ['title', 'publication_date', 'journal', 'slug', 'doi'], true)
            ? $orderbyRaw
            : 'publication_date';

        $orderRaw = strtolower((string) $request->get_param('order'));
        $order = $orderRaw === 'asc' ? 'asc' : 'desc';

        $publications = $this->publicationRepo->findForAdmin($page, $perPage, $search, $status, $orderby, $order);
        $total = $this->publicationRepo->countForAdmin($search, $status);

        $data = array_map(fn($pub): array => $pub->toArray(), $publications);

        return new \WP_REST_Response([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ]);
    }

    public function getItems(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');
        $search = $request->get_param('search');

        if ($search !== '') {
            $publications = $this->publicationRepo->searchActive($search, $page, $perPage);
        } else {
            $publications = $this->publicationRepo->findAllActive($page, $perPage);
        }

        $total = $this->publicationRepo->countActive();

        $data = array_map(fn($pub): array => $pub->toArray(), $publications);

        return new \WP_REST_Response([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    public function getItem(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $slug = $request->get_param('slug');
        $publication = $this->publicationRepo->findBySlug($slug);

        if ($publication === null) {
            return new \WP_Error(
                'legaciti_publication_not_found',
                'Publication not found.',
                ['status' => 404]
            );
        }

        $people = $this->relationRepo->getPeopleForPublication($publication->id);

        return new \WP_REST_Response([
            'data' => $publication->toArray(),
            'people' => array_map(fn(array $entry): array => [
                'person' => $entry['person']->toArray(),
                'role' => $entry['role'],
                'position' => $entry['position'],
            ], $people),
        ]);
    }
}
