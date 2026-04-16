#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/utils.php';
require_once __DIR__ . '/../inc/blocks.php';
require_once __DIR__ . '/../inc/presets.php';
require_once __DIR__ . '/../inc/storage.php';
require_once __DIR__ . '/../inc/render.php';

/*
Usage:
- The bridge reads one JSON object from STDIN and prints one JSON object to STDOUT.
- Run from the SiteKit project root.
- Commands without a payload: `blocks`, `presets`, `load_page`.
- Commands without a payload: `blocks`, `presets`, `load_page`, `list_pages`, `storage_status`.
- Commands with a payload: `normalize_payload`, `render_preview`, `export_json`,
  `export_skit`, `download_html`, `save_page`, `delete_page`.
-
- Exact examples:
- `php bin/sitekit_bridge.php blocks <<<'{"locale":"en"}'`
- `php bin/sitekit_bridge.php presets <<<'{"locale":"en"}'`
- `php bin/sitekit_bridge.php load_page <<<'{"locale":"en","slug":"home"}'`
- `php bin/sitekit_bridge.php list_pages <<<'{"locale":"en"}'`
- `php bin/sitekit_bridge.php storage_status <<<'{"locale":"en"}'`
- `php bin/sitekit_bridge.php normalize_payload <<'JSON'
{"locale":"en","primaryAddress":"123 Main Street\nSpringfield, IL 62704\nUnited States","payload":{"page":{"title":"Home","slug":"home","locale":"en","sharedContact":{"address":"123 Main Street\nSpringfield, IL 62704\nUnited States"},"blocks":[]}}}
JSON`
- `php bin/sitekit_bridge.php render_preview < payload.json`
- `php bin/sitekit_bridge.php export_json < payload.json`
- `php bin/sitekit_bridge.php export_skit < payload.json`
- `php bin/sitekit_bridge.php download_html < payload.json`
- `php bin/sitekit_bridge.php save_page < payload.json`
- `php bin/sitekit_bridge.php delete_page <<<'{"slug":"home"}'`
-
- Payload input can be either the raw SiteKit payload or `{"payload": {...}}`.
- To carry a primary address into SiteKit, pass `primaryAddress` or
  `primary_address` at the top level. The bridge copies it into
  `page.sharedContact.address` and every `site.pages[*].sharedContact.address`.
- For multi-page payloads, if only `page.sharedContact.address` is provided, the
  bridge also copies that value into the active SiteKit page before
  normalization/export/render/save.
*/

function bridge_fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function bridge_read_input(): array
{
    $raw = stream_get_contents(STDIN);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    app_assert_sitekit_import_size($raw, 'Bridge payload');

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        bridge_fail('Input must be a JSON object.');
    }

    return $decoded;
}

function bridge_payload_from_input(array $input): array
{
    // Most commands accept either {"payload": {...}} or the raw payload object.
    $payload = $input['payload'] ?? $input;
    if (!is_array($payload)) {
        bridge_fail('Payload must be a JSON object.');
    }

    return $payload;
}

function bridge_trim_multiline_string($value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    return trim(str_replace("\r", '', (string) $value));
}

