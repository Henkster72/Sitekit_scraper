<?php
declare(strict_types=1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/blocks.php';

function app_page_presets_path(): string
{
    return __DIR__ . '/../data/page_presets.json';
}

function app_default_preset_animation(string $type): string
{
    return $type === 'header' ? 'none' : 'fade-left';
}

function app_default_preset_truststrip_block(): array
{
    $block = app_create_block('truststrip', 5);
    if (!is_array($block)) {
        return [
            'type' => 'truststrip',
            'variant' => 5,
            'hidden' => false,
            'widthPercent' => 75,
            'animation' => 'fade-left',
            'data' => [],
        ];
    }

    $normalized = [
        'type' => 'truststrip',
        'variant' => (int) ($block['variant'] ?? 5),
        'hidden' => (bool) ($block['hidden'] ?? false),
        'widthPercent' => (int) ($block['widthPercent'] ?? 75),
        'data' => is_array($block['data'] ?? null) ? $block['data'] : [],
        'animation' => 'fade-left',
    ];
    foreach (['backgroundColor', 'backgroundOpacity', 'cardBackgroundOpacity', 'foregroundOpacity', 'verticalPaddingScale', 'fontScale', 'cardColor', 'cardBorderWidth', 'cardBorderColor', 'cardBorderStyle'] as $key) {
        if (array_key_exists($key, $block)) {
            $normalized[$key] = $block[$key];
        }
    }

    return $normalized;
}

function app_finalize_page_preset_blocks(array $blocks): array
{
    $normalized = array_values(array_filter($blocks, 'is_array'));
    if ($normalized === []) {
        return [];
    }

    $featuresIndex = -1;
    $truststripIndex = -1;
    foreach ($normalized as $index => $block) {
        $type = (string) ($block['type'] ?? '');
        if ($featuresIndex < 0 && $type === 'features') {
            $featuresIndex = $index;
        }
        if ($truststripIndex < 0 && $type === 'truststrip') {
            $truststripIndex = $index;
        }
    }

    if ($featuresIndex >= 0) {
        $truststripBlock = null;
        if ($truststripIndex >= 0) {
            $truststripBlock = $normalized[$truststripIndex];
            array_splice($normalized, $truststripIndex, 1);
            if ($truststripIndex < $featuresIndex) {
                $featuresIndex -= 1;
            }
        } else {
            $truststripBlock = app_default_preset_truststrip_block();
        }

        array_splice($normalized, $featuresIndex + 1, 0, [$truststripBlock]);
    }

    $animationStartIndex = null;
    foreach ($normalized as $index => $block) {
        $type = (string) ($block['type'] ?? '');
        if (app_default_preset_animation($type) === 'none') {
            continue;
        }
        $animationStartIndex = $index;
        break;
    }

    if ($animationStartIndex !== null) {
        foreach ($normalized as $index => $block) {
            if ($index < $animationStartIndex) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            $defaultAnimation = app_default_preset_animation($type);
            if ($defaultAnimation === 'none') {
                continue;
            }
            $animation = app_normalize_block_animation($block['animation'] ?? 'none');
            if ($animation === 'none') {
                $normalized[$index]['animation'] = $defaultAnimation;
            }
        }
    }

    return array_values($normalized);
}

function app_localize_preset_blocks(array $blocks, ?string $locale = null): array
{
    $localized = [];
    foreach ($blocks as $index => $block) {
        if (!is_array($block)) {
            $localized[$index] = $block;
            continue;
        }

        $next = $block;
        if (isset($block['data']) && is_array($block['data'])) {
            $next['data'] = app_localize_data($block['data'], $locale);
        }
        $localized[$index] = $next;
    }

    return $localized;
}

function app_localize_page_snapshot(array $page, ?string $locale = null): array
{
    $localized = $page;
    foreach (['title', 'pageTitle', 'menuLabel', 'label'] as $key) {
        if (isset($localized[$key]) && is_string($localized[$key])) {
            $localized[$key] = app_tr($localized[$key], [], $locale);
        }
    }
    if (isset($localized['blocks']) && is_array($localized['blocks'])) {
        $localized['blocks'] = app_localize_preset_blocks($localized['blocks'], $locale);
    }

    return $localized;
}

function app_localize_page_preset(array $preset, ?string $locale = null): array
{
    $localized = $preset;
    foreach (['label', 'group', 'pageTitle', 'description'] as $key) {
        if (isset($localized[$key]) && is_string($localized[$key])) {
            $localized[$key] = app_tr($localized[$key], [], $locale);
        }
    }
    if (isset($localized['blocks']) && is_array($localized['blocks'])) {
        $localized['blocks'] = app_localize_preset_blocks($localized['blocks'], $locale);
    }

    return $localized;
}

function app_localize_page_preset_set(array $set, ?string $locale = null): array
{
    $localized = $set;
    foreach (['label', 'description'] as $key) {
        if (isset($localized[$key]) && is_string($localized[$key])) {
            $localized[$key] = app_tr($localized[$key], [], $locale);
        }
    }

    if (isset($localized['pages']) && is_array($localized['pages'])) {
        $pages = [];
        foreach ($localized['pages'] as $index => $page) {
            if (!is_array($page)) {
                $pages[$index] = $page;
                continue;
            }

            $nextPage = $page;
            foreach (['label', 'pageTitle', 'title'] as $key) {
                if (isset($nextPage[$key]) && is_string($nextPage[$key])) {
                    $nextPage[$key] = app_tr($nextPage[$key], [], $locale);
                }
            }
            if (isset($nextPage['page']) && is_array($nextPage['page'])) {
                $nextPage['page'] = app_localize_page_snapshot($nextPage['page'], $locale);
            }
            $pages[$index] = $nextPage;
        }
        $localized['pages'] = array_values($pages);
    }

    return $localized;
}

function app_page_presets(): array
{
    $payload = app_read_json_file(app_page_presets_path(), []);
    $rawPresets = [];

    if (isset($payload['presets']) && is_array($payload['presets'])) {
        $rawPresets = $payload['presets'];
    } elseif (array_is_list($payload)) {
        $rawPresets = $payload;
    }

    $normalized = [];
    $seen = [];

    foreach ($rawPresets as $index => $preset) {
        if (!is_array($preset)) {
            continue;
        }

        $label = trim((string) ($preset['label'] ?? $preset['name'] ?? $preset['title'] ?? ('Page Preset ' . ($index + 1))));
        if ($label === '') {
            continue;
        }

        $id = app_slugify((string) ($preset['id'] ?? $label));
        if ($id === '') {
            $id = 'page-preset-' . ($index + 1);
        }
        if (isset($seen[$id])) {
            $id .= '-' . ($index + 1);
        }
        $seen[$id] = true;

        $group = trim((string) ($preset['group'] ?? 'General'));
        if ($group === '') {
            $group = 'General';
        }

        $pageTitle = trim((string) ($preset['pageTitle'] ?? $preset['title'] ?? $label));
        if ($pageTitle === '') {
            $pageTitle = $label;
        }

        $slug = app_slugify((string) ($preset['slug'] ?? $id));
        $description = trim((string) ($preset['description'] ?? ''));

        $blocks = [];
        $rawBlocks = is_array($preset['blocks'] ?? null) ? $preset['blocks'] : [];
        foreach ($rawBlocks as $rawBlock) {
            if (!is_array($rawBlock)) {
                continue;
            }

            $type = (string) ($rawBlock['type'] ?? '');
            $definition = app_block_definition($type);
            if ($definition === null) {
                continue;
            }

            $variants = $definition['variants'] ?? [1];
            $variant = (int) ($rawBlock['variant'] ?? $variants[0]);
            if (!in_array($variant, $variants, true)) {
                $variant = (int) $variants[0];
            }

            $defaultWidthPercent = app_default_block_width_percent($type, $variant);
            $widthPercent = app_normalize_block_width_percent(
                $rawBlock['widthPercent'] ?? (((bool) ($rawBlock['fullWidth'] ?? false)) ? 100 : $defaultWidthPercent),
                $defaultWidthPercent
            );

            $block = [
                'type' => $type,
                'variant' => $variant,
                'hidden' => (bool) ($rawBlock['hidden'] ?? false),
                'widthPercent' => $widthPercent,
                'data' => is_array($rawBlock['data'] ?? null) ? $rawBlock['data'] : [],
            ];
            $animation = app_normalize_block_animation($rawBlock['animation'] ?? 'none');
            if ($animation !== 'none') {
                $block['animation'] = $animation;
            }
            $backgroundColor = app_normalize_block_background_color((string) ($rawBlock['backgroundColor'] ?? ''));
            if ($backgroundColor !== '') {
                $block['backgroundColor'] = $backgroundColor;
            }
            $cardColor = app_normalize_block_card_color((string) ($rawBlock['cardColor'] ?? ''));
            if ($cardColor !== '') {
                $block['cardColor'] = $cardColor;
            }
            if (array_key_exists('cardBorderWidth', $rawBlock)) {
                $block['cardBorderWidth'] = app_normalize_block_card_border_width($rawBlock['cardBorderWidth']);
            }
            $cardBorderColor = app_normalize_block_card_border_color((string) ($rawBlock['cardBorderColor'] ?? ''));
            if ($cardBorderColor !== '') {
                $block['cardBorderColor'] = $cardBorderColor;
            }
            $cardBorderStyle = app_normalize_block_card_border_style($rawBlock['cardBorderStyle'] ?? '');
            if ($cardBorderStyle !== '') {
                $block['cardBorderStyle'] = $cardBorderStyle;
            }
            if (array_key_exists('backgroundOpacity', $rawBlock)) {
                $block['backgroundOpacity'] = max(0, min(100, (float) $rawBlock['backgroundOpacity']));
            }
            if (array_key_exists('cardBackgroundOpacity', $rawBlock)) {
                $block['cardBackgroundOpacity'] = max(0, min(100, (float) $rawBlock['cardBackgroundOpacity']));
            }
            if (array_key_exists('foregroundOpacity', $rawBlock)) {
                $block['foregroundOpacity'] = max(0, min(100, (float) $rawBlock['foregroundOpacity']));
            }
            if (array_key_exists('fontScale', $rawBlock)) {
                $block['fontScale'] = max(25, min(300, (float) $rawBlock['fontScale']));
            }
            if (array_key_exists('verticalPaddingScale', $rawBlock)) {
                $block['verticalPaddingScale'] = max(0, min(300, (float) $rawBlock['verticalPaddingScale']));
            }

            $blocks[] = $block;
        }

        $blocks = app_finalize_page_preset_blocks($blocks);
        if ($blocks === []) {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'label' => $label,
            'group' => $group,
            'pageTitle' => $pageTitle,
            'slug' => $slug,
            'description' => $description,
            'blocks' => $blocks,
        ];
    }

    return $normalized;
}

function app_page_presets_localized(?string $locale = null): array
{
    return array_map(
        static fn (array $preset): array => app_localize_page_preset($preset, $locale),
        app_page_presets()
    );
}

function app_page_preset_map(): array
{
    static $presetMap = null;
    if ($presetMap !== null) {
        return $presetMap;
    }

    $presetMap = [];
    foreach (app_page_presets() as $preset) {
        if (!is_array($preset)) {
            continue;
        }
        $presetId = app_slugify((string) ($preset['id'] ?? ''));
        if ($presetId === '') {
            continue;
        }
        $presetMap[$presetId] = $preset;
    }

    return $presetMap;
}

function app_page_preset_by_id(string $presetId): ?array
{
    $safeId = app_slugify($presetId);
    if ($safeId === '') {
        return null;
    }

    $presetMap = app_page_preset_map();
    return $presetMap[$safeId] ?? null;
}

if (!function_exists('app_blocks_match_baseline_shape')) {
    function app_blocks_match_baseline_shape($rawBlocks, $baselineBlocks): bool
    {
        if (!is_array($rawBlocks) || !is_array($baselineBlocks) || count($rawBlocks) !== count($baselineBlocks)) {
            return false;
        }

        foreach ($rawBlocks as $index => $rawBlock) {
            if (!is_array($rawBlock)) {
                return false;
            }
            $baseline = $baselineBlocks[$index] ?? null;
            if (!is_array($baseline)) {
                return false;
            }

            $rawType = (string) ($rawBlock['type'] ?? '');
            $baselineType = (string) ($baseline['type'] ?? '');
            if ($rawType !== $baselineType) {
                return false;
            }

            $rawVariant = (int) ($rawBlock['variant'] ?? ($baseline['variant'] ?? 1));
            $baselineVariant = (int) ($baseline['variant'] ?? 1);
            if ($rawVariant !== $baselineVariant) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('app_merge_structured_value_with_baseline')) {
    function app_merge_structured_value_with_baseline($baseline, $override)
    {
        if (is_array($override)) {
            if ($override === [] && is_array($baseline) && !array_is_list($baseline)) {
                return $baseline;
            }
            if (array_is_list($override)) {
                $baseArray = is_array($baseline) && array_is_list($baseline) ? $baseline : [];
                $next = [];
                foreach ($override as $index => $entry) {
                    $next[$index] = app_merge_structured_value_with_baseline($baseArray[$index] ?? null, $entry);
                }
                return $next;
            }

            $baseObject = is_array($baseline) && !array_is_list($baseline) ? $baseline : [];
            $next = $baseObject;
            foreach ($override as $key => $value) {
                $next[$key] = app_merge_structured_value_with_baseline($baseObject[$key] ?? null, $value);
            }
            return $next;
        }

        return $override;
    }
}

if (!function_exists('app_merge_blocks_with_baseline')) {
    function app_merge_blocks_with_baseline(array $baselineBlocks, $rawBlocks): array
    {
        if (!app_blocks_match_baseline_shape($rawBlocks, $baselineBlocks)) {
            if (!is_array($rawBlocks)) {
                return [];
            }
            return array_values(array_filter($rawBlocks, 'is_array'));
        }

        $merged = [];
        foreach ($rawBlocks as $index => $rawBlock) {
            $baseline = is_array($baselineBlocks[$index] ?? null) ? $baselineBlocks[$index] : [];
            $override = is_array($rawBlock) ? $rawBlock : [];
            $next = $baseline;
            foreach (['uid', 'type', 'variant', 'hidden', 'animation', 'widthPercent', 'backgroundColor', 'backgroundOpacity', 'cardBackgroundOpacity', 'foregroundOpacity', 'verticalPaddingScale', 'fontScale', 'cardColor', 'cardBorderWidth', 'cardBorderColor', 'cardBorderStyle'] as $key) {
                if (array_key_exists($key, $override)) {
                    $next[$key] = $override[$key];
                }
            }
            if (isset($override['data']) && is_array($override['data'])) {
                $next['data'] = app_merge_structured_value_with_baseline($baseline['data'] ?? [], $override['data']);
            }
            $merged[] = $next;
        }

        return $merged;
    }
}

function app_build_page_from_preset(string $presetId, array $theme, array $pageSpec = []): ?array
{
    $preset = app_page_preset_by_id($presetId);
    if ($preset === null) {
        return null;
    }

    $spec = is_array($pageSpec) ? $pageSpec : [];
    $specPage = isset($spec['page']) && is_array($spec['page']) ? $spec['page'] : [];
    $pageLocale = app_normalize_locale((string) ($spec['locale'] ?? ($specPage['locale'] ?? app_current_locale())));
    $localizedPreset = app_localize_page_preset($preset, $pageLocale);
    $blocks = [];
    foreach (($localizedPreset['blocks'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $type = (string) ($entry['type'] ?? '');
        $variant = isset($entry['variant']) ? (int) $entry['variant'] : app_default_block_variant($type);
        $block = app_create_block($type, $variant, $theme);
        if ($block === null) {
            continue;
        }

        if (isset($entry['data']) && is_array($entry['data'])) {
            $block['data'] = app_merge_block_data($block['data'] ?? [], $entry['data']);
        }
        if (array_key_exists('hidden', $entry)) {
            $block['hidden'] = (bool) $entry['hidden'];
        }
        if (array_key_exists('widthPercent', $entry) || array_key_exists('fullWidth', $entry)) {
            $defaultWidthPercent = app_default_block_width_percent($block['type'], (int) ($block['variant'] ?? $variant));
            $block['widthPercent'] = app_normalize_block_width_percent(
                $entry['widthPercent'] ?? (((bool) ($entry['fullWidth'] ?? false)) ? 100 : $defaultWidthPercent),
                $defaultWidthPercent
            );
        }
        if (array_key_exists('backgroundOpacity', $entry)) {
            $block['backgroundOpacity'] = max(0, min(100, (float) $entry['backgroundOpacity']));
        }
        $legacyCardOpacity = array_key_exists('cardBackgroundOpacity', $entry)
            ? $entry['cardBackgroundOpacity']
            : (!array_key_exists('backgroundColor', $entry)
                ? ($entry['backgroundOpacity'] ?? null)
                : null);
        if ($legacyCardOpacity !== null) {
            $block['cardBackgroundOpacity'] = max(0, min(100, (float) $legacyCardOpacity));
        }
        if (array_key_exists('foregroundOpacity', $entry)) {
            $block['foregroundOpacity'] = max(0, min(100, (float) $entry['foregroundOpacity']));
        }
        if (array_key_exists('verticalPaddingScale', $entry)) {
            $block['verticalPaddingScale'] = max(0, min(300, (float) $entry['verticalPaddingScale']));
        }
        if (array_key_exists('fontScale', $entry)) {
            $block['fontScale'] = max(25, min(300, (float) $entry['fontScale']));
        }
        $animation = app_normalize_block_animation($entry['animation'] ?? 'none');
        if ($animation !== 'none') {
            $block['animation'] = $animation;
        }
        $backgroundColor = app_normalize_block_background_color((string) ($entry['backgroundColor'] ?? ''));
        if ($backgroundColor !== '') {
            $block['backgroundColor'] = $backgroundColor;
        }
        $cardColor = app_normalize_block_card_color((string) ($entry['cardColor'] ?? ''));
        if ($cardColor !== '') {
            $block['cardColor'] = $cardColor;
        }
        if (array_key_exists('cardBorderWidth', $entry)) {
            $block['cardBorderWidth'] = app_normalize_block_card_border_width($entry['cardBorderWidth'], $theme);
        }
        $cardBorderColor = app_normalize_block_card_border_color((string) ($entry['cardBorderColor'] ?? ''));
        if ($cardBorderColor !== '') {
            $block['cardBorderColor'] = $cardBorderColor;
        }
        $cardBorderStyle = app_normalize_block_card_border_style($entry['cardBorderStyle'] ?? '');
        if ($cardBorderStyle !== '') {
            $block['cardBorderStyle'] = $cardBorderStyle;
        }
        $block['data'] = app_fill_image_defaults(is_array($block['data'] ?? null) ? $block['data'] : [], $theme);
        $blocks[] = $block;
    }

    if ($blocks === []) {
        return null;
    }

    $fallbackTitle = trim((string) ($localizedPreset['pageTitle'] ?? $localizedPreset['label'] ?? app_tr('Untitled Page')));
    if ($fallbackTitle === '') {
        $fallbackTitle = app_tr('Untitled Page');
    }
    $fallbackSlug = app_slugify((string) ($preset['slug'] ?? $fallbackTitle));
    if ($fallbackSlug === '') {
        $fallbackSlug = 'untitled-page';
    }
    $fallbackId = app_slugify((string) ($preset['id'] ?? $fallbackSlug));
    if ($fallbackId === '') {
        $fallbackId = 'page';
    }

    $page = [
        'id' => app_slugify((string) ($spec['id'] ?? $spec['pageId'] ?? $fallbackId)) ?: $fallbackId,
        'title' => trim((string) ($spec['pageTitle'] ?? $spec['title'] ?? $fallbackTitle)) ?: $fallbackTitle,
        'menuLabel' => trim((string) ($spec['menuLabel'] ?? $spec['label'] ?? $localizedPreset['label'] ?? $fallbackTitle)) ?: $fallbackTitle,
        'slug' => app_slugify((string) ($spec['slug'] ?? $fallbackSlug)) ?: $fallbackSlug,
        'locale' => $pageLocale,
        'showInHeaderNav' => app_normalize_site_page_nav_visibility($spec['showInHeaderNav'] ?? null, true),
        'showInFooterNav' => app_normalize_site_page_nav_visibility($spec['showInFooterNav'] ?? null, true),
        'presetId' => (string) ($preset['id'] ?? $presetId),
        'canvas' => app_normalize_canvas_settings($spec['canvas'] ?? null),
        'blocks' => $blocks,
    ];

    if (isset($spec['page']) && is_array($spec['page'])) {
        $pageSnapshot = $spec['page'];
        $page['title'] = trim((string) ($pageSnapshot['title'] ?? $pageSnapshot['pageTitle'] ?? $page['title'] ?? $fallbackTitle)) ?: $fallbackTitle;
        $page['menuLabel'] = trim((string) ($pageSnapshot['menuLabel'] ?? $pageSnapshot['label'] ?? $page['menuLabel'] ?? $page['title'] ?? $fallbackTitle)) ?: $page['title'];
        $page['slug'] = app_slugify((string) ($pageSnapshot['slug'] ?? $page['slug'] ?? $page['title'] ?? $fallbackSlug)) ?: $fallbackSlug;
        $page['locale'] = app_normalize_locale((string) ($pageSnapshot['locale'] ?? $page['locale'] ?? $pageLocale));
        $page['showInHeaderNav'] = app_normalize_site_page_nav_visibility($pageSnapshot['showInHeaderNav'] ?? $page['showInHeaderNav'], true);
        $page['showInFooterNav'] = app_normalize_site_page_nav_visibility($pageSnapshot['showInFooterNav'] ?? $page['showInFooterNav'], true);
        $page['canvas'] = app_normalize_canvas_settings($pageSnapshot['canvas'] ?? $page['canvas']);
        if (isset($pageSnapshot['blocks']) && is_array($pageSnapshot['blocks'])) {
            $pageSnapshotBlocks = $pageSnapshot['blocks'];
            if (app_blocks_match_baseline_shape($pageSnapshotBlocks, $page['blocks'])) {
                $pageSnapshotBlocks = app_merge_blocks_with_baseline($page['blocks'], $pageSnapshotBlocks);
            }
            $normalizedBlocks = app_normalize_blocks($pageSnapshotBlocks, $theme);
            if ($normalizedBlocks !== []) {
                $page['blocks'] = $normalizedBlocks;
            }
        }
    }

    if ($page['menuLabel'] === '') {
        $page['menuLabel'] = $page['title'];
    }
    $page['locale'] = app_normalize_locale((string) ($page['locale'] ?? $pageLocale));

    return $page;
}

function app_page_preset_sets_path(): string
{
    return __DIR__ . '/../data/page_preset_sets.json';
}

function app_site_type_theme_defaults($raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }

    $themeMetaRaw = isset($raw['theme']) && is_array($raw['theme']) ? $raw['theme'] : [];
    $themeMeta = [];
    if ($themeMetaRaw !== []) {
        if (array_key_exists('id', $themeMetaRaw)) {
            $themeMeta['id'] = (string) ($themeMetaRaw['id'] ?? '');
        }
        if (array_key_exists('name', $themeMetaRaw)) {
            $themeMeta['name'] = (string) ($themeMetaRaw['name'] ?? '');
        }
        if (array_key_exists('picsumId', $themeMetaRaw)) {
            $themeMeta['picsumId'] = (string) ($themeMetaRaw['picsumId'] ?? '');
        }
        if (isset($themeMetaRaw['colors']) && is_array($themeMetaRaw['colors'])) {
            $themeMeta['colors'] = $themeMetaRaw['colors'];
        }
        if (isset($themeMetaRaw['effects']) && is_array($themeMetaRaw['effects'])) {
            $themeMeta['effects'] = $themeMetaRaw['effects'];
        }
    }

    $result = [];
    if ($themeMeta !== []) {
        $result['theme'] = $themeMeta;
    }
    foreach (['palette', 'gradient', 'typography', 'elements'] as $key) {
        if (isset($raw[$key]) && is_array($raw[$key])) {
            $result[$key] = $raw[$key];
        }
    }
    if (isset($raw['images']) && is_array($raw['images'])) {
        $images = [];
        foreach ($raw['images'] as $image) {
            if (!is_array($image)) {
                continue;
            }
            $entry = [];
            if (array_key_exists('id', $image)) {
                $entry['id'] = (string) ($image['id'] ?? '');
            }
            if (array_key_exists('source', $image)) {
                $entry['source'] = strtolower((string) ($image['source'] ?? ''));
            }
            if (array_key_exists('url', $image)) {
                $safeUrl = app_safe_url((string) ($image['url'] ?? ''));
                if ($safeUrl !== '') {
                    $entry['url'] = $safeUrl;
                }
            }
            if ($entry !== []) {
                $images[] = $entry;
            }
        }
        if ($images !== []) {
            $result['images'] = $images;
        }
    }

    if (!isset($result['theme']) || !isset($result['palette']) || !isset($result['typography'])) {
        return null;
    }

    return $result;
}

function app_page_preset_sets_config(array $presets): array
{
    $payload = app_read_json_file(app_page_preset_sets_path(), []);
    $rawSets = [];

    if (isset($payload['siteTypes']) && is_array($payload['siteTypes'])) {
        $rawSets = $payload['siteTypes'];
    } elseif (isset($payload['sets']) && is_array($payload['sets'])) {
        $rawSets = $payload['sets'];
    } elseif (array_is_list($payload)) {
        $rawSets = $payload;
    }

    $presetMap = [];
    $presetIds = [];
    $presetGroups = [];
    foreach ($presets as $preset) {
        if (!is_array($preset)) {
            continue;
        }
        $presetId = trim((string) ($preset['id'] ?? ''));
        if ($presetId === '') {
            continue;
        }
        $presetMap[$presetId] = $preset;
        $presetIds[] = $presetId;
        $presetGroups[$presetId] = trim((string) ($preset['group'] ?? 'General'));
    }

    $normalizedSets = [];
    $seenSetIds = [];
    foreach ($rawSets as $index => $set) {
        if (!is_array($set)) {
            continue;
        }

        $label = trim((string) ($set['label'] ?? $set['name'] ?? ('Page Preset Set ' . ($index + 1))));
        if ($label === '') {
            continue;
        }

        $id = app_slugify((string) ($set['id'] ?? $label));
        if ($id === '') {
            $id = 'page-preset-set-' . ($index + 1);
        }
        if (isset($seenSetIds[$id])) {
            $id .= '-' . ($index + 1);
        }
        $seenSetIds[$id] = true;
        $setCanvas = null;
        if (isset($set['canvas']) && is_array($set['canvas'])) {
            $setCanvas = app_normalize_canvas_settings($set['canvas']);
        } elseif (isset($set['canvasControls']) && is_array($set['canvasControls'])) {
            $setCanvas = app_normalize_canvas_settings($set['canvasControls']);
        }
        $setThemeDefaults = null;
        if (isset($set['themeDefaults']) && is_array($set['themeDefaults'])) {
            $setThemeDefaults = app_site_type_theme_defaults($set['themeDefaults']);
        } elseif (isset($set['theme']) && is_array($set['theme'])) {
            $setThemeDefaults = app_site_type_theme_defaults($set['theme']);
        }

        $pages = [];
        $seenPageIds = [];
        $rawPages = $set['pages'] ?? [];
        if (is_array($rawPages)) {
            foreach ($rawPages as $pageIndex => $pageSpec) {
                $spec = is_array($pageSpec) ? $pageSpec : ['presetId' => $pageSpec];
                $presetId = app_slugify((string) ($spec['presetId'] ?? $spec['preset'] ?? $spec['id'] ?? ''));
                if ($presetId === '' || !isset($presetMap[$presetId])) {
                    continue;
                }

                $preset = $presetMap[$presetId];
                $pageLabel = trim((string) ($spec['label'] ?? $spec['menuLabel'] ?? $preset['label'] ?? $preset['pageTitle'] ?? $presetId));
                if ($pageLabel === '') {
                    $pageLabel = 'Page ' . ($pageIndex + 1);
                }
                $pageTitle = trim((string) ($spec['pageTitle'] ?? $spec['title'] ?? $preset['pageTitle'] ?? $pageLabel));
                if ($pageTitle === '') {
                    $pageTitle = $pageLabel;
                }
                $slug = app_slugify((string) ($spec['slug'] ?? $preset['slug'] ?? $pageTitle));
                if ($slug === '') {
                    $slug = $presetId . '-' . ($pageIndex + 1);
                }
                $pageId = app_slugify((string) ($spec['pageId'] ?? $spec['id'] ?? $slug));
                if ($pageId === '') {
                    $pageId = 'page-' . ($pageIndex + 1);
                }
                if (isset($seenPageIds[$pageId])) {
                    $pageId .= '-' . ($pageIndex + 1);
                }
                $seenPageIds[$pageId] = true;

                $page = [
                    'id' => $pageId,
                    'label' => $pageLabel,
                    'pageTitle' => $pageTitle,
                    'slug' => $slug,
                    'showInHeaderNav' => app_normalize_site_page_nav_visibility($spec['showInHeaderNav'] ?? null, true),
                    'showInFooterNav' => app_normalize_site_page_nav_visibility($spec['showInFooterNav'] ?? null, true),
                    'presetId' => $presetId,
                ];
                if (isset($spec['canvas']) && is_array($spec['canvas'])) {
                    $page['canvas'] = app_normalize_canvas_settings($spec['canvas']);
                } elseif (isset($spec['canvasControls']) && is_array($spec['canvasControls'])) {
                    $page['canvas'] = app_normalize_canvas_settings($spec['canvasControls']);
                } elseif (is_array($setCanvas)) {
                    $page['canvas'] = $setCanvas;
                }
                if (isset($spec['page']) && is_array($spec['page'])) {
                    $page['page'] = $spec['page'];
                    if (is_array($setCanvas) && (!isset($page['page']['canvas']) || !is_array($page['page']['canvas']))) {
                        $page['page']['canvas'] = $setCanvas;
                    }
                }
                $pages[] = $page;
            }
        }

        if ($pages === []) {
            $requestedPresetIds = [];
            $rawPresetIds = $set['presetIds'] ?? $set['presets'] ?? [];
            if (is_array($rawPresetIds)) {
                foreach ($rawPresetIds as $presetId) {
                    $safeId = app_slugify((string) $presetId);
                    if ($safeId !== '') {
                        $requestedPresetIds[] = $safeId;
                    }
                }
            }

            $groupFilter = [];
            $rawGroups = $set['groups'] ?? [];
            if (is_array($rawGroups)) {
                foreach ($rawGroups as $group) {
                    $name = trim((string) $group);
                    if ($name !== '') {
                        $groupFilter[] = $name;
                    }
                }
            }

            if ($requestedPresetIds === [] && $groupFilter !== []) {
                foreach ($presetIds as $presetId) {
                    $presetGroup = $presetGroups[$presetId] ?? '';
                    if (in_array($presetGroup, $groupFilter, true)) {
                        $requestedPresetIds[] = $presetId;
                    }
                }
            }

            if ($requestedPresetIds === []) {
                $requestedPresetIds = $presetIds;
            }

            foreach ($requestedPresetIds as $presetIndex => $presetId) {
                if (!isset($presetMap[$presetId])) {
                    continue;
                }
                $preset = $presetMap[$presetId];
                $pageLabel = trim((string) ($preset['label'] ?? $preset['pageTitle'] ?? $presetId));
                if ($pageLabel === '') {
                    $pageLabel = 'Page ' . ($presetIndex + 1);
                }
                $pageTitle = trim((string) ($preset['pageTitle'] ?? $pageLabel));
                if ($pageTitle === '') {
                    $pageTitle = $pageLabel;
                }
                $slug = app_slugify((string) ($preset['slug'] ?? $pageTitle));
                if ($slug === '') {
                    $slug = $presetId;
                }
                $pageId = app_slugify((string) ($preset['id'] ?? $slug));
                if ($pageId === '') {
                    $pageId = 'page-' . ($presetIndex + 1);
                }
                if (isset($seenPageIds[$pageId])) {
                    continue;
                }
                $seenPageIds[$pageId] = true;

                $pages[] = [
                    'id' => $pageId,
                    'label' => $pageLabel,
                    'pageTitle' => $pageTitle,
                    'slug' => $slug,
                    'showInHeaderNav' => true,
                    'showInFooterNav' => true,
                    'presetId' => $presetId,
                ];
                if (is_array($setCanvas)) {
                    $lastIndex = count($pages) - 1;
                    if ($lastIndex >= 0) {
                        $pages[$lastIndex]['canvas'] = $setCanvas;
                    }
                }
            }
        }

        if ($pages === []) {
            continue;
        }

        $allowedPresetIds = [];
        foreach ($pages as $page) {
            $presetId = (string) ($page['presetId'] ?? '');
            if ($presetId === '' || in_array($presetId, $allowedPresetIds, true)) {
                continue;
            }
            $allowedPresetIds[] = $presetId;
        }

        $normalizedSet = [
            'id' => $id,
            'label' => $label,
            'description' => trim((string) ($set['description'] ?? '')),
            'pages' => $pages,
            'presetIds' => $allowedPresetIds,
        ];
        if (is_array($setCanvas)) {
            $normalizedSet['canvas'] = $setCanvas;
        }
        if (is_array($setThemeDefaults)) {
            $normalizedSet['themeDefaults'] = $setThemeDefaults;
        }
        $normalizedSets[] = $normalizedSet;
    }

    if ($normalizedSets === []) {
        $pages = [];
        foreach ($presetIds as $presetIndex => $presetId) {
            $preset = $presetMap[$presetId] ?? [];
            $pageLabel = trim((string) ($preset['label'] ?? $preset['pageTitle'] ?? $presetId));
            if ($pageLabel === '') {
                $pageLabel = 'Page ' . ($presetIndex + 1);
            }
            $pageTitle = trim((string) ($preset['pageTitle'] ?? $pageLabel));
            if ($pageTitle === '') {
                $pageTitle = $pageLabel;
            }
            $slug = app_slugify((string) ($preset['slug'] ?? $pageTitle));
            if ($slug === '') {
                $slug = $presetId;
            }
            $pages[] = [
                'id' => app_slugify((string) ($preset['id'] ?? $slug)),
                'label' => $pageLabel,
                'pageTitle' => $pageTitle,
                'slug' => $slug,
                'presetId' => $presetId,
            ];
        }

        $normalizedSets[] = [
            'id' => 'all-site-types',
            'label' => 'All Site Types',
            'description' => '',
            'pages' => $pages,
            'presetIds' => $presetIds,
        ];
    }

    $defaultSetId = app_slugify((string) ($payload['defaultSiteTypeId'] ?? $payload['defaultSetId'] ?? $payload['defaultPresetSetId'] ?? ''));
    $availableSetIds = array_map(static fn (array $set): string => (string) $set['id'], $normalizedSets);
    if (!in_array($defaultSetId, $availableSetIds, true)) {
        $defaultSetId = $availableSetIds[0] ?? '';
    }

    return [
        'defaultSiteTypeId' => $defaultSetId,
        'defaultSetId' => $defaultSetId,
        'sets' => $normalizedSets,
    ];
}

function app_page_preset_sets_config_localized(array $presets, ?string $locale = null): array
{
    $config = app_page_preset_sets_config($presets);
    $config['sets'] = array_map(
        static fn (array $set): array => app_localize_page_preset_set($set, $locale),
        is_array($config['sets'] ?? null) ? $config['sets'] : []
    );

    return $config;
}

function app_site_type_by_id(string $siteTypeId): ?array
{
    $targetId = app_slugify($siteTypeId);
    if ($targetId === '') {
        return null;
    }

    $config = app_page_preset_sets_config(app_page_presets());
    foreach (($config['sets'] ?? []) as $set) {
        if (!is_array($set)) {
            continue;
        }
        if (app_slugify((string) ($set['id'] ?? '')) === $targetId) {
            return $set;
        }
    }

    return null;
}

function app_sitekit_project_seed(array $options = []): array
{
    $siteTypeId = app_slugify((string) ($options['siteTypeId'] ?? ''));
    $presetId = app_slugify((string) ($options['presetId'] ?? ''));
    $siteName = trim((string) ($options['siteName'] ?? ''));
    $pageTitle = trim((string) ($options['pageTitle'] ?? ''));
    $pageSlug = app_slugify((string) ($options['pageSlug'] ?? ''));
    $pageLocale = app_normalize_locale((string) ($options['locale'] ?? app_current_locale()));

    $theme = app_default_theme();
    if (isset($options['theme']) && is_array($options['theme'])) {
        $theme = app_normalize_theme_json($options['theme']);
    }

    $siteType = $siteTypeId !== '' ? app_site_type_by_id($siteTypeId) : null;
    if (is_array($siteType) && isset($siteType['themeDefaults']) && is_array($siteType['themeDefaults'])) {
        $theme = app_normalize_theme_json(app_theme_deep_merge($theme, $siteType['themeDefaults']));
    }

    $site = [];
    $activePage = null;

    if (is_array($siteType)) {
        $sitePages = [];
        foreach (($siteType['pages'] ?? []) as $pageSpec) {
            if (!is_array($pageSpec)) {
                continue;
            }

            $spec = $pageSpec;
            $spec['locale'] = $pageLocale;
            $candidatePresetId = app_slugify((string) ($spec['presetId'] ?? ''));
            if ($presetId !== '' && $candidatePresetId === $presetId) {
                if ($pageTitle !== '') {
                    $spec['pageTitle'] = $pageTitle;
                    $spec['title'] = $pageTitle;
                    $spec['label'] = $pageTitle;
                    $spec['menuLabel'] = $pageTitle;
                }
                if ($pageSlug !== '') {
                    $spec['slug'] = $pageSlug;
                    $spec['id'] = $pageSlug;
                    $spec['pageId'] = $pageSlug;
                }
            }

            $page = app_build_page_from_preset($candidatePresetId, $theme, $spec);
            if (!is_array($page)) {
                continue;
            }
            $page['locale'] = $pageLocale;
            $sitePages[] = $page;
        }

        if ($sitePages !== []) {
            $activePage = $sitePages[0];
            if ($presetId !== '') {
                foreach ($sitePages as $page) {
                    if (app_slugify((string) ($page['presetId'] ?? '')) === $presetId) {
                        $activePage = $page;
                        break;
                    }
                }
            }
            $site = [
                'name' => $siteName !== '' ? $siteName : trim((string) ($siteType['label'] ?? '')),
                'typeId' => (string) ($siteType['id'] ?? $siteTypeId),
                'activePageId' => (string) ($activePage['id'] ?? ''),
                'pages' => $sitePages,
            ];
        }
    }

    if (!is_array($activePage)) {
        if ($presetId !== '') {
            $activePage = app_build_page_from_preset($presetId, $theme, [
                'id' => $pageSlug !== '' ? $pageSlug : '',
                'pageId' => $pageSlug !== '' ? $pageSlug : '',
                'title' => $pageTitle,
                'pageTitle' => $pageTitle,
                'menuLabel' => $pageTitle,
                'label' => $pageTitle,
                'slug' => $pageSlug,
                'locale' => $pageLocale,
            ]);
        }

        if (!is_array($activePage)) {
            $activePage = app_default_page($theme);
            if ($pageTitle !== '') {
                $activePage['title'] = $pageTitle;
                $activePage['menuLabel'] = $pageTitle;
            }
            if ($pageSlug !== '') {
                $activePage['slug'] = $pageSlug;
                $activePage['id'] = $pageSlug;
            }
            $activePage['locale'] = $pageLocale;
            $activePage['sharedContact'] = app_normalize_shared_contact_profile($activePage['sharedContact'] ?? null, $pageLocale);
        }

        $site = array_filter([
            'name' => $siteName,
        ], static fn ($value): bool => $value !== '');
    }

    if ($siteName !== '') {
        $site['name'] = $siteName;
    }

    return app_import_sitekit_payload([
        'version' => '2.0',
        'theme' => $theme,
        'page' => $activePage,
        'site' => $site,
    ]);
}
