<?php

declare(strict_types=1);

namespace LegacitiForWp\RestApi;

use LegacitiForWp\Database\PublicationRepository;
use LegacitiForWp\Database\PersonRepository;
use LegacitiForWp\Database\RelationRepository;

final class PublicationsController
{
    private const NAMESPACE = 'legaciti/v1';
    private const ROUTE = '/publications';

    public function __construct(
        private readonly PublicationRepository $publicationRepo,
        private readonly PersonRepository $personRepo,
        private readonly RelationRepository $relationRepo,
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
