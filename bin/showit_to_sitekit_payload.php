#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/render.php';
require_once dirname(__DIR__) . '/inc/presets.php';

function showit_mapper_fail(string $message, int $code = 1): never
{
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit($code);
}

function showit_mapper_usage(): never
{
    $script = basename(__FILE__);
    $usage = <<<TXT
Usage:
  php bin/{$script} --output=/path/to/sitekit.json [--html=/path/to/divmagic.html] [--url=https://example.com/]
                     [--mapper-output=/path/to/mapper.json] [--skit-output=/path/to/sitekit.skit]
                     [--site-name="Site Name"]

Notes:
  - Use `--html` with a DivMagic HTML copy for the best Showit section detection.
  - `--url` is used for `site.baseUrl`, source metadata, and same-origin link normalization.
  - Current mapper focuses on single-page editorial Showit homepages and emits canonical SiteKit payloads.
TXT;

    fwrite(STDERR, $usage . PHP_EOL);
    exit(1);
}

function showit_mapper_parse_args(array $argv): array
{
    $options = [
        'html' => '',
        'url' => '',
        'output' => '',
        'mapper-output' => '',
        'skit-output' => '',
        'site-name' => '',
    ];

    for ($i = 1, $count = count($argv); $i < $count; $i++) {
        $arg = (string) $argv[$i];
        if ($arg === '--help' || $arg === '-h') {
            showit_mapper_usage();
        }
        if (!str_starts_with($arg, '--')) {
            showit_mapper_fail('Unexpected argument `' . $arg . '`.');
        }

        $name = substr($arg, 2);
        $value = null;
        if (str_contains($name, '=')) {
            [$name, $value] = explode('=', $name, 2);
        } else {
            $next = $argv[$i + 1] ?? null;
            if (is_string($next) && !str_starts_with($next, '--')) {
                $value = $next;
                $i++;
            }
        }

        if (!array_key_exists($name, $options)) {
            showit_mapper_fail('Unknown option `--' . $name . '`.');
        }

        $options[$name] = trim((string) ($value ?? ''));
    }

    if ($options['output'] === '') {
        showit_mapper_usage();
    }
    if ($options['html'] === '' && $options['url'] === '') {
        showit_mapper_fail('Provide at least one of `--html` or `--url`.');
    }

    return $options;
}

function showit_mapper_read_source_html(string $htmlPath, string $url): string
{
    if ($htmlPath !== '') {
        if (!is_file($htmlPath)) {
            showit_mapper_fail('HTML source not found at `' . $htmlPath . '`.');
        }
        $html = file_get_contents($htmlPath);
        if (!is_string($html) || $html === '') {
            showit_mapper_fail('Failed to read HTML source from `' . $htmlPath . '`.');
        }
        return $html;
    }

    $html = @file_get_contents($url);
    if (!is_string($html) || trim($html) === '') {
        showit_mapper_fail('Failed to fetch live HTML from `' . $url . '`.');
    }

    return $html;
}

function showit_mapper_normalize_text(string $text): string
{
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = str_replace(["\r", "\n", "\t", "\xc2\xa0"], [' ', ' ', ' ', ' '], $decoded);
    $decoded = preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;
    return trim($decoded);
}

function showit_mapper_origin(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    if ($scheme === '' || $host === '') {
        return '';
    }

    $origin = $scheme . '://' . $host;
    if (isset($parts['port'])) {
        $origin .= ':' . (string) $parts['port'];
    }

    return $origin;
}

function showit_mapper_normalize_url(string $url, string $baseUrl = ''): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
        return '';
    }

    $url = preg_replace('#^https?://[^/]+//static\.showit\.co/#i', 'https://static.showit.co/', $url) ?? $url;

    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }
    if (preg_match('#^https?://#i', $url) === 1) {
        return $url;
    }
    if ($baseUrl === '') {
        return $url;
    }

    $origin = showit_mapper_origin($baseUrl);
    if ($origin === '') {
        return $url;
    }
    if (str_starts_with($url, '/')) {
        return $origin . $url;
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}

function showit_mapper_relativize_url(string $url, string $baseUrl = ''): string
{
    $normalized = showit_mapper_normalize_url($url, $baseUrl);
    if ($normalized === '' || $baseUrl === '') {
        return $normalized;
    }

    $origin = showit_mapper_origin($baseUrl);
    $parts = parse_url($normalized);
    if ($origin === '' || !is_array($parts)) {
        return $normalized;
    }

    $valueOrigin = showit_mapper_origin($normalized);
    if ($valueOrigin === '' || $valueOrigin !== $origin) {
        return $normalized;
    }

    $path = (string) ($parts['path'] ?? '/');
    $fragment = (string) ($parts['fragment'] ?? '');
    $query = (string) ($parts['query'] ?? '');

    if ($path === '') {
        $path = '/';
    }
    if ($path === '/' && ($fragment === '/' || $fragment === '')) {
        return '/';
    }

    $relative = $path;
    if ($query !== '') {
        $relative .= '?' . $query;
    }
    if ($fragment !== '' && $fragment !== '/') {
        $relative .= '#' . $fragment;
    }

    return $relative;
}

function showit_mapper_normalize_color(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value) === 1) {
        if (strlen($value) === 4) {
            return '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }
        return $value;
    }
    if (preg_match('/rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})/i', $value, $matches) === 1) {
        return sprintf('#%02x%02x%02x', max(0, min(255, (int) $matches[1])), max(0, min(255, (int) $matches[2])), max(0, min(255, (int) $matches[3])));
    }

    return $value;
}

function showit_mapper_style_urls(string $style, string $baseUrl): array
{
    $matches = [];
    preg_match_all('/url\((["\']?)([^"\')]+)\1\)/i', $style, $matches, PREG_SET_ORDER);
    $urls = [];
    foreach ($matches as $match) {
        $url = showit_mapper_normalize_url((string) ($match[2] ?? ''), $baseUrl);
        if ($url !== '') {
            $urls[] = $url;
        }
    }

    return showit_mapper_unique_strings($urls);
}

