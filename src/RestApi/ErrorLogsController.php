<?php

declare(strict_types=1);

namespace LegacitiForWp\RestApi;

use LegacitiForWp\Database\ErrorLogRepository;

final class ErrorLogsController
{
    private const NAMESPACE = 'legaciti/v1';

    public function __construct(
        private readonly ErrorLogRepository $errorLogRepo,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/admin/error-logs', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getItems'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
                'args' => [
                    'page' => [
                        'default' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'default' => 50,
                        'sanitize_callback' => 'absint',
                    ],
                    'level' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'source' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'q' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteItems'],
                'permission_callback' => fn(): bool => current_user_can('manage_options'),
                'args' => [
                    'level' => [
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    public function getItems(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = min(200, max(1, (int) $request->get_param('per_page')));
        $level = (string) $request->get_param('level');
        $source = (string) $request->get_param('source');
        $q = (string) $request->get_param('q');

        $levelFilter = $level !== '' && in_array($level, ['error', 'warning', 'info', 'debug'], true)
            ? $level
            : null;

        $result = $this->errorLogRepo->findPage(
            $page,
            $perPage,
            $levelFilter,
            $source !== '' ? $source : null,
            $q
        );

        $total = $result['total'];

        return new \WP_REST_Response([
            'data' => $result['rows'],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ]);
    }

    public function deleteItems(\WP_REST_Request $request): \WP_REST_Response
    {
        $level = (string) $request->get_param('level');
        $levelFilter = $level !== '' && in_array($level, ['error', 'warning', 'info', 'debug'], true)
            ? $level
            : null;

        $deleted = $this->errorLogRepo->deleteAll($levelFilter);

        return new \WP_REST_Response([
            'deleted' => $deleted,
            'truncated' => $levelFilter === null,
        ]);
    }
}
