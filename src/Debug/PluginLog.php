<?php

declare(strict_types=1);

namespace LegacitiForWp\Debug;

use LegacitiForWp\Database\ErrorLogRepository;

/**
 * Development / debug logger: writes to {@see ErrorLogRepository} (table removable later).
 */
final class PluginLog
{
    private static ?ErrorLogRepository $repository = null;

    public static function setRepository(ErrorLogRepository $repository): void
    {
        self::$repository = $repository;
    }

    private static function repo(): ErrorLogRepository
    {
        return self::$repository ??= new ErrorLogRepository();
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function debug(string $source, string $message, array $context = [], ?\Throwable $e = null): void
    {
        self::write('debug', $source, $message, $context, $e);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $source, string $message, array $context = [], ?\Throwable $e = null): void
    {
        self::write('info', $source, $message, $context, $e);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $source, string $message, array $context = [], ?\Throwable $e = null): void
    {
        self::write('warning', $source, $message, $context, $e);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $source, string $message, array $context = [], ?\Throwable $e = null): void
    {
        self::write('error', $source, $message, $context, $e);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function exception(string $source, string $message, \Throwable $e, array $context = []): void
    {
        self::write('error', $source, $message, $context, $e);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function write(string $level, string $source, string $message, array $context, ?\Throwable $e): void
    {
        try {
            $row = array_merge(self::requestContext(), [
                'level' => $level,
                'source' => $source,
                'message' => $message,
                'context' => $context,
            ]);

            if ($e !== null) {
                $row['exception_type'] = $e::class;
                if (($row['message'] ?? '') === '') {
                    $row['message'] = $e->getMessage() !== '' ? $e->getMessage() : $e::class;
                }
                $row['file'] = $e->getFile();
                $row['line'] = $e->getLine();
                $trace = $e->getTraceAsString();
                $row['stack'] = strlen($trace) > 60000 ? substr($trace, 0, 60000) . "\n…" : $trace;
                $row['context'] = array_merge($context, ['exception_message' => $e->getMessage()]);
            }

            self::repo()->insert($row);
        } catch (\Throwable $t) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Legaciti PluginLog] ' . $t->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function requestContext(): array
    {
        $userId = function_exists('get_current_user_id') ? get_current_user_id() : 0;

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if (strlen($uri) > 2000) {
            $uri = substr($uri, 0, 2000) . '…';
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '';

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $fwd = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] ?? '');
            if ($fwd !== '') {
                $ip = substr($fwd, 0, 45);
            }
        }

        return [
            'user_id' => $userId > 0 ? $userId : null,
            'request_uri' => $uri !== '' ? $uri : null,
            'request_method' => $method !== '' ? $method : null,
            'ip' => $ip !== '' ? $ip : null,
        ];
    }
}