function showit_mapper_style_background_color(string $style): string
{
    if (preg_match('/background-color\s*:\s*([^;]+)/i', $style, $matches) !== 1) {
        return '';
    }

    return showit_mapper_normalize_color((string) ($matches[1] ?? ''));
}

function showit_mapper_parse_height(string $style): int
{
    if (preg_match('/height\s*:\s*([0-9.]+)px/i', $style, $matches) !== 1) {
        return 0;
    }

    return (int) round((float) ($matches[1] ?? 0));
}

function showit_mapper_unique_strings(array $values): array
{
    $unique = [];
    $seen = [];
    foreach ($values as $value) {
        $text = trim((string) $value);
        if ($text === '' || isset($seen[$text])) {
            continue;
        }
        $seen[$text] = true;
        $unique[] = $text;
    }

    return $unique;
}

function showit_mapper_unique_rows(array $rows, callable $keyFn): array
{
    $unique = [];
    $seen = [];
    foreach ($rows as $row) {
        $key = (string) $keyFn($row);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $row;
    }

    return $unique;
}

function showit_mapper_direct_div_children(DOMNode $node): array
{
    $children = [];
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMElement && strtolower($child->tagName) === 'div') {
            $children[] = $child;
        }
    }
    return $children;
}

function showit_mapper_find_section_container(DOMDocument $document): DOMElement
{
    $xpath = new DOMXPath($document);
    $best = null;
    $bestScore = -1;

    foreach ($xpath->query('//div') as $candidate) {
        if (!$candidate instanceof DOMElement) {
            continue;
        }
        $children = showit_mapper_direct_div_children($candidate);
        $count = count($children);
        if ($count < 5) {
            continue;
        }

        $style = strtolower((string) $candidate->getAttribute('style'));
        $score = $count * 10;
        if (str_contains($style, 'min-width: 320px')) {
            $score += 25;
        }
        if (str_contains($style, 'overflow: hidden')) {
            $score += 10;
        }
        if (str_contains($style, 'width: 100%')) {
            $score += 5;
        }

        if ($score > $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    if (!$best instanceof DOMElement) {
        showit_mapper_fail('Could not detect the Showit section container.');
    }

    return $best;
}

function showit_mapper_extract_section(DOMElement $section, string $baseUrl, int $index): array
{
    $xpath = new DOMXPath($section->ownerDocument);
    $style = (string) $section->getAttribute('style');
    $textRecords = [];
    foreach ($xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6|.//p|.//nav', $section) as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $text = showit_mapper_normalize_text($node->textContent);
        if ($text === '') {
            continue;
        }
        $textRecords[] = [
            'tag' => strtolower($node->tagName),
            'text' => $text,
        ];
    }

    $links = [];
    foreach ($xpath->query('.//a[@href]', $section) as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $label = showit_mapper_normalize_text($node->textContent);
        $url = showit_mapper_relativize_url((string) $node->getAttribute('href'), $baseUrl);
        if ($label === '' && $url === '') {
            continue;
        }
        $links[] = [
            'label' => $label,
            'url' => $url,
        ];
    }
    $links = showit_mapper_unique_rows($links, static fn(array $row): string => strtolower(($row['label'] ?? '') . '|' . ($row['url'] ?? '')));

    $images = [];
    foreach ($xpath->query('.//img[@src]', $section) as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $url = showit_mapper_normalize_url((string) $node->getAttribute('src'), $baseUrl);
        if ($url === '') {
            continue;
        }
        $images[] = [
            'url' => $url,
            'alt' => showit_mapper_normalize_text((string) $node->getAttribute('alt')),
            'kind' => 'img',
        ];
    }

    $backgroundColors = [];
    $styledNodes = [$section];
    foreach ($xpath->query('.//*[@style]', $section) as $node) {
        if ($node instanceof DOMElement) {
            $styledNodes[] = $node;
        }
    }
    foreach ($styledNodes as $node) {
        $nodeStyle = (string) $node->getAttribute('style');
        foreach (showit_mapper_style_urls($nodeStyle, $baseUrl) as $url) {
            $images[] = [
                'url' => $url,
                'alt' => '',
                'kind' => 'background',
            ];
        }
        $background = showit_mapper_style_background_color($nodeStyle);
        if ($background !== '') {
            $backgroundColors[] = $background;
        }
    }
    $images = showit_mapper_unique_rows($images, static fn(array $row): string => (string) ($row['url'] ?? ''));

    $texts = [];
    foreach ($textRecords as $record) {
        $texts[] = (string) ($record['text'] ?? '');
    }

    return [
        'index' => $index,
        'height' => showit_mapper_parse_height($style),
        'texts' => showit_mapper_unique_strings($texts),
        'textRecords' => $textRecords,
        'links' => $links,
        'images' => $images,
        'backgroundColors' => showit_mapper_unique_strings($backgroundColors),
        'rawText' => strtolower(implode("\n", $texts)),
    ];
}

function showit_mapper_section_has_text(array $section, string $needle): bool
{
    return str_contains((string) ($section['rawText'] ?? ''), strtolower($needle));
}

function showit_mapper_section_photo_urls(array $section): array
{
    $urls = [];
    foreach (($section['images'] ?? []) as $image) {
        if (!is_array($image)) {
            continue;
        }
        $url = (string) ($image['url'] ?? '');
        if ($url === '') {
            continue;
        }
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (str_ends_with($path, '.svg')) {
            continue;
        }
        $urls[] = $url;
    }

    return showit_mapper_unique_strings($urls);
}

function showit_mapper_guess_site_name(array $sections, string $fallback): string
{
    if ($fallback !== '') {
        return $fallback;
    }

    foreach ($sections as $section) {
        foreach (($section['links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            if ($label !== '' && strlen($label) >= 6 && !in_array(strtolower($label), ['portfolio', 'services', 'experience', 'contact', 'inquire', 'resources'], true)) {
                return ucwords($label);
            }
        }
    }

    return 'Showit Site';
}

function showit_mapper_classify_section(array $section): ?string
{
    $texts = (array) ($section['texts'] ?? []);
    $links = (array) ($section['links'] ?? []);
    if ($texts === [] && $links === [] && (array) ($section['images'] ?? []) === []) {
        return null;
    }

    $joined = (string) ($section['rawText'] ?? '');
    $linkLabels = [];
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }
        $linkLabels[] = strtolower(trim((string) ($link['label'] ?? '')));
    }

    $serviceLabels = ['weddings', 'portraits', 'editorial', 'brands'];
    $serviceHits = 0;
    foreach ($serviceLabels as $serviceLabel) {
        if (in_array($serviceLabel, $linkLabels, true)) {
            $serviceHits++;
        }
    }

    if (str_contains($joined, '© tonic site shop') || str_contains($joined, 'luxury wedding photographer')) {
        return 'footer';
    }
    if ((int) ($section['height'] ?? 0) <= 160 && count($links) >= 6 && str_contains($joined, 'home') && str_contains($joined, 'portfolio')) {
        return 'header';
    }
    if (showit_mapper_section_has_text($section, 'the new') && showit_mapper_section_has_text($section, 'romantics')) {
        return 'hero';
    }
    if (showit_mapper_section_has_text($section, 'we make timeless images')) {
        return 'intro';
    }
    if ($serviceHits >= 4) {
        return 'services';
    }
    if (showit_mapper_section_has_text($section, 'ways to work') || showit_mapper_section_has_text($section, 'together')) {
        return 'together';
    }
    if (showit_mapper_section_has_text($section, 'roselyn') && showit_mapper_section_has_text($section, 'fitzgerald')) {
        return 'bio';
    }
    if (showit_mapper_section_has_text($section, 'our approach')) {
        return 'approach';
    }
    if (showit_mapper_section_has_text($section, 'past clients') || showit_mapper_section_has_text($section, 'no one sees light')) {
        return 'testimonial';
    }
    if (showit_mapper_section_has_text($section, 'explore') && showit_mapper_section_has_text($section, 'weddings')) {
        return 'portfolio-cta';
    }
    if (showit_mapper_section_has_text($section, 'featured') && showit_mapper_section_has_text($section, 'projects')) {
        return 'featured-projects';
    }
    if (showit_mapper_section_has_text($section, 'your inbox just got')) {
        return 'newsletter';
    }
    if (showit_mapper_section_has_text($section, 'more to explore')) {
        return 'blog-cards';
    }
    if (showit_mapper_section_has_text($section, "let's make magic")) {
        return 'contact-cta';
    }

    return null;
}

function showit_mapper_heading_texts(array $section, string $tag): array
{
    $headings = [];
    foreach (($section['textRecords'] ?? []) as $record) {
        if (!is_array($record) || (string) ($record['tag'] ?? '') !== $tag) {
            continue;
        }
        $text = trim((string) ($record['text'] ?? ''));
        if ($text !== '') {
            $headings[] = $text;
        }
    }

    return $headings;
}

function showit_mapper_first_paragraph(array $section): string
{
    foreach (($section['textRecords'] ?? []) as $record) {
        if (!is_array($record) || (string) ($record['tag'] ?? '') !== 'p') {
            continue;
        }
        $text = trim((string) ($record['text'] ?? ''));
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

function showit_mapper_first_link(array $section): array
{
    foreach (($section['links'] ?? []) as $link) {
        if (is_array($link)) {
            return $link;
        }
    }

    return ['label' => '', 'url' => ''];
}

function showit_mapper_build_block(string $type, int $variant, array $data, array $extra = []): array
{
    $block = [
        'type' => $type,
        'variant' => $variant,
        'data' => $data,
    ];

    foreach (['backgroundColor', 'cardColor', 'cardBorderColor', 'cardBorderStyle', 'cardBorderWidth', 'animation'] as $key) {
        if (array_key_exists($key, $extra) && $extra[$key] !== '' && $extra[$key] !== null) {
            $block[$key] = $extra[$key];
        }
    }

    return $block;
}

function showit_mapper_mapping_source_pattern(string $role): array
{
    $map = [
        'header' => ['family' => 'editorial-header-nav', 'label' => 'Editorial Header Nav', 'confidence' => 0.98, 'cues' => ['global nav', 'centered brand', 'single CTA']],
        'footer' => ['family' => 'editorial-footer-nav', 'label' => 'Editorial Footer Nav', 'confidence' => 0.98, 'cues' => ['large wordmark', 'footer nav', 'credits']],
        'hero' => ['family' => 'editorial-hero-overlay', 'label' => 'Editorial Hero Overlay', 'confidence' => 0.99, 'cues' => ['full-bleed photo', 'stacked headline', 'single CTA']],
        'intro' => ['family' => 'split-intro-story', 'label' => 'Split Intro Story', 'confidence' => 0.97, 'cues' => ['headline and body', 'single supporting image']],
        'services' => ['family' => 'oversized-services-list', 'label' => 'Oversized Services List', 'confidence' => 0.98, 'cues' => ['oversized service words', 'single destination URL']],
        'together' => ['family' => 'split-intro-story', 'label' => 'Ways To Work Callout', 'confidence' => 0.94, 'cues' => ['eyebrow + headline', 'paired links']],
        'bio' => ['family' => 'bio-band-with-portrait', 'label' => 'Bio Band', 'confidence' => 0.93, 'cues' => ['dark band', 'bio copy', 'learn more CTA']],
        'approach' => ['family' => 'split-intro-story', 'label' => 'Editorial Mission Statement', 'confidence' => 0.93, 'cues' => ['eyebrow', 'centered manifesto']],
        'testimonial' => ['family' => 'review-stage', 'label' => 'Review Stage', 'confidence' => 0.95, 'cues' => ['large quote', 'named attribution']],
        'portfolio-cta' => ['family' => 'dark-cta-band', 'label' => 'Dark CTA Band', 'confidence' => 0.98, 'cues' => ['dark background', 'editorial CTA', 'single button']],
        'featured-projects' => ['family' => 'portfolio-feature-list', 'label' => 'Portfolio Feature List', 'confidence' => 0.93, 'cues' => ['section title', 'image-led showcase', 'supporting links']],
        'newsletter' => ['family' => 'newsletter-split', 'label' => 'Newsletter Split', 'confidence' => 0.98, 'cues' => ['form teaser', 'background photo', 'download CTA']],
        'blog-cards' => ['family' => 'portfolio-feature-list', 'label' => 'Editorial Blog Cards', 'confidence' => 0.94, 'cues' => ['two story cards', 'read more CTA']],
        'contact-cta' => ['family' => 'dark-cta-band', 'label' => 'Inquiry CTA', 'confidence' => 0.98, 'cues' => ['dark band', 'image + CTA', 'inquiry button']],
    ];

    return $map[$role] ?? ['family' => 'unknown', 'label' => 'Unknown', 'confidence' => 0.5, 'cues' => []];
}

function showit_mapper_build_mapping(array $section, string $role, string $siteName, string $baseUrl): ?array
{
    $photos = showit_mapper_section_photo_urls($section);
    $sourceSection = [
        'height' => (int) ($section['height'] ?? 0),
        'backgroundColors' => array_values((array) ($section['backgroundColors'] ?? [])),
        'texts' => array_values((array) ($section['texts'] ?? [])),
        'links' => array_values((array) ($section['links'] ?? [])),
        'images' => array_values((array) ($section['images'] ?? [])),
    ];
    $mapping = [
        'id' => 'home-' . $role . '-' . ((int) ($section['index'] ?? 0) + 1),
        'kind' => 'section',
        'role' => $role,
        'sourcePattern' => showit_mapper_mapping_source_pattern($role),
        'sourceSection' => $sourceSection,
        'targetBlock' => [
            'shared' => false,
            'type' => 'content',
            'variant' => 1,
            'data' => [],
        ],
        'notes' => [],
    ];

    switch ($role) {
        case 'header':
            $brand = $siteName;
            $navLinks = [];
            $cta = ['label' => '', 'url' => ''];
            foreach ((array) ($section['links'] ?? []) as $index => $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = trim((string) ($link['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                if ($index === 0 && strlen($label) >= 6) {
                    $brand = ucwords($label);
                    continue;
                }
                if (strtolower($label) === 'inquire') {
                    $cta = $link;
                    continue;
                }
                $navLinks[] = [
                    'label' => $label,
                    'url' => (string) ($link['url'] ?? '#'),
                ];
            }
            $mapping['targetBlock'] = [
                'shared' => true,
                'type' => 'header',
                'variant' => 6,
                'layoutMode' => 'split-brand-center',
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'brand' => $brand,
                    'brandUrl' => '/',
                    'navLayout' => 'split-brand-center',
                    'splitNavAt' => 4,
                    'splitHideTagline' => true,
                    'splitHideCta' => false,
                    'tagline' => '',
                    'sticky' => true,
                    'ctaLabel' => (string) ($cta['label'] ?? ''),
                    'ctaUrl' => (string) ($cta['url'] ?? ''),
                    'links' => $navLinks,
                ],
            ];
            $mapping['notes'][] = 'Mapped to shared header block to keep the home page payload dry.';
            return $mapping;

        case 'footer':
            $tagline = '';
            $copyright = '';
            foreach ((array) ($section['texts'] ?? []) as $text) {
                $candidate = trim((string) $text);
                if ($tagline === '' && str_contains(strtolower($candidate), 'luxury wedding photographer')) {
                    $tagline = $candidate;
                }
                if ($copyright === '' && str_contains($candidate, '©')) {
                    $copyright = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
                }
            }
            $brand = $siteName;
            $links = [];
            $creditsSegments = [];
            $cta = ['label' => '', 'url' => ''];
            foreach ((array) ($section['links'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = trim((string) ($link['label'] ?? ''));
                $url = (string) ($link['url'] ?? '');
                if ($label === '') {
                    continue;
                }
                if (strtolower($label) === strtolower($siteName)) {
                    $brand = $siteName;
                    continue;
                }
                if (str_contains(strtolower($label), 'site credit')) {
                    $creditsSegments[] = ['text' => $label, 'url' => $url];
                    continue;
                }
                if (strtolower($label) === 'inquire') {
                    $cta = ['label' => $label, 'url' => $url];
                    continue;
                }
                $links[] = ['label' => $label, 'url' => $url];
            }
            $mapping['targetBlock'] = [
                'shared' => true,
                'type' => 'footer',
                'variant' => 5,
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'heading' => '',
                    'brand' => $brand,
                    'tagline' => $tagline,
                    'address' => '',
                    'contactLine' => '',
                    'blurb' => '',
                    'legalLine' => '',
                    'creditsSegments' => $creditsSegments,
                    'ctaLabel' => (string) ($cta['label'] ?? ''),
                    'ctaUrl' => (string) ($cta['url'] ?? ''),
                    'links' => $links,
                    'columns' => [],
                    'copyright' => $copyright,
                ],
            ];
            $mapping['notes'][] = 'Keep footer links flat; do not emit legacy nested `columns` for Showit imports.';
            return $mapping;

        case 'hero':
            $headings = showit_mapper_heading_texts($section, 'h1');
            $cta = showit_mapper_first_link($section);
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'hero',
                'variant' => 5,
                'layoutMode' => 'title-overlay',
                'data' => [
                    'layoutMode' => 'title-overlay',
                    'heading' => implode("\n", $headings),
                    'subheading' => showit_mapper_first_paragraph($section),
                    'scrollCue' => '',
                    'ctaLabel' => (string) ($cta['label'] ?? ''),
                    'ctaUrl' => (string) ($cta['url'] ?? ''),
                    'secondaryLabel' => '',
                    'secondaryUrl' => '',
                    'mediaAlign' => 'auto',
                    'copyInsetX' => 0,
                    'heroVerticalMargin' => 0,
                    'imageMode' => 'manual',
                    'imageFit' => 'cover',
                    'imagePosition' => 'center',
                    'imageUrl' => (string) ($photos[0] ?? ''),
                    'images' => [],
                    'bullets' => [],
                ],
            ];
            return $mapping;

        case 'intro':
            $heading = showit_mapper_heading_texts($section, 'h2');
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'content',
                'variant' => 1,
                'data' => [
                    'layoutMode' => 'default',
                    'sectionTitle' => (string) ($heading[0] ?? ''),
                    'headingLevel' => 'h2',
                    'sectionTitleSecondary' => '',
                    'columnOneText' => showit_mapper_first_paragraph($section),
                    'columnTwoText' => '',
                    'sideLabel' => 'FP',
                    'imageColumn' => '3',
                    'imageMode' => 'manual',
                    'imageUrl' => (string) ($photos[0] ?? ''),
                    'secondaryImageMode' => 'manual',
                    'secondaryImageUrl' => '',
                    'decorImageUrl' => '',
                    'items' => [],
                ],
            ];
            return $mapping;

        case 'services':
            $items = [];
            foreach ((array) ($section['links'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = trim((string) ($link['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $items[] = [
                    'title' => $label,
                    'text' => '',
                    'price' => '',
                    'icon' => '',
                    'linkUrl' => (string) ($link['url'] ?? '#'),
                    'imageMode' => 'manual',
                    'imageUrl' => '',
                ];
            }
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'services',
                'variant' => 1,
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'sectionTitle' => 'Services',
                    'sectionSubtitle' => '',
                    'columns' => 1,
                    'items' => $items,
                ],
            ];
            return $mapping;

        case 'together':
            $links = array_values((array) ($section['links'] ?? []));
            $primary = $links[0] ?? ['label' => '', 'url' => ''];
            $secondary = $links[1] ?? ['label' => '', 'url' => ''];
            $headings = showit_mapper_heading_texts($section, 'h2');
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'cta',
                'variant' => 8,
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'layoutMode' => 'default',
                    'eyebrow' => 'ways to work',
                    'heading' => (string) ($headings[0] ?? 'TOGETHER'),
                    'headingLevel' => 'h2',
                    'text' => showit_mapper_first_paragraph($section),
                    'imageMode' => 'manual',
                    'imageUrl' => '',
                    'buttonLabel' => (string) ($primary['label'] ?? ''),
                    'buttonUrl' => (string) ($primary['url'] ?? ''),
                    'secondaryLabel' => (string) ($secondary['label'] ?? ''),
                    'secondaryUrl' => (string) ($secondary['url'] ?? ''),
                ],
            ];
            return $mapping;

        case 'bio':
            $headings = showit_mapper_heading_texts($section, 'h2');
            $cta = showit_mapper_first_link($section);
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'cta',
                'variant' => 1,
                'backgroundColor' => '#253320',
                'cardColor' => '#253320',
                'data' => [
                    'layoutMode' => 'default',
                    'eyebrow' => '',
                    'heading' => implode("\n", $headings),
                    'headingLevel' => 'h2',
                    'text' => showit_mapper_first_paragraph($section),
                    'imageMode' => 'manual',
                    'imageUrl' => '',
                    'buttonLabel' => (string) ($cta['label'] ?? ''),
                    'buttonUrl' => (string) ($cta['url'] ?? ''),
                    'secondaryLabel' => '',
                    'secondaryUrl' => '',
                ],
            ];
            return $mapping;

        case 'approach':
            $headings = showit_mapper_heading_texts($section, 'h2');
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'cta',
                'variant' => 3,
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'layoutMode' => 'default',
                    'eyebrow' => 'our approach',
                    'heading' => (string) ($headings[0] ?? ''),
                    'headingLevel' => 'h2',
                    'text' => showit_mapper_first_paragraph($section),
                    'imageMode' => 'manual',
                    'imageUrl' => '',
                    'buttonLabel' => '',
                    'buttonUrl' => '',
                    'secondaryLabel' => '',
                    'secondaryUrl' => '',
                ],
            ];
            return $mapping;

        case 'testimonial':
            $quote = '';
            $name = '';
            foreach ((array) ($section['texts'] ?? []) as $text) {
                $candidate = trim((string) $text);
                if ($quote === '' && str_contains($candidate, 'Fitzgerald')) {
                    $quote = trim($candidate, " \t\n\r\0\x0B“”\"");
                    continue;
                }
                if ($name === '' && str_contains(strtolower($candidate), 'past clients')) {
                    $name = $candidate;
                }
            }
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'testimonials',
                'variant' => 8,
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'sectionTitle' => 'Kind words',
                    'sectionSubtitle' => '',
                    'layoutMode' => 'review-stage',
                    'asideLabel' => 'Kind words from',
                    'columns' => 1,
                    'thumbnails' => [],
                    'items' => [
                        [
                            'quote' => $quote,
                            'name' => $name,
                            'role' => '',
                            'rating' => 5,
                            'imageMode' => 'manual',
                            'imageUrl' => '',
                        ],
                    ],
                ],
            ];
            return $mapping;

        case 'portfolio-cta':
            $headings = showit_mapper_heading_texts($section, 'h2');
            $cta = showit_mapper_first_link($section);
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'cta',
                'variant' => 1,
                'backgroundColor' => '#070707',
                'cardColor' => '#070707',
                'data' => [
                    'layoutMode' => 'default',
                    'eyebrow' => '',
                    'heading' => implode("\n", $headings),
                    'headingLevel' => 'h2',
                    'text' => showit_mapper_first_paragraph($section),
                    'imageMode' => 'manual',
                    'imageUrl' => '',
                    'buttonLabel' => (string) ($cta['label'] ?? ''),
                    'buttonUrl' => (string) ($cta['url'] ?? ''),
                    'secondaryLabel' => '',
                    'secondaryUrl' => '',
                ],
            ];
            return $mapping;

        case 'featured-projects':
            $viewMoreUrls = [];
            foreach ((array) ($section['links'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = strtolower(trim((string) ($link['label'] ?? '')));
                if ($label === 'view more') {
                    $viewMoreUrls[] = (string) ($link['url'] ?? '#');
                }
            }
            $galleryImages = array_slice($photos, 0, 3);
            if (count($galleryImages) < 3) {
                $galleryImages = array_values(array_unique(array_merge($galleryImages, [
                    'https://static.showit.co/400/gWuMW6Q_QHaPRtMdTCa7sw/51489/lecollectif-102.jpg',
                    'https://static.showit.co/file/7Hj3x5JQRmiLPMwwFFNV-A/51489/lecollectif-44.jpg',
                    'https://static.showit.co/file/51qKNGppTOiUb9bbsUpy6w/51489/lecollectif-77.jpg',
                ])));
            }
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'gallery',
                'variant' => 6,
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'sectionTitle' => 'Featured Projects',
                    'layoutMode' => 'editorial-collage',
                    'introText' => '',
                    'columns' => 3,
                    'lightbox' => false,
                    'links' => [
                        ['label' => 'Project One', 'url' => (string) ($viewMoreUrls[0] ?? '/single-post-demo-delete')],
                        ['label' => 'Project Two', 'url' => (string) ($viewMoreUrls[1] ?? '/single-post-demo-delete')],
                        ['label' => 'Project Three', 'url' => (string) ($viewMoreUrls[2] ?? '/single-post-demo-delete')],
                    ],
                    'decorImages' => [],
                    'images' => [
                        ['imageMode' => 'manual', 'url' => (string) ($galleryImages[0] ?? ''), 'alt' => 'Featured project image', 'eyebrow' => 'Featured', 'caption' => 'Project one'],
                        ['imageMode' => 'manual', 'url' => (string) ($galleryImages[1] ?? ''), 'alt' => 'Editorial project image', 'eyebrow' => 'Portfolio', 'caption' => 'Project two'],
                        ['imageMode' => 'manual', 'url' => (string) ($galleryImages[2] ?? ''), 'alt' => 'Wedding project image', 'eyebrow' => 'Story', 'caption' => 'Project three'],
                    ],
                ],
            ];
            $mapping['notes'][] = 'Showit used one dominant image plus text links; mapped to an editorial collage to stay within SiteKit block families.';
            return $mapping;

        case 'newsletter':
            $cta = showit_mapper_first_link($section);
            $heading = '';
            foreach ((array) ($section['texts'] ?? []) as $text) {
                $candidate = trim((string) $text);
                if (str_contains(strtolower($candidate), 'your inbox just got')) {
                    $heading = $candidate;
                    break;
                }
            }
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'cta',
                'variant' => 6,
                'layoutMode' => 'newsletter-split',
                'backgroundColor' => '#f9f9f5',
                'data' => [
                    'layoutMode' => 'newsletter-split',
                    'eyebrow' => '',
                    'heading' => $heading,
                    'headingLevel' => 'h2',
                    'text' => 'First Name\nEmail Address',
                    'imageMode' => 'manual',
                    'imageUrl' => (string) ($photos[0] ?? ''),
                    'buttonLabel' => (string) ($cta['label'] ?? ''),
                    'buttonUrl' => (string) ($cta['url'] ?? ''),
                    'secondaryLabel' => '',
                    'secondaryUrl' => '',
                ],
            ];
            return $mapping;

        case 'blog-cards':
            $cards = [];
            $links = array_values((array) ($section['links'] ?? []));
            $photos = array_values($photos);
            $pairs = [
                ['titleIndex' => 0, 'ctaIndex' => 1, 'imageIndex' => 0],
                ['titleIndex' => 2, 'ctaIndex' => 3, 'imageIndex' => 1],
            ];
            foreach ($pairs as $pairIndex => $pair) {
                $titleLink = is_array($links[$pair['titleIndex']] ?? null) ? $links[$pair['titleIndex']] : null;
                $ctaLink = is_array($links[$pair['ctaIndex']] ?? null) ? $links[$pair['ctaIndex']] : null;
                if ($titleLink === null) {
                    continue;
                }
                $cards[] = [
                    'badge' => 'Journal',
                    'title' => (string) ($titleLink['label'] ?? ''),
                    'text' => (string) (($ctaLink['label'] ?? '') !== '' ? ($ctaLink['label'] ?? '') : 'Read on the blog'),
                    'linkUrl' => (string) ($titleLink['url'] ?? '#'),
                    'imageMode' => 'manual',
                    'imageUrl' => (string) ($photos[$pair['imageIndex']] ?? ''),
                ];
            }
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'cards',
                'variant' => 1,
                'backgroundColor' => '#eae7e1',
                'data' => [
                    'sectionTitle' => 'More To Explore',
                    'sectionSubtitle' => '',
                    'columns' => 2,
                    'cards' => $cards,
                ],
            ];
            return $mapping;

        case 'contact-cta':
            $cta = showit_mapper_first_link($section);
            $heading = '';
            foreach ((array) ($section['texts'] ?? []) as $text) {
                $candidate = trim((string) $text);
                if (str_contains(strtolower($candidate), 'we\'d love to work with you')) {
                    break;
                }
                if (str_contains(strtolower($candidate), "let's make magic")) {
                    $heading = $candidate;
                    break;
                }
            }
            $copy = '';
            foreach ((array) ($section['texts'] ?? []) as $text) {
                $candidate = trim((string) $text);
                if (str_contains(strtolower($candidate), 'we\'d love to work with you')) {
                    $copy = $candidate;
                    break;
                }
            }
            $mapping['targetBlock'] = [
                'shared' => false,
                'type' => 'cta',
                'variant' => 6,
                'layoutMode' => 'overlay-card',
                'backgroundColor' => '#253320',
                'cardColor' => '#253320',
                'data' => [
                    'layoutMode' => 'overlay-card',
                    'eyebrow' => '',
                    'heading' => $heading,
                    'headingLevel' => 'h2',
                    'text' => $copy,
                    'imageMode' => 'manual',
                    'imageUrl' => (string) ($photos[0] ?? ''),
                    'buttonLabel' => (string) ($cta['label'] ?? ''),
                    'buttonUrl' => (string) ($cta['url'] ?? ''),
                    'secondaryLabel' => '',
                    'secondaryUrl' => '',
                ],
            ];
            return $mapping;
    }

    return null;
}

