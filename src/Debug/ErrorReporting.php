<?php

declare(strict_types=1);

namespace LegacitiForWp\Debug;

/**
 * Central hooks for capturing REST failures, PHP fatals in plugin code (shutdown), and bootstrap notices.
 */
final class ErrorReporting
{
    public function register(): void
    {
        add_filter('rest_pre_dispatch', [$this, 'onRestPreDispatch'], 5, 3);
        add_filter('rest_post_dispatch', [$this, 'onRestPostDispatch'], 10, 3);
        add_action('shutdown', [$this, 'onShutdown'], 999);
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public function onRestPreDispatch($result, \WP_REST_Server $server, \WP_REST_Request $request)
    {
        $route = $request->get_route();
        if (
            is_string($route)
            && strpos($route, '/legaciti/') !== false
            && strpos($route, '/admin/error-logs') === false
            && strpos($route, '/admin/people/connectivity') === false
        ) {
            PluginLog::debug('rest_in', 'Legaciti REST request', [
                'route' => $route,
                'method' => $request->get_method(),
            ]);
        }

        return $result;
    }

    /**
     * Log Legaciti REST responses that indicate failure (4xx/5xx or WP_Error).
     *
     * @param \WP_HTTP_Response|\WP_Error $result
     */
    public function onRestPostDispatch($result, \WP_REST_Server $server, \WP_REST_Request $request)
    {
        $route = $request->get_route();
        if (! is_string($route) || strpos($route, '/legaciti/') === false) {
            return $result;
        }

        if (strpos($route, '/admin/error-logs') !== false) {
            return $result;
        }

        if (strpos($route, '/admin/people/connectivity') !== false) {
            return $result;
        }

        if ($result instanceof \WP_Error) {
            PluginLog::warning(
                'rest',
                $result->get_error_message(),
                [
                    'code' => $result->get_error_code(),
                    'data' => $result->get_error_data(),
                    'route' => $route,
                    'method' => $request->get_method(),
                ]
            );

            return $result;
        }

        if ($result instanceof \WP_REST_Response) {
            $status = $result->get_status();
            if ($status >= 400) {
                $data = $result->get_data();
                PluginLog::warning(
                    'rest',
                    'REST response HTTP ' . $status,
                    [
                        'route' => $route,
                        'method' => $request->get_method(),
                        'status' => $status,
                        'body' => is_array($data) ? $this->truncatePayload($data) : $data,
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|string
     */
    private function truncatePayload(array $data): array|string
    {
        $encoded = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return '[unserializable]';
        }
        if (strlen($encoded) > 8000) {
            return substr($encoded, 0, 8000) . '…';
        }

        return $data;
    }

    public function onShutdown(): void
    {
        if (! function_exists('error_get_last')) {
            return;
        }

        $last = error_get_last();
        if ($last === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (! in_array($last['type'], $fatalTypes, true)) {
            return;
        }

        $file = $last['file'] ?? '';
        if (! is_string($file) || strpos($file, 'legaciti-for-wp') === false) {
            return;
        }

        PluginLog::error(
            'php_fatal',
            (string) ($last['message'] ?? 'Fatal error'),
            [
                'type' => $last['type'],
                'file' => $file,
                'line' => $last['line'] ?? null,
            ]
        );
    }

}