function bridge_explicit_primary_address(array $input, array $payload): string
{
    $candidates = [
        $input['primaryAddress'] ?? null,
        $input['primary_address'] ?? null,
        $input['address'] ?? null,
        $payload['primaryAddress'] ?? null,
        $payload['primary_address'] ?? null,
        $payload['address'] ?? null,
        (is_array($payload['sharedContact'] ?? null) ? ($payload['sharedContact']['address'] ?? null) : null),
    ];

    foreach ($candidates as $candidate) {
        $normalized = bridge_trim_multiline_string($candidate);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return '';
}

function bridge_active_site_page_index(array $payload): ?int
{
    $pages = $payload['site']['pages'] ?? null;
    if (!is_array($pages) || $pages === []) {
        return null;
    }

    $activePageId = app_slugify((string) (($payload['site']['activePageId'] ?? $payload['site']['currentPageId'] ?? '')));
    if ($activePageId !== '') {
        foreach ($pages as $index => $page) {
            if (!is_array($page)) {
                continue;
            }
            if (app_slugify((string) ($page['id'] ?? '')) === $activePageId) {
                return (int) $index;
            }
        }
    }

    $pageSlug = app_slugify((string) (($payload['page']['slug'] ?? '')));
    if ($pageSlug !== '') {
        foreach ($pages as $index => $page) {
            if (!is_array($page)) {
                continue;
            }
            if (app_slugify((string) ($page['slug'] ?? '')) === $pageSlug) {
                return (int) $index;
            }
        }
    }

    foreach ($pages as $index => $page) {
        if (is_array($page)) {
            return (int) $index;
        }
    }

    return null;
}

function bridge_set_page_primary_address(array $page, string $primaryAddress): array
{
    if ($primaryAddress === '') {
        return $page;
    }

    if (!isset($page['sharedContact']) || !is_array($page['sharedContact'])) {
        $page['sharedContact'] = [];
    }
    $page['sharedContact']['address'] = $primaryAddress;

    return $page;
}

function bridge_sync_page_primary_address_to_active_site_page(array $payload): array
{
    $pageAddress = bridge_trim_multiline_string($payload['page']['sharedContact']['address'] ?? null);
    if ($pageAddress === '') {
        return $payload;
    }

    $activeIndex = bridge_active_site_page_index($payload);
    if ($activeIndex === null || !isset($payload['site']['pages'][$activeIndex]) || !is_array($payload['site']['pages'][$activeIndex])) {
        return $payload;
    }

    $payload['site']['pages'][$activeIndex] = bridge_set_page_primary_address($payload['site']['pages'][$activeIndex], $pageAddress);
    return $payload;
}

function bridge_apply_primary_address(array $payload, string $primaryAddress): array
{
    if ($primaryAddress === '') {
        return $payload;
    }

    if (isset($payload['page']) && is_array($payload['page'])) {
        $payload['page'] = bridge_set_page_primary_address($payload['page'], $primaryAddress);
    }

    if (isset($payload['site']['pages']) && is_array($payload['site']['pages'])) {
        foreach ($payload['site']['pages'] as $index => $page) {
            if (!is_array($page)) {
                continue;
            }
            $payload['site']['pages'][$index] = bridge_set_page_primary_address($page, $primaryAddress);
        }
    }

    return $payload;
}

function bridge_payload_for_command(array $input): array
{
    $payload = bridge_payload_from_input($input);
    $payload = bridge_sync_page_primary_address_to_active_site_page($payload);
    $primaryAddress = bridge_explicit_primary_address($input, $payload);
    return bridge_apply_primary_address($payload, $primaryAddress);
}

function bridge_set_locale(array $input): void
{
    // Locale is set once per bridge call so PHP preset/block/render helpers all
    // resolve labels and strings consistently.
    $locale = trim((string) ($input['locale'] ?? ''));
    if ($locale !== '') {
        app_set_locale($locale);
    }
}

function bridge_print(array $payload): never
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        bridge_fail('Failed to encode bridge response.');
    }

    fwrite(STDOUT, $json . PHP_EOL);
    exit(0);
}

$command = $argv[1] ?? '';
if ($command === '') {
    bridge_fail('Missing command.');
}

$input = bridge_read_input();
bridge_set_locale($input);