function showit_mapper_block_from_target(array $targetBlock): array
{
    $data = is_array($targetBlock['data'] ?? null) ? $targetBlock['data'] : [];
    if (isset($targetBlock['layoutMode']) && !isset($data['layoutMode'])) {
        $data['layoutMode'] = $targetBlock['layoutMode'];
    }

    return showit_mapper_build_block(
        (string) ($targetBlock['type'] ?? 'content'),
        (int) ($targetBlock['variant'] ?? 1),
        $data,
        [
            'backgroundColor' => $targetBlock['backgroundColor'] ?? '',
            'cardColor' => $targetBlock['cardColor'] ?? '',
            'cardBorderColor' => $targetBlock['cardBorderColor'] ?? '',
            'cardBorderStyle' => $targetBlock['cardBorderStyle'] ?? '',
            'cardBorderWidth' => $targetBlock['cardBorderWidth'] ?? '',
            'animation' => $targetBlock['animation'] ?? '',
        ]
    );
}

function showit_mapper_collect_image_urls(array $value): array
{
    $urls = [];
    $walker = static function ($node) use (&$walker, &$urls): void {
        if (is_array($node)) {
            foreach ($node as $child) {
                $walker($child);
            }
            return;
        }
        if (!is_string($node)) {
            return;
        }
        $trimmed = trim($node);
        if ($trimmed === '') {
            return;
        }
        if (preg_match('#^https?://#i', $trimmed) !== 1) {
            return;
        }
        $path = strtolower((string) parse_url($trimmed, PHP_URL_PATH));
        if ($path === '') {
            return;
        }
        if (
            str_ends_with($path, '.jpg')
            || str_ends_with($path, '.jpeg')
            || str_ends_with($path, '.png')
            || str_ends_with($path, '.webp')
            || str_ends_with($path, '.gif')
        ) {
            $urls[] = $trimmed;
        }
    };

    $walker($value);
    return showit_mapper_unique_strings($urls);
}

