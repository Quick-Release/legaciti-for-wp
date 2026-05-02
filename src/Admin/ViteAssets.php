<?php

declare(strict_types=1);

namespace LegacitiForWp\Admin;

final class ViteAssets
{
    /**
     * Registers Vite chunks (shared + entry) and enqueues the entry script handle.
     * Style chunks from the manifest are enqueued with derived handles.
     *
     * @return bool True when the manifest and entry exist and scripts were registered
     */
    public static function enqueueEntry(
        string $distPath,
        string $distUrl,
        string $scriptHandle,
        string $entryName
    ): bool {
        $manifest = self::loadManifest($distPath);
        if ($manifest === null) {
            return false;
        }

        $entryKey = self::findEntryKey($manifest, $entryName);
        if ($entryKey === null) {
            return false;
        }

        $ordered = self::orderedChunks($manifest, $entryKey);
        if ($ordered['js'] === []) {
            return false;
        }

        $prevHandle = null;
        $jsFiles = $ordered['js'];
        $lastIndex = count($jsFiles) - 1;

        foreach ($jsFiles as $index => $file) {
            $isLast = $index === $lastIndex;
            $handle = $isLast ? $scriptHandle : $scriptHandle . '-vite-' . $index;
            $deps = $prevHandle !== null ? [$prevHandle] : [];

            wp_register_script(
                $handle,
                $distUrl . $file,
                $deps,
                self::fileVersion($distPath . $file),
                true
            );
            $prevHandle = $handle;
        }

        wp_enqueue_script($scriptHandle);

        $cssIndex = 0;
        foreach ($ordered['css'] as $file) {
            wp_enqueue_style(
                $scriptHandle . '-css-' . $cssIndex,
                $distUrl . $file,
                [],
                self::fileVersion($distPath . $file)
            );
            ++$cssIndex;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadManifest(string $distPath): ?array
    {
        $candidates = [
            $distPath . 'manifest.json',
            $distPath . '.vite' . DIRECTORY_SEPARATOR . 'manifest.json',
        ];

        foreach ($candidates as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $json = file_get_contents($path);
            if ($json === false) {
                continue;
            }

            $data = json_decode($json, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private static function findEntryKey(array $manifest, string $entryName): ?string
    {
        foreach ($manifest as $key => $chunk) {
            if (! is_array($chunk)) {
                continue;
            }

            $isEntry = ! empty($chunk['isEntry']);
            $name = isset($chunk['name']) && is_string($chunk['name']) ? $chunk['name'] : '';

            if ($isEntry && $name === $entryName) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{js: list<string>, css: list<string>}
     */
    private static function orderedChunks(array $manifest, string $entryKey): array
    {
        $visited = [];
        $js = [];
        $css = [];

        self::walkChunk($manifest, $entryKey, $visited, $js, $css);

        return [
            'js' => $js,
            'css' => self::uniquePreserveOrder($css),
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, true> $visited
     * @param list<string> $js
     * @param list<string> $css
     */
    private static function walkChunk(array $manifest, string $key, array &$visited, array &$js, array &$css): void
    {
        if (isset($visited[$key])) {
            return;
        }

        $visited[$key] = true;

        $chunk = $manifest[$key] ?? null;
        if (! is_array($chunk)) {
            return;
        }

        foreach ($chunk['imports'] ?? [] as $importKey) {
            if (is_string($importKey)) {
                self::walkChunk($manifest, $importKey, $visited, $js, $css);
            }
        }

        if (! empty($chunk['file']) && is_string($chunk['file'])) {
            $js[] = $chunk['file'];
        }

        foreach ($chunk['css'] ?? [] as $c) {
            if (is_string($c)) {
                $css[] = $c;
            }
        }
    }

    /**
     * @param list<string> $items
     * @return list<string>
     */
    private static function uniquePreserveOrder(array $items): array
    {
        $seen = [];
        $out = [];

        foreach ($items as $item) {
            if (isset($seen[$item])) {
                continue;
            }

            $seen[$item] = true;
            $out[] = $item;
        }

        return $out;
    }

    private static function fileVersion(string $absolutePath): string
    {
        if (is_readable($absolutePath)) {
            $mtime = filemtime($absolutePath);

            return $mtime !== false ? (string) $mtime : '0';
        }

        return '0';
    }
}
