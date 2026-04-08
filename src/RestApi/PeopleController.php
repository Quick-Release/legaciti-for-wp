<?php

declare(strict_types=1);

namespace LegacitiForWp\RestApi;

use LegacitiForWp\Database\PersonRepository;
use LegacitiForWp\Database\PublicationRepository;
use LegacitiForWp\Database\RelationRepository;

final class PeopleController
{
    private const NAMESPACE = 'legaciti/v1';
    private const ROUTE = '/people';

    public function __construct(
        private readonly PersonRepository $personRepo,
        private readonly PublicationRepository $publicationRepo,
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

        register_rest_route(self::NAMESPACE, self::ROUTE . '/(?P<nickname>[a-zA-Z0-9_-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getItem'],
                'permission_callback' => '__return_true',
                'args' => [
                    'nickname' => [
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
            $people = $this->personRepo->searchActive($search, $page, $perPage);
        } else {
            $people = $this->personRepo->findAllActive($page, $perPage);
        }

        $total = $this->personRepo->countActive();

        $data = array_map(fn($person): array => $person->toArray(), $people);

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
        $nickname = $request->get_param('nickname');
        $person = $this->personRepo->findByNickname($nickname);

        if ($person === null) {
            return new \WP_Error(
                'legaciti_person_not_found',
                'Person not found.',
                ['status' => 404]
            );
        }

        $publications = $this->relationRepo->getPublicationsForPerson($person->id);

        return new \WP_REST_Response([
            'data' => $person->toArray(),
            'publications' => array_map(fn($pub): array => $pub->toArray(), $publications),
        ]);
    }
}