function showit_mapper_theme(array $imageUrls, string $siteName, string $sourceUrl, string $extractionMode): array
{
    return [
        'theme' => [
            'id' => 'showit-' . app_slugify($siteName),
            'name' => $siteName,
            'colors' => [
                'background' => '#f9f9f5',
                'surface' => '#ffffff',
                'primary' => '#253320',
                'secondary' => '#070707',
                'text' => '#070707',
                'textMuted' => '#6f6b64',
                'border' => '#d9d5cd',
            ],
            'effects' => [
                'shadow' => 'none',
            ],
            'picsumId' => '0',
        ],
        'palette' => [
            'base' => '#253320',
            'lighter' => '#eae7e1',
            'darker' => '#070707',
            'complementary' => '#8c8a78',
            'splitComplementary' => '#ffffff',
            'triadic' => '#d5d0c5',
            'background' => '#f9f9f5',
        ],
        'gradient' => [
            'accent' => '#8c8a78',
            'angle' => 180,
            'glow' => 0,
            'gradStart' => '#f9f9f5',
            'gradEnd' => '#eae7e1',
        ],
        'typography' => [
            'mode' => 'theme',
            'heading' => [
                'family' => 'Georgia, "Times New Roman", serif',
                'size' => 72,
                'weight' => 400,
                'lineHeight' => 1.08,
                'letterSpacing' => 0,
                'color' => '#070707',
                'h2Color' => '#070707',
                'h3PlusColor' => '#070707',
            ],
            'body' => [
                'family' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
                'size' => 18,
                'weight' => 300,
                'lineHeight' => 1.65,
                'letterSpacing' => 0,
                'color' => '#070707',
            ],
            'link' => [
                'color' => '#070707',
            ],
            'fontFamily' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
            'headingFont' => 'Georgia, "Times New Roman", serif',
            'bodyFont' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
            'fontSizeBase' => '18px',
        ],
        'images' => array_map(static fn(string $url): array => ['url' => $url], $imageUrls),
        'metadata' => [
            'createdAt' => gmdate('Y-m-d'),
            'sourcePlatform' => 'showit',
            'sourceUrl' => $sourceUrl,
            'extractionMode' => $extractionMode,
            'generator' => 'bin/showit_to_sitekit_payload.php',
        ],
        'elements' => [
            'card' => [
                'opacity' => 1,
                'radius' => 0,
                'color' => '#fffdf9',
                'shadow' => 'none',
                'hoverShadow' => 'none',
            ],
            'button' => [
                'color' => '#253320',
                'opacity' => 1,
                'borderColor' => '#253320',
                'secondaryBackground' => 'transparent',
                'radius' => 4,
                'shadow' => 'none',
                'hoverShadow' => 'none',
            ],
            'input' => [
                'background' => '#ffffff',
            ],
            'hr' => [
                'color' => '#d9d5cd',
            ],
            'borderWidth' => 1,
        ],
    ];
}