try {
    switch ($command) {
        case 'blocks':
            $registry = app_block_registry_for_client();
            $categories = [];
            foreach ($registry as $block) {
                $category = (string) ($block['category'] ?? 'General');
                if (!in_array($category, $categories, true)) {
                    $categories[] = $category;
                }
            }
            sort($categories);

            bridge_print([
                'ok' => true,
                'registry' => $registry,
                'categories' => $categories,
                'imageSources' => app_image_source_list(),
            ]);

        case 'presets':
            $locale = trim((string) ($input['locale'] ?? ''));
            $presets = app_page_presets_localized($locale !== '' ? $locale : null);
            $presetSetConfig = app_page_preset_sets_config_localized(app_page_presets(), $locale !== '' ? $locale : null);

            bridge_print([
                'ok' => true,
                'presets' => $presets,
                'presetSets' => $presetSetConfig['sets'],
                'siteTypes' => $presetSetConfig['sets'],
                'defaultSiteTypeId' => $presetSetConfig['defaultSiteTypeId'],
                'defaultPresetSetId' => $presetSetConfig['defaultSetId'],
            ]);

        case 'normalize_payload':
            $payload = app_import_sitekit_payload(bridge_payload_for_command($input));
            bridge_print(['ok' => true, 'payload' => $payload]);

        case 'render_preview':
            $payload = bridge_payload_for_command($input);
            bridge_print(['ok' => true] + app_render_preview($payload));

        case 'export_json':
            $payload = app_exportable_page_json(bridge_payload_for_command($input));
            bridge_print([
                'ok' => true,
                'filename' => app_slugify((string) (($payload['page']['slug'] ?? $payload['page']['title'] ?? 'site-export'))) . '.json',
                'payload' => $payload,
            ]);

        case 'export_skit':
            $payload = app_exportable_page_json(bridge_payload_for_command($input));
            $envelope = app_encode_skit_envelope($payload);
            bridge_print([
                'ok' => true,
                'filename' => app_slugify((string) (($payload['page']['slug'] ?? $payload['page']['title'] ?? 'site-export'))) . '.skit',
                'payload' => $payload,
                'envelope' => $envelope,
            ]);

        case 'download_html':
            $payload = app_import_sitekit_payload(bridge_payload_for_command($input));
            $sitePages = isset($payload['site']['pages']) && is_array($payload['site']['pages']) ? $payload['site']['pages'] : [];
            $html = count($sitePages) > 1
                ? app_render_wetted_site_document($payload)
                : app_render_wetted_document($payload);
            bridge_print([
                'ok' => true,
                'filename' => app_slugify((string) (($payload['page']['slug'] ?? $payload['page']['title'] ?? 'site-export'))) . '.html',
                'html' => $html,
            ]);

        case 'save_page':
            $payload = bridge_payload_for_command($input);
            $result = app_save_page($payload);
            bridge_print(['ok' => (bool) ($result['ok'] ?? false)] + $result);

        case 'delete_page':
            $slug = app_slugify((string) ($input['slug'] ?? $input['projectId'] ?? ''));
            if ($slug === '') {
                bridge_fail('Missing slug.');
            }
            $deleted = app_delete_page($slug);
            if (!$deleted) {
                bridge_fail('Page not found.', 2);
            }
            bridge_print([
                'ok' => true,
                'slug' => $slug,
                'storage' => app_storage_adapter_summary(),
            ]);

        case 'load_page':
            $slug = app_slugify((string) ($input['slug'] ?? ''));
            if ($slug === '') {
                bridge_fail('Missing slug.');
            }
            $payload = app_load_page($slug);
            if ($payload === null) {
                bridge_fail('Page not found.', 2);
            }
            bridge_print(['ok' => true, 'payload' => $payload]);

        case 'list_pages':
            bridge_print([
                'ok' => true,
                'storage' => app_storage_adapter_summary(),
                'projects' => app_list_pages(),
            ]);

        case 'storage_status':
            $projectId = trim((string) ($input['projectId'] ?? $input['slug'] ?? ''));
            bridge_print([
                'ok' => true,
                'storage' => app_storage_adapter_summary(),
                'status' => app_storage_sync_status($projectId !== '' ? $projectId : null),
            ]);

        default:
            bridge_fail('Unknown command: ' . $command);
    }
} catch (Throwable $e) {
    bridge_print([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}
