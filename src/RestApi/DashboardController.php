<?php

declare(strict_types=1);

namespace LegacitiForWp\RestApi;

use LegacitiForWp\Database\PersonRepository;
use LegacitiForWp\Database\PublicationRepository;

final class DashboardController
{
    private const NAMESPACE = 'legaciti/v1';

    public function __construct(
        private readonly PersonRepository $personRepo,
        private readonly PublicationRepository $publicationRepo,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/dashboard', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getDashboard'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
            ],
        ]);
    }

    public function getDashboard(): \WP_REST_Response
    {
        $settings = get_option('legaciti_settings', []);

        return new \WP_REST_Response([
            'total_people' => $this->personRepo->countActive(),
            'total_publications' => $this->publicationRepo->countActive(),
            'last_sync' => $settings['last_sync'] ?? null,
        ]);
    }
}