function showit_mapper_payload(array $theme, string $siteName, string $baseUrl, array $sharedBlocks, array $pageBlocks): array
{
    $canvas = [
        'lineHeightScale' => 100,
        'spacingXScale' => 100,
        'spacingYScale' => 140,
        'elementSpacingScale' => 100,
        'gradientOpacity' => 100,
        'linkAnimation' => 'underlineSweep',
        'containerMaxWidthDesktop' => 86,
        'containerMaxWidthTablet' => 92,
        'containerMaxWidthMobile' => 100,
        'footerFollowContainerMaxWidth' => true,
    ];

    return [
        'version' => '2.0',
        'theme' => $theme,
        'palette' => $theme['palette'],
        'typography' => $theme['typography'],
        'gradient' => $theme['gradient'],
        'elements' => $theme['elements'],
        'images' => $theme['images'],
        'metadata' => $theme['metadata'],
        'site' => [
            'name' => $siteName,
            'typeId' => app_slugify($siteName) . '-showit',
            'activePageId' => 'home',
            'baseUrl' => $baseUrl,
            'canvas' => $canvas,
            'sharedBlocks' => $sharedBlocks,
            'pages' => [
                [
                    'id' => 'home',
                    'title' => 'Home',
                    'menuLabel' => 'Home',
                    'slug' => 'home',
                    'locale' => 'en',
                    'showInHeaderNav' => true,
                    'showInFooterNav' => true,
                    'blocks' => $pageBlocks,
                ],
            ],
        ],
        'page' => [
            'title' => 'Home',
            'slug' => 'home',
            'locale' => 'en',
            'blocks' => $pageBlocks,
        ],
    ];
}

$options = showit_mapper_parse_args($argv);
$sourceHtml = showit_mapper_read_source_html($options['html'], $options['url']);
$sourceUrl = $options['url'];

$document = new DOMDocument();
$wrappedHtml = '<!doctype html><html><body>' . $sourceHtml . '</body></html>';
libxml_use_internal_errors(true);
$loaded = $document->loadHTML($wrappedHtml, LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();
if ($loaded !== true) {
    showit_mapper_fail('Failed to parse the source HTML into a DOM.');
}

$sectionContainer = showit_mapper_find_section_container($document);
$rawSections = [];
$baseUrl = $sourceUrl;
if ($baseUrl === '') {
    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//a[@href]') as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }
        $href = trim((string) $anchor->getAttribute('href'));
        if ($href !== '' && preg_match('#^https?://#i', $href) === 1 && !str_contains($href, 'static.showit.co')) {
            $baseUrl = rtrim(showit_mapper_origin($href), '/') . '/';
            break;
        }
    }
}
if ($baseUrl !== '' && !str_ends_with($baseUrl, '/')) {
    $baseUrl .= '/';
}

foreach (showit_mapper_direct_div_children($sectionContainer) as $index => $sectionElement) {
    $rawSections[] = showit_mapper_extract_section($sectionElement, $baseUrl, $index);
}

$siteName = showit_mapper_guess_site_name($rawSections, trim((string) $options['site-name']));
$mappings = [];
$sharedBlocks = [];
$pageBlocks = [];
foreach ($rawSections as $section) {
    $role = showit_mapper_classify_section($section);
    if ($role === null) {
        continue;
    }
    $mapping = showit_mapper_build_mapping($section, $role, $siteName, $baseUrl);
    if ($mapping === null) {
        continue;
    }
    $targetBlock = is_array($mapping['targetBlock'] ?? null) ? $mapping['targetBlock'] : [];
    $block = showit_mapper_block_from_target($targetBlock);
    if (!empty($targetBlock['shared'])) {
        $sharedBlocks[(string) ($block['type'] ?? '')] = $block;
    } else {
        $pageBlocks[] = $block;
    }
    $mappings[] = $mapping;
}

if (!isset($sharedBlocks['header']) || !isset($sharedBlocks['footer'])) {
    showit_mapper_fail('Expected the Showit mapper to find both a shared header and a shared footer.');
}
if ($pageBlocks === []) {
    showit_mapper_fail('Expected the Showit mapper to find at least one page block.');
}

$themeImageUrls = showit_mapper_collect_image_urls([
    'sharedBlocks' => $sharedBlocks,
    'pageBlocks' => $pageBlocks,
]);
$theme = showit_mapper_theme(
    $themeImageUrls,
    $siteName,
    $sourceUrl !== '' ? $sourceUrl : $baseUrl,
    $options['html'] !== '' ? 'divmagic-html' : 'live-dom'
);
$payload = showit_mapper_payload($theme, $siteName, $baseUrl, $sharedBlocks, $pageBlocks);

app_validate_sitekit_payload($payload);
app_import_sitekit_payload($payload);

$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
if (!is_string($payloadJson) || $payloadJson === '') {
    showit_mapper_fail('Failed to encode the SiteKit payload as JSON.');
}
if (file_put_contents($options['output'], $payloadJson) === false) {
    showit_mapper_fail('Failed to write payload JSON to `' . $options['output'] . '`.');
}

if ($options['mapper-output'] !== '') {
    $mapperPayload = [
        'version' => '1.0',
        'source' => [
            'platform' => 'showit',
            'sourceUrls' => array_values(array_filter([$sourceUrl !== '' ? $sourceUrl : $baseUrl])),
            'baseUrl' => $baseUrl,
            'extractionMode' => $options['html'] !== '' ? 'divmagic-html' : 'live-dom',
            'artifacts' => array_values(array_filter([
                $options['html'] !== '' ? ['type' => 'html', 'path' => $options['html']] : null,
            ])),
        ],
        'globals' => [
            'header' => $mappings[array_search('header', array_column($mappings, 'role'), true)] ?? null,
            'footer' => $mappings[array_search('footer', array_column($mappings, 'role'), true)] ?? null,
        ],
        'pages' => [
            [
                'id' => 'home',
                'title' => 'Home',
                'slug' => 'home',
                'sourceUrl' => $sourceUrl !== '' ? $sourceUrl : $baseUrl,
                'sections' => array_values(array_filter($mappings, static fn(array $mapping): bool => !in_array((string) ($mapping['role'] ?? ''), ['header', 'footer'], true))),
            ],
        ],
    ];
    $mapperJson = json_encode($mapperPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($mapperJson) || $mapperJson === '') {
        showit_mapper_fail('Failed to encode the mapper JSON.');
    }
    if (file_put_contents($options['mapper-output'], $mapperJson) === false) {
        showit_mapper_fail('Failed to write mapper JSON to `' . $options['mapper-output'] . '`.');
    }
}

if ($options['skit-output'] !== '') {
    $skit = app_encode_skit_envelope($payload);
    $skitJson = json_encode($skit, JSON_UNESCAPED_SLASHES);
    if (!is_string($skitJson) || $skitJson === '') {
        showit_mapper_fail('Failed to encode the `.skit` envelope.');
    }
    if (file_put_contents($options['skit-output'], $skitJson) === false) {
        showit_mapper_fail('Failed to write `.skit` output to `' . $options['skit-output'] . '`.');
    }
}

fwrite(STDOUT, 'OK: generated SiteKit payload from Showit source' . PHP_EOL);
