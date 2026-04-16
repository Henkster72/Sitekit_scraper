<?php
declare(strict_types=1);

require_once __DIR__ . '/utils.php';

function app_shared_contact_locale_defaults(?string $locale = null): array
{
    $resolvedLocale = app_normalize_locale($locale ?? app_current_locale());
    $defaults = [
        'en' => [
            'email' => 'hello@example.com',
            'phone' => '+1 (555) 010-9000',
            'address' => "123 Main Street\nSpringfield, IL 62704\nUnited States",
            'locationLabel' => 'Main Office',
            'locationNote' => 'Mon-Fri 09:00-18:00',
            'latitude' => '39.781721',
            'longitude' => '-89.650148',
        ],
        'de' => [
            'email' => 'kontakt@beispiel.de',
            'phone' => '+49 30 12345678',
            'address' => "Friedrichstrasse 123\n10117 Berlin\nDeutschland",
            'locationLabel' => 'Hauptstandort',
            'locationNote' => 'Mo-Fr 09:00-18:00',
            'latitude' => '52.507195',
            'longitude' => '13.390349',
        ],
        'fr' => [
            'email' => 'contact@exemple.fr',
            'phone' => '+33 1 89 70 45 60',
            'address' => "24 rue des Fleurs\n75008 Paris\nFrance",
            'locationLabel' => 'Bureau principal',
            'locationNote' => 'Lun-Ven 09:00-18:00',
            'latitude' => '48.870579',
            'longitude' => '2.316439',
        ],
        'nl' => [
            'email' => 'hallo@voorbeeld.nl',
            'phone' => '+31 20 123 4567',
            'address' => "Keizersgracht 120\n1015 CW Amsterdam\nNederland",
            'locationLabel' => 'Hoofdkantoor',
            'locationNote' => 'Ma-Vr 09:00-18:00',
            'latitude' => '52.376772',
            'longitude' => '4.883606',
        ],
        'es' => [
            'email' => 'hola@ejemplo.es',
            'phone' => '+34 91 123 45 67',
            'address' => "Calle Mayor 18\n28013 Madrid\nEspaña",
            'locationLabel' => 'Oficina principal',
            'locationNote' => 'Lun-Vie 09:00-18:00',
            'latitude' => '40.416775',
            'longitude' => '-3.703790',
        ],
        'pt' => [
            'email' => 'ola@exemplo.pt',
            'phone' => '+351 21 123 45 67',
            'address' => "Rua Augusta 120\n1100-053 Lisboa\nPortugal",
            'locationLabel' => 'Escritório principal',
            'locationNote' => 'Seg-Sex 09:00-18:00',
            'latitude' => '38.707750',
            'longitude' => '-9.136592',
        ],
    ];

    return $defaults[$resolvedLocale] ?? $defaults['en'];
}

function app_normalize_shared_contact_profile($value, ?string $locale = null): array
{
    $defaults = app_shared_contact_locale_defaults($locale);
    $source = is_array($value) ? $value : [];

    $normalizeSingle = static function (string $key) use ($source, $defaults): string {
        if (!array_key_exists($key, $source)) {
            return $defaults[$key];
        }
        return trim((string) $source[$key]);
    };

    $normalizeMultiline = static function (string $key) use ($source, $defaults): string {
        if (!array_key_exists($key, $source)) {
            return $defaults[$key];
        }
        return trim(str_replace("\r", '', (string) $source[$key]));
    };

    return [
        'email' => $normalizeSingle('email'),
        'phone' => $normalizeSingle('phone'),
        'address' => $normalizeMultiline('address'),
        'locationLabel' => $normalizeSingle('locationLabel'),
        'locationNote' => $normalizeSingle('locationNote'),
        'latitude' => $normalizeSingle('latitude'),
        'longitude' => $normalizeSingle('longitude'),
    ];
}

function app_shared_contact_line(array $profile): string
{
    $parts = array_values(array_filter([
        trim((string) ($profile['email'] ?? '')),
        trim((string) ($profile['phone'] ?? '')),
    ], static fn(string $value): bool => $value !== ''));

    return implode(' · ', $parts);
}

function app_block_registry(): array
{
    static $registryByLocale = [];
    $locale = app_current_locale();
    if (isset($registryByLocale[$locale])) {
        return $registryByLocale[$locale];
    }

    $registry = [
        'header' => [
            'type' => 'header',
            'name' => 'Header',
            'category' => 'Layout',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'header',
            'thumb' => ['layout' => 'nav'],
            'schema' => [
                ['key' => 'brand', 'label' => 'Brand', 'type' => 'text', 'default' => 'SiteKit'],
                ['key' => 'brandImageUrl', 'label' => 'Brand Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'brandResolvedImageUrl', 'label' => 'Resolved Brand Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'brandImageAlt', 'label' => 'Brand Image Alt', 'type' => 'text', 'default' => 'Brand'],
                ['key' => 'brandUrl', 'label' => 'Brand URL', 'type' => 'url', 'default' => '#home'],
                ['key' => 'decorImageUrl', 'label' => 'Decor Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'decorResolvedImageUrl', 'label' => 'Resolved Decor Image URL', 'type' => 'url', 'default' => ''],
                [
                    'key' => 'decorPosition',
                    'label' => 'Decor Position',
                    'type' => 'select',
                    'default' => 'top-left',
                    'options' => [
                        ['value' => 'top-left', 'label' => 'Top Left'],
                        ['value' => 'top-right', 'label' => 'Top Right'],
                        ['value' => 'brand-left', 'label' => 'Behind Brand Left'],
                        ['value' => 'brand-center', 'label' => 'Behind Brand Center'],
                        ['value' => 'brand-right', 'label' => 'Behind Brand Right'],
                    ],
                ],
                [
                    'key' => 'navLayout',
                    'label' => 'Nav Layout',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'split-brand-center', 'label' => 'Split Brand Center'],
                        ['value' => 'brand-left-center-links', 'label' => 'Brand Left / Center Links'],
                    ],
                ],
                [
                    'key' => 'splitNavAt',
                    'label' => 'Split After Item',
                    'type' => 'number',
                    'control' => 'slider',
                    'default' => 0,
                    'min' => 0,
                    'max' => 7,
                ],
                ['key' => 'splitHideTagline', 'label' => 'Hide Tagline In Split Layout', 'type' => 'boolean', 'default' => true],
                ['key' => 'splitHideCta', 'label' => 'Hide CTA In Split Layout', 'type' => 'boolean', 'default' => true],
                ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'default' => 'Modern local site builder'],
                ['key' => 'sticky', 'label' => 'Sticky', 'type' => 'boolean', 'default' => true],
                ['key' => 'ctaLabel', 'label' => 'CTA Label', 'type' => 'text', 'default' => 'Get Started'],
                ['key' => 'ctaUrl', 'label' => 'CTA URL', 'type' => 'url', 'default' => '#contact'],
                [
                    'key' => 'links',
                    'label' => 'Links',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 8,
                    'fields' => [
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Menu'],
                        ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => '#'],
                    ],
                    'default' => [
                        ['label' => 'Features', 'url' => '#features'],
                        ['label' => 'Pricing', 'url' => '#pricing'],
                        ['label' => 'Contact', 'url' => '#contact'],
                    ],
                ],
            ],
        ],
        'banner' => [
            'type' => 'banner',
            'name' => 'Banner',
            'category' => 'Layout',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'banner',
            'thumb' => ['layout' => 'announcement'],
            'schema' => [
                [
                    'key' => 'layoutMode',
                    'label' => 'Layout Style',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'caption-stack', 'label' => 'Caption Stack'],
                    ],
                ],
                [
                    'key' => 'motion',
                    'label' => 'Marquee Motion',
                    'type' => 'select',
                    'default' => 'none',
                    'options' => [
                        ['value' => 'none', 'label' => 'None'],
                        ['value' => 'rtl', 'label' => 'Right to Left'],
                        ['value' => 'ltr', 'label' => 'Left to Right'],
                    ],
                ],
                ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'New'],
                ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'default' => 'Now shipping your pages in minutes.'],
                [
                    'key' => 'items',
                    'label' => 'Extra Links',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 6,
                    'fields' => [
                        ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'default' => 'Learn more'],
                        ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => '#'],
                    ],
                    'default' => [],
                ],
                ['key' => 'url', 'label' => 'Link URL', 'type' => 'url', 'default' => '#'],
                ['key' => 'dismissible', 'label' => 'Dismissible', 'type' => 'boolean', 'default' => false],
            ],
        ],
        'hero' => [
            'type' => 'hero',
            'name' => 'Hero',
            'category' => 'Intro',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'hero',
            'thumb' => ['layout' => 'hero'],
            'schema' => [
                [
                    'key' => 'layoutMode',
                    'label' => 'Layout Style',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'title-overlay', 'label' => 'Title Overlay'],
                    ],
                ],
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'Design pages by stacking blocks.'],
                ['key' => 'subheading', 'label' => 'Subheading', 'type' => 'textarea', 'default' => 'Pick a section, tune content, preview instantly, and export one JSON file.'],
                ['key' => 'scrollCue', 'label' => 'Scroll Cue', 'type' => 'text', 'default' => ''],
                ['key' => 'ctaLabel', 'label' => 'Primary Button', 'type' => 'text', 'default' => 'Start Building'],
                ['key' => 'ctaUrl', 'label' => 'Primary URL', 'type' => 'url', 'default' => '#'],
                ['key' => 'secondaryLabel', 'label' => 'Secondary Button', 'type' => 'text', 'default' => 'See Demo'],
                ['key' => 'secondaryUrl', 'label' => 'Secondary URL', 'type' => 'url', 'default' => '#'],
                [
                    'key' => 'mediaAlign',
                    'label' => 'Card Side',
                    'type' => 'select',
                    'default' => 'auto',
                    'options' => [
                        ['value' => 'auto', 'label' => 'Auto'],
                        ['value' => 'left', 'label' => 'Card left'],
                        ['value' => 'right', 'label' => 'Card right'],
                    ],
                ],
                [
                    'key' => 'copyInsetX',
                    'label' => 'Copy Horizontal Inset',
                    'type' => 'number',
                    'control' => 'slider',
                    'default' => 0,
                    'min' => 0,
                    'max' => 100,
                ],
                [
                    'key' => 'heroVerticalMargin',
                    'label' => 'Vertical Margin',
                    'type' => 'number',
                    'control' => 'slider',
                    'default' => 0,
                    'min' => 0,
                    'max' => 160,
                ],
                [
                    'key' => 'imageMode',
                    'label' => 'Image Source',
                    'type' => 'select',
                    'default' => 'themeRandom',
                    'options' => [
                        ['value' => 'manual', 'label' => 'Manual URL'],
                        ['value' => 'themeRandom', 'label' => 'Theme Random'],
                        ['value' => 'listRandom', 'label' => 'List Random'],
                    ],
                ],
                [
                    'key' => 'imageFit',
                    'label' => 'Image Fit',
                    'type' => 'select',
                    'default' => 'cover',
                    'options' => [
                        ['value' => 'cover', 'label' => 'Cover'],
                        ['value' => 'contain', 'label' => 'Contain'],
                        ['value' => 'fill', 'label' => 'Fill'],
                        ['value' => 'none', 'label' => 'None'],
                        ['value' => 'scale-down', 'label' => 'Scale Down'],
                    ],
                ],
                [
                    'key' => 'imagePosition',
                    'label' => 'Image Position',
                    'type' => 'select',
                    'default' => 'center',
                    'options' => [
                        ['value' => 'center', 'label' => 'Center'],
                        ['value' => 'top', 'label' => 'Top'],
                        ['value' => 'bottom', 'label' => 'Bottom'],
                        ['value' => 'left', 'label' => 'Left'],
                        ['value' => 'right', 'label' => 'Right'],
                        ['value' => 'top-left', 'label' => 'Top Left'],
                        ['value' => 'top-right', 'label' => 'Top Right'],
                        ['value' => 'bottom-left', 'label' => 'Bottom Left'],
                        ['value' => 'bottom-right', 'label' => 'Bottom Right'],
                    ],
                ],
                ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'parallax', 'label' => 'Parallax Image', 'type' => 'boolean', 'default' => false],
                [
                    'key' => 'images',
                    'label' => 'Slideshow Images',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 8,
                    'fields' => [
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                        [
                            'key' => 'imageAlign',
                            'label' => 'Image Align',
                            'type' => 'select',
                            'default' => 'center',
                            'options' => [
                                ['value' => 'left', 'label' => 'Left'],
                                ['value' => 'center', 'label' => 'Center'],
                                ['value' => 'right', 'label' => 'Right'],
                            ],
                        ],
                        [
                            'key' => 'imagePosition',
                            'label' => 'Image Position',
                            'type' => 'select',
                            'default' => 'center',
                            'options' => [
                                ['value' => 'center', 'label' => 'Center'],
                                ['value' => 'top', 'label' => 'Top'],
                                ['value' => 'bottom', 'label' => 'Bottom'],
                                ['value' => 'left', 'label' => 'Left'],
                                ['value' => 'right', 'label' => 'Right'],
                                ['value' => 'top-left', 'label' => 'Top Left'],
                                ['value' => 'top-right', 'label' => 'Top Right'],
                                ['value' => 'bottom-left', 'label' => 'Bottom Left'],
                                ['value' => 'bottom-right', 'label' => 'Bottom Right'],
                            ],
                        ],
                        [
                            'key' => 'imageWidthPercent',
                            'label' => 'Image Fill Width',
                            'type' => 'number',
                            'control' => 'slider',
                            'default' => 100,
                            'min' => 0,
                            'max' => 100,
                        ],
                        ['key' => 'alt', 'label' => 'Alt Text', 'type' => 'text', 'default' => 'Hero image'],
                    ],
                    'default' => [],
                ],
                [
                    'key' => 'bullets',
                    'label' => 'Bullet Items',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 6,
                    'fields' => [
                        ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'default' => 'Bullet point'],
                    ],
                    'default' => [
                        ['text' => 'No Node or build tooling required'],
                        ['text' => 'Single JSON export/import workflow'],
                        ['text' => 'Theme-aware typography and colors'],
                    ],
                ],
            ],
        ],
        'divider' => [
            'type' => 'divider',
            'name' => 'Divider',
            'category' => 'Layout',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'divider',
            'thumb' => ['layout' => 'divider'],
            'schema' => [
                [
                    'key' => 'style',
                    'label' => 'Style',
                    'type' => 'select',
                    'default' => 'line',
                    'options' => [
                        ['value' => 'line', 'label' => 'Line'],
                        ['value' => 'icon', 'label' => 'Icon'],
                        ['value' => 'label', 'label' => 'Label'],
                        ['value' => 'spacer', 'label' => 'Spacer'],
                        ['value' => 'wave', 'label' => 'Wave'],
                    ],
                ],
                ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Continue'],
                ['key' => 'icon', 'label' => 'Icon', 'type' => 'text', 'default' => '*'],
                ['key' => 'thickness', 'label' => 'Thickness', 'type' => 'number', 'default' => 1, 'min' => 1, 'max' => 8],
                ['key' => 'spacing', 'label' => 'Spacing (px)', 'type' => 'number', 'default' => 40, 'min' => 0, 'max' => 160],
            ],
        ],
        'truststrip' => [
            'type' => 'truststrip',
            'name' => 'Trust Strip',
            'category' => 'Social Proof',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'truststrip',
            'thumb' => ['layout' => 'logos'],
            'schema' => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'Trusted by teams worldwide'],
                ['key' => 'ratingLabel', 'label' => 'Rating Label', 'type' => 'text', 'default' => '4.9 average rating'],
                [
                    'key' => 'layoutMode',
                    'label' => 'Layout Style',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'press-bar', 'label' => 'Press Bar'],
                    ],
                ],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'number', 'control' => 'slider', 'default' => 4, 'min' => 1, 'max' => 6],
                [
                    'key' => 'logos',
                    'label' => 'Logos',
                    'type' => 'repeater',
                    'min' => 3,
                    'max' => 10,
                    'fields' => [
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'default' => 'Company'],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'url', 'label' => 'Target URL', 'type' => 'url', 'default' => '#'],
                    ],
                    'default' => [
                        ['name' => 'Northwind', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => '', 'url' => '#'],
                        ['name' => 'Skyline', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => '', 'url' => '#'],
                        ['name' => 'Monolith', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => '', 'url' => '#'],
                        ['name' => 'Atlas', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => '', 'url' => '#'],
                    ],
                ],
            ],
        ],
        'features' => [
            'type' => 'features',
            'name' => 'Features',
            'category' => 'Content',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'features',
            'thumb' => ['layout' => 'feature-grid'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Feature highlights'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => 'Give visitors the fastest path to understanding your value.'],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'number', 'control' => 'slider', 'default' => 3, 'min' => 1, 'max' => 6],
                ['key' => 'parallax', 'label' => 'Parallax Images', 'type' => 'boolean', 'default' => false],
                [
                    'key' => 'cards',
                    'label' => 'Feature Cards',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 8,
                    'fields' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Feature'],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Description'],
                        ['key' => 'icon', 'label' => 'Icon', 'type' => 'text', 'default' => '*'],
                        ['key' => 'linkUrl', 'label' => 'Link URL', 'type' => 'url', 'default' => '#'],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [
                        ['title' => 'Schema-driven', 'text' => 'Inspector forms are generated from strict field schemas.', 'icon' => 'CFG', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['title' => 'Server rendered', 'text' => 'PHP partials are the source of truth for output.', 'icon' => 'BLK', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['title' => 'Portable JSON', 'text' => 'Themes and content travel together in one file.', 'icon' => 'JSON', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                    ],
                ],
            ],
        ],
        'services' => [
            'type' => 'services',
            'name' => 'Services',
            'category' => 'Content',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'services',
            'thumb' => ['layout' => 'service-cards'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Services'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => 'Show what you offer and how people engage with you.'],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'number', 'control' => 'slider', 'default' => 2, 'min' => 1, 'max' => 6],
                [
                    'key' => 'items',
                    'label' => 'Service Items',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 8,
                    'fields' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Service'],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Service details'],
                        ['key' => 'price', 'label' => 'Price', 'type' => 'text', 'default' => '$199'],
                        ['key' => 'icon', 'label' => 'Icon', 'type' => 'text', 'default' => 'OK'],
                        ['key' => 'linkUrl', 'label' => 'Link URL', 'type' => 'url', 'default' => '#'],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [
                        ['title' => 'Landing page setup', 'text' => 'Full page configuration with reusable blocks.', 'price' => '$399', 'icon' => 'OK', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['title' => 'Theme adaptation', 'text' => 'Map your existing theme tokens to the builder.', 'price' => '$249', 'icon' => 'OK', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['title' => 'Content polish', 'text' => 'Improve messaging in each section.', 'price' => '$149', 'icon' => 'OK', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                    ],
                ],
            ],
        ],
        'cards' => [
            'type' => 'cards',
            'name' => 'Cards',
            'category' => 'Content',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'cards',
            'thumb' => ['layout' => 'cards-grid'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Card collection'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => 'Flexible card grid for products, posts, or case studies.'],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'number', 'control' => 'slider', 'default' => 3, 'min' => 1, 'max' => 6],
                [
                    'key' => 'cards',
                    'label' => 'Cards',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 12,
                    'fields' => [
                        ['key' => 'badge', 'label' => 'Badge', 'type' => 'text', 'default' => 'New'],
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Card title'],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Card summary text'],
                        ['key' => 'linkUrl', 'label' => 'Link URL', 'type' => 'url', 'default' => '#'],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [
                        ['badge' => 'Guide', 'title' => 'Launch checklist', 'text' => 'A practical list for production readiness.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'Case Study', 'title' => 'Conversion uplift', 'text' => 'How clearer blocks improved outcomes.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'Template', 'title' => 'B2B landing', 'text' => 'A clean structure for enterprise offers.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'Playbook', 'title' => 'Growth campaign', 'text' => 'Cross-channel launch plan with measurable milestones.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'UX', 'title' => 'Onboarding flow', 'text' => 'Reduce friction in the first five user actions.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'Toolkit', 'title' => 'Brand components', 'text' => 'Reusable visual patterns for consistent pages.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'Release', 'title' => 'Changelog notes', 'text' => 'Highlight what changed this month.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'Story', 'title' => 'Customer quote', 'text' => 'Share a quick testimonial and impact.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['badge' => 'Guide', 'title' => 'Implementation steps', 'text' => 'A short checklist for rollout teams.', 'linkUrl' => '#', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                    ],
                ],
            ],
        ],
        'stats' => [
            'type' => 'stats',
            'name' => 'Stats',
            'category' => 'Social Proof',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'stats',
            'thumb' => ['layout' => 'stats-row'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'By the numbers'],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'number', 'control' => 'slider', 'default' => 4, 'min' => 1, 'max' => 6],
                ['key' => 'animate', 'label' => 'Animate Numbers', 'type' => 'boolean', 'default' => true],
                [
                    'key' => 'trigger',
                    'label' => 'Animation Trigger',
                    'type' => 'select',
                    'default' => 'onView',
                    'options' => [
                        ['value' => 'onView', 'label' => 'On View'],
                        ['value' => 'onLoad', 'label' => 'On Load'],
                    ],
                ],
                [
                    'key' => 'stats',
                    'label' => 'Stats',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 8,
                    'fields' => [
                        ['key' => 'value', 'label' => 'Value', 'type' => 'number', 'default' => 120],
                        ['key' => 'suffix', 'label' => 'Suffix', 'type' => 'text', 'default' => '%'],
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Metric'],
                    ],
                    'default' => [
                        ['value' => 92, 'suffix' => '%', 'label' => 'Faster setup time'],
                        ['value' => 40, 'suffix' => 'min', 'label' => 'Average first publish'],
                        ['value' => 12, 'suffix' => 'x', 'label' => 'Reusable sections'],
                        ['value' => 1, 'suffix' => ' file', 'label' => 'JSON export'],
                    ],
                ],
            ],
        ],
        'gallery' => [
            'type' => 'gallery',
            'name' => 'Gallery',
            'category' => 'Media',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'gallery',
            'thumb' => ['layout' => 'gallery-grid'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Gallery'],
                [
                    'key' => 'layoutMode',
                    'label' => 'Layout Style',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'editorial-collage', 'label' => 'Editorial Collage'],
                        ['value' => 'category-cards', 'label' => 'Category Cards'],
                        ['value' => 'social-grid', 'label' => 'Social Grid'],
                    ],
                ],
                ['key' => 'introText', 'label' => 'Intro Text', 'type' => 'text', 'default' => ''],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'number', 'control' => 'slider', 'default' => 3, 'min' => 2, 'max' => 5],
                ['key' => 'lightbox', 'label' => 'Lightbox', 'type' => 'boolean', 'default' => false],
                [
                    'key' => 'links',
                    'label' => 'Overlay Links',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 6,
                    'fields' => [
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Link'],
                        ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => '#'],
                    ],
                    'default' => [],
                ],
                [
                    'key' => 'decorImages',
                    'label' => 'Decor Images',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 4,
                    'fields' => [
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [],
                ],
                [
                    'key' => 'images',
                    'label' => 'Images',
                    'type' => 'repeater',
                    'min' => 3,
                    'max' => 12,
                    'fields' => [
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'url', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'alt', 'label' => 'Alt Text', 'type' => 'text', 'default' => 'Gallery image'],
                        ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'default' => ''],
                        ['key' => 'caption', 'label' => 'Caption', 'type' => 'text', 'default' => ''],
                    ],
                    'default' => [
                        ['imageMode' => 'themeRandom', 'url' => '', 'resolvedImageUrl' => '', 'alt' => 'Theme image', 'caption' => 'From current theme image set'],
                        ['imageMode' => 'manual', 'url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => '', 'alt' => 'Code workspace', 'caption' => 'Engineering desk setup'],
                        ['imageMode' => 'manual', 'url' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => '', 'alt' => 'Developer keyboard', 'caption' => 'Coding workflow close-up'],
                        ['imageMode' => 'manual', 'url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => '', 'alt' => 'Hardware detail', 'caption' => 'Product detail capture'],
                        ['imageMode' => 'manual', 'url' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => '', 'alt' => 'Team collaboration', 'caption' => 'Studio collaboration moment'],
                        ['imageMode' => 'manual', 'url' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => '', 'alt' => 'Project planning', 'caption' => 'Planning session overview'],
                        ['imageMode' => 'manual', 'url' => 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => '', 'alt' => 'Workspace scene', 'caption' => 'Office environment snapshot'],
                    ],
                ],
            ],
        ],
        'carousel' => [
            'type' => 'carousel',
            'name' => 'Carousel',
            'category' => 'Media',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'carousel',
            'thumb' => ['layout' => 'carousel'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Swipe through highlights'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => ''],
                ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'default' => ''],
                ['key' => 'buttonLabel', 'label' => 'Primary Button', 'type' => 'text', 'default' => ''],
                ['key' => 'buttonUrl', 'label' => 'Primary URL', 'type' => 'url', 'default' => '#'],
                ['key' => 'cardWidth', 'label' => 'Strip Width', 'type' => 'number', 'control' => 'slider', 'default' => 320, 'min' => 100, 'max' => 400, 'step' => 5, 'variants' => [1]],
                ['key' => 'topCardWidth', 'label' => 'Top Strip Width', 'type' => 'number', 'control' => 'slider', 'default' => 230, 'min' => 100, 'max' => 400, 'step' => 5, 'variants' => [2]],
                ['key' => 'bottomCardWidth', 'label' => 'Bottom Strip Width', 'type' => 'number', 'control' => 'slider', 'default' => 250, 'min' => 100, 'max' => 400, 'step' => 5, 'variants' => [2]],
                ['key' => 'stripGap', 'label' => 'Shared Gap', 'type' => 'number', 'control' => 'slider', 'default' => 14, 'min' => 0, 'max' => 40, 'step' => 1, 'variants' => [2]],
                [
                    'key' => 'mode',
                    'label' => 'Mode',
                    'type' => 'select',
                    'default' => 'cards',
                    'options' => [
                        ['value' => 'cards', 'label' => 'Cards'],
                        ['value' => 'images', 'label' => 'Images'],
                        ['value' => 'testimonials', 'label' => 'Testimonials'],
                        ['value' => 'logos', 'label' => 'Logos'],
                    ],
                ],
                ['key' => 'autoplay', 'label' => 'Autoplay', 'type' => 'boolean', 'default' => true],
                ['key' => 'interval', 'label' => 'Autoplay Delay (ms)', 'type' => 'number', 'default' => 4200, 'min' => 1000, 'max' => 12000],
                ['key' => 'transitionMs', 'label' => 'Slide Transition (ms)', 'type' => 'number', 'default' => 720, 'min' => 200, 'max' => 2200],
                ['key' => 'scrollDriven', 'label' => 'Scroll-driven Motion', 'type' => 'boolean', 'default' => false],
                ['key' => 'showScrollbar', 'label' => 'Show Scrollbar', 'type' => 'boolean', 'default' => true],
                ['key' => 'showButtons', 'label' => 'Show Arrow Buttons', 'type' => 'boolean', 'default' => true],
                [
                    'key' => 'scrollFlow',
                    'label' => 'Scroll Flow',
                    'type' => 'select',
                    'default' => 'ltr',
                    'options' => [
                        ['value' => 'ltr', 'label' => 'Left to Right'],
                        ['value' => 'rtl', 'label' => 'Right to Left'],
                    ],
                ],
                [
                    'key' => 'slides',
                    'label' => 'Slides',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 12,
                    'fields' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Slide title'],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Slide description'],
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Category'],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [
                        ['title' => 'Clarity first', 'text' => 'Focused sections keep editing fast.', 'label' => 'Process', 'imageMode' => 'themeRandom', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['title' => 'Reusable tokens', 'text' => 'Theme tokens drive each block style.', 'label' => 'Theme', 'imageMode' => 'manual', 'imageUrl' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => ''],
                        ['title' => 'Portable content', 'text' => 'Import and export full page JSON.', 'label' => 'Data', 'imageMode' => 'manual', 'imageUrl' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => ''],
                        ['title' => 'Always local', 'text' => 'Runs on plain PHP with no build pipeline.', 'label' => 'Local', 'imageMode' => 'manual', 'imageUrl' => 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => ''],
                        ['title' => 'Faster iteration', 'text' => 'Scroll and validate content changes instantly.', 'label' => 'Speed', 'imageMode' => 'manual', 'imageUrl' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => ''],
                        ['title' => 'Visual consistency', 'text' => 'Design tokens keep every section aligned.', 'label' => 'Design', 'imageMode' => 'manual', 'imageUrl' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => ''],
                        ['title' => 'Easy publishing', 'text' => 'Export one JSON and deploy anywhere.', 'label' => 'Delivery', 'imageMode' => 'manual', 'imageUrl' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=600&h=400&q=80', 'resolvedImageUrl' => ''],
                    ],
                ],
                [
                    'key' => 'secondarySlides',
                    'label' => 'Secondary Slides',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 12,
                    'fields' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Slide title'],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Slide description'],
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Category'],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [],
                ],
            ],
        ],
        'datepicker' => [
            'type' => 'datepicker',
            'name' => 'Date Picker',
            'category' => 'Utility',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'datepicker',
            'thumb' => ['layout' => 'calendar'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Plan your upcoming dates'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => 'Use a month view and a week overview together so visitors can pick a slot quickly.'],
                ['key' => 'monthStart', 'label' => 'Month Start (YYYY-MM-DD)', 'type' => 'text', 'default' => '2026-04-01'],
                ['key' => 'selectedDate', 'label' => 'Selected Date (YYYY-MM-DD)', 'type' => 'text', 'default' => '2026-04-16'],
                [
                    'key' => 'viewMode',
                    'label' => 'View Mode',
                    'type' => 'select',
                    'default' => 'both',
                    'options' => [
                        ['value' => 'month', 'label' => 'Month'],
                        ['value' => 'weeks', 'label' => 'Upcoming Weeks'],
                        ['value' => 'both', 'label' => 'Both'],
                    ],
                ],
                ['key' => 'buttonLabel', 'label' => 'Button Label', 'type' => 'text', 'default' => 'Book selected date'],
                ['key' => 'buttonUrl', 'label' => 'Button URL', 'type' => 'url', 'default' => '#contact'],
                [
                    'key' => 'events',
                    'label' => 'Events',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 20,
                    'fields' => [
                        ['key' => 'date', 'label' => 'Date (YYYY-MM-DD)', 'type' => 'text', 'default' => '2026-04-16'],
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Planning call'],
                        ['key' => 'time', 'label' => 'Time', 'type' => 'text', 'default' => '09:30'],
                        ['key' => 'location', 'label' => 'Location', 'type' => 'text', 'default' => 'Remote'],
                        ['key' => 'icon', 'label' => 'Icon', 'type' => 'text', 'default' => 'pi-calendar'],
                    ],
                    'default' => [
                        ['date' => '2026-04-03', 'title' => 'Demo with Berlin team', 'time' => '10:00', 'location' => 'Berlin', 'icon' => 'pi-calendar'],
                        ['date' => '2026-04-10', 'title' => 'Product workshop', 'time' => '14:00', 'location' => 'Amsterdam', 'icon' => 'pi-clock'],
                        ['date' => '2026-04-16', 'title' => 'Launch review', 'time' => '09:30', 'location' => 'Prague', 'icon' => 'pi-star'],
                        ['date' => '2026-04-22', 'title' => 'Customer interview', 'time' => '11:30', 'location' => 'Remote', 'icon' => 'pi-contact'],
                        ['date' => '2026-04-29', 'title' => 'Quarter planning', 'time' => '15:00', 'location' => 'Vienna', 'icon' => 'pi-calendar'],
                    ],
                ],
            ],
        ],
        'faq' => [
            'type' => 'faq',
            'name' => 'FAQ',
            'category' => 'Content',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'faq',
            'thumb' => ['layout' => 'faq'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Frequently asked questions'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => 'Answer common questions before users need to reach out.'],
                ['key' => 'openFirst', 'label' => 'Open First Item', 'type' => 'boolean', 'default' => true],
                ['key' => 'supportLabel', 'label' => 'Support Label', 'type' => 'text', 'default' => 'Need more help? Contact support'],
                ['key' => 'supportUrl', 'label' => 'Support URL', 'type' => 'url', 'default' => '#contact'],
                [
                    'key' => 'items',
                    'label' => 'Questions',
                    'type' => 'repeater',
                    'min' => 3,
                    'max' => 14,
                    'fields' => [
                        ['key' => 'question', 'label' => 'Question', 'type' => 'text', 'default' => 'Question title'],
                        ['key' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'default' => 'Answer text'],
                        ['key' => 'icon', 'label' => 'Icon', 'type' => 'text', 'default' => 'pi-question'],
                    ],
                    'default' => [
                        ['question' => 'Can I import only a theme JSON?', 'answer' => 'Yes. Importing a theme creates a starter kit if no page is present.', 'icon' => 'pi-question'],
                        ['question' => 'Does this require Node or a build step?', 'answer' => 'No. The project runs directly with PHP and vanilla JavaScript.', 'icon' => 'pi-checkmark'],
                        ['question' => 'Can I export a single portable file?', 'answer' => 'Yes. Export Kit downloads one JSON with theme, blocks, and content.', 'icon' => 'pi-download'],
                        ['question' => 'Can I reorder blocks from the canvas?', 'answer' => 'Yes. Select and drag blocks directly in the canvas, or use move actions.', 'icon' => 'pi-rightwardsarrow'],
                    ],
                ],
            ],
        ],
        'location' => [
            'type' => 'location',
            'name' => 'Location Map',
            'category' => 'Conversion',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'location',
            'thumb' => ['layout' => 'map'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Our locations in Europe'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => 'Use map + address legend layouts with left or right emphasis.'],
                ['key' => 'mapUrl', 'label' => 'OpenStreetMap Embed URL', 'type' => 'url', 'default' => ''],
                ['key' => 'mapLink', 'label' => 'Open Map Link', 'type' => 'url', 'default' => ''],
                [
                    'key' => 'legendPosition',
                    'label' => 'Legend Side',
                    'type' => 'select',
                    'default' => 'right',
                    'options' => [
                        ['value' => 'left', 'label' => 'Left'],
                        ['value' => 'right', 'label' => 'Right'],
                    ],
                ],
                [
                    'key' => 'locations',
                    'label' => 'Addresses',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 2,
                    'fields' => [
                        ['key' => 'label', 'label' => 'Location Label', 'type' => 'text', 'default' => ''],
                        ['key' => 'address', 'label' => 'Address', 'type' => 'textarea', 'default' => ''],
                        ['key' => 'note', 'label' => 'Note', 'type' => 'text', 'default' => ''],
                        ['key' => 'icon', 'label' => 'Icon', 'type' => 'text', 'default' => 'pi-local'],
                    ],
                    'default' => [
                        ['label' => '', 'address' => '', 'note' => '', 'icon' => 'pi-local'],
                        ['label' => '', 'address' => '', 'note' => '', 'icon' => 'pi-map'],
                    ],
                ],
            ],
        ],
        'testimonials' => [
            'type' => 'testimonials',
            'name' => 'Testimonials',
            'category' => 'Social Proof',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'testimonials',
            'thumb' => ['layout' => 'quotes'],
            'schema' => [
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'What clients say'],
                ['key' => 'sectionSubtitle', 'label' => 'Section Subtitle', 'type' => 'textarea', 'default' => 'Human proof builds trust faster than feature lists.'],
                [
                    'key' => 'layoutMode',
                    'label' => 'Layout Style',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'review-stage', 'label' => 'Review Stage'],
                        ['value' => 'review-stage-thumbnails', 'label' => 'Review Stage Thumbnails'],
                    ],
                ],
                ['key' => 'asideLabel', 'label' => 'Aside Label', 'type' => 'text', 'default' => ''],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'number', 'control' => 'slider', 'default' => 3, 'min' => 1, 'max' => 6],
                [
                    'key' => 'thumbnails',
                    'label' => 'Thumbnail Images',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 8,
                    'fields' => [
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'alt', 'label' => 'Alt Text', 'type' => 'text', 'default' => 'Thumbnail image'],
                    ],
                    'default' => [],
                ],
                [
                    'key' => 'items',
                    'label' => 'Testimonials',
                    'type' => 'repeater',
                    'min' => 2,
                    'max' => 10,
                    'fields' => [
                        ['key' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'default' => 'Great experience from start to finish.'],
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'default' => 'Jane Doe'],
                        ['key' => 'role', 'label' => 'Role', 'type' => 'text', 'default' => 'Marketing Director'],
                        ['key' => 'rating', 'label' => 'Rating (1-5)', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 5],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Avatar URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Avatar URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [
                        ['quote' => 'We launched in days instead of weeks.', 'name' => 'Leah Cole', 'role' => 'Founder', 'rating' => 5, 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['quote' => 'The JSON workflow made handoff painless.', 'name' => 'Sam Ortiz', 'role' => 'Product Lead', 'rating' => 5, 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['quote' => 'Great balance of control and simplicity.', 'name' => 'Nora White', 'role' => 'Designer', 'rating' => 4, 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                    ],
                ],
            ],
        ],
        'content' => [
            'type' => 'content',
            'name' => 'Content Split',
            'category' => 'Content',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'content',
            'thumb' => ['layout' => 'split'],
            'schema' => [
                [
                    'key' => 'layoutMode',
                    'label' => 'Layout Style',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'triptych-link-stack', 'label' => 'Triptych Link Stack'],
                    ],
                ],
                ['key' => 'sectionTitle', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Content section'],
                ['key' => 'sectionTitleSecondary', 'label' => 'Section Title Secondary', 'type' => 'text', 'default' => ''],
                ['key' => 'columnOneText', 'label' => 'Column One Text', 'type' => 'textarea', 'default' => ''],
                ['key' => 'columnTwoText', 'label' => 'Column Two Text', 'type' => 'textarea', 'default' => ''],
                ['key' => 'sideLabel', 'label' => 'Side Label', 'type' => 'text', 'default' => ''],
                [
                    'key' => 'imageColumn',
                    'label' => 'Image Column',
                    'type' => 'select',
                    'default' => '3',
                    'options' => [
                        ['value' => '1', 'label' => 'Column 1'],
                        ['value' => '2', 'label' => 'Column 2'],
                        ['value' => '3', 'label' => 'Column 3'],
                    ],
                ],
                [
                    'key' => 'imageMode',
                    'label' => 'Main Image Source',
                    'type' => 'select',
                    'default' => 'manual',
                    'options' => [
                        ['value' => 'manual', 'label' => 'Manual URL'],
                        ['value' => 'themeRandom', 'label' => 'Theme Random'],
                        ['value' => 'listRandom', 'label' => 'List Random'],
                    ],
                ],
                ['key' => 'imageUrl', 'label' => 'Main Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'resolvedImageUrl', 'label' => 'Resolved Main Image URL', 'type' => 'url', 'default' => ''],
                [
                    'key' => 'secondaryImageMode',
                    'label' => 'Secondary Image Source',
                    'type' => 'select',
                    'default' => 'manual',
                    'options' => [
                        ['value' => 'manual', 'label' => 'Manual URL'],
                        ['value' => 'themeRandom', 'label' => 'Theme Random'],
                        ['value' => 'listRandom', 'label' => 'List Random'],
                    ],
                ],
                ['key' => 'secondaryImageUrl', 'label' => 'Secondary Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'secondaryResolvedImageUrl', 'label' => 'Resolved Secondary Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'decorImageUrl', 'label' => 'Decor Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'decorResolvedImageUrl', 'label' => 'Resolved Decor Image URL', 'type' => 'url', 'default' => ''],
                [
                    'key' => 'items',
                    'label' => 'Rows',
                    'type' => 'repeater',
                    'min' => 1,
                    'max' => 8,
                    'fields' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Section item'],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Short explanatory copy for this row.'],
                        ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => '#'],
                        ['key' => 'align', 'label' => 'Image Side', 'type' => 'select', 'default' => 'right', 'options' => [
                            ['value' => 'left', 'label' => 'Left'],
                            ['value' => 'right', 'label' => 'Right'],
                        ]],
                        [
                            'key' => 'imageMode',
                            'label' => 'Image Source',
                            'type' => 'select',
                            'default' => 'manual',
                            'options' => [
                                ['value' => 'manual', 'label' => 'Manual URL'],
                                ['value' => 'themeRandom', 'label' => 'Theme Random'],
                                ['value' => 'listRandom', 'label' => 'List Random'],
                            ],
                        ],
                        ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                        ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [
                        ['title' => 'Keep content structured', 'text' => 'Each item uses strict fields for consistency.', 'align' => 'right', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                        ['title' => 'Alternate rhythm', 'text' => 'Switch image alignment for visual variety.', 'align' => 'left', 'imageMode' => 'manual', 'imageUrl' => '', 'resolvedImageUrl' => ''],
                    ],
                ],
            ],
        ],
        'cta' => [
            'type' => 'cta',
            'name' => 'CTA',
            'category' => 'Conversion',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'cta',
            'thumb' => ['layout' => 'cta'],
            'schema' => [
                [
                    'key' => 'layoutMode',
                    'label' => 'Layout Style',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        ['value' => 'default', 'label' => 'Default'],
                        ['value' => 'newsletter-split', 'label' => 'Newsletter Split'],
                        ['value' => 'overlay-card', 'label' => 'Overlay Card'],
                    ],
                ],
                ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'default' => ''],
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'Ready to publish your page?'],
                ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Keep the stack simple, update copy quickly, and ship with confidence.'],
                [
                    'key' => 'imageMode',
                    'label' => 'Image Source',
                    'type' => 'select',
                    'default' => 'manual',
                    'options' => [
                        ['value' => 'manual', 'label' => 'Manual URL'],
                        ['value' => 'themeRandom', 'label' => 'Theme Random'],
                        ['value' => 'listRandom', 'label' => 'List Random'],
                    ],
                ],
                ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'buttonLabel', 'label' => 'Primary Button', 'type' => 'text', 'default' => 'Book a demo'],
                ['key' => 'buttonUrl', 'label' => 'Primary URL', 'type' => 'url', 'default' => '#contact'],
                ['key' => 'secondaryLabel', 'label' => 'Secondary Button', 'type' => 'text', 'default' => 'View docs'],
                ['key' => 'secondaryUrl', 'label' => 'Secondary URL', 'type' => 'url', 'default' => '#'],
            ],
        ],
        'contact' => [
            'type' => 'contact',
            'name' => 'Contact',
            'category' => 'Conversion',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'contact',
            'thumb' => ['layout' => 'contact'],
            'schema' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'Let\'s talk'],
                ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Share your goals and we will follow up quickly.'],
                [
                    'key' => 'imageMode',
                    'label' => 'Image Source',
                    'type' => 'select',
                    'default' => 'manual',
                    'options' => [
                        ['value' => 'manual', 'label' => 'Manual URL'],
                        ['value' => 'themeRandom', 'label' => 'Theme Random'],
                        ['value' => 'listRandom', 'label' => 'List Random'],
                    ],
                ],
                ['key' => 'imageUrl', 'label' => 'Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'resolvedImageUrl', 'label' => 'Resolved Image URL', 'type' => 'url', 'default' => ''],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'default' => ''],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'default' => ''],
                ['key' => 'address', 'label' => 'Address', 'type' => 'textarea', 'default' => ''],
                ['key' => 'formEnabled', 'label' => 'Show Form', 'type' => 'boolean', 'default' => true],
                ['key' => 'buttonLabel', 'label' => 'Form Button', 'type' => 'text', 'default' => 'Send message'],
                ['key' => 'mapUrl', 'label' => 'Map URL', 'type' => 'url', 'default' => ''],
            ],
        ],
        'socialbar' => [
            'type' => 'socialbar',
            'name' => 'Social Media Bar',
            'category' => 'Social Proof',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'socialbar',
            'thumb' => ['layout' => 'social-bar'],
            'schema' => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'Follow us'],
                ['key' => 'subheading', 'label' => 'Subheading', 'type' => 'text', 'default' => 'Stay connected through our latest updates.'],
                [
                    'key' => 'socials',
                    'label' => 'Social Links',
                    'type' => 'repeater',
                    'min' => 1,
                    'max' => 10,
                    'fields' => [
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'X'],
                        ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => '#'],
                    ],
                    'default' => [
                        ['label' => 'X', 'url' => '#'],
                        ['label' => 'LinkedIn', 'url' => '#'],
                        ['label' => 'Instagram', 'url' => '#'],
                        ['label' => 'YouTube', 'url' => '#'],
                        ['label' => 'WhatsApp', 'url' => '#'],
                    ],
                ],
            ],
        ],
        'footer' => [
            'type' => 'footer',
            'name' => 'Footer',
            'category' => 'Layout',
            'variants' => [1, 2, 3, 4, 5, 6, 7, 8],
            'partial' => 'footer',
            'thumb' => ['layout' => 'footer'],
            'schema' => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => ''],
                ['key' => 'brand', 'label' => 'Brand', 'type' => 'text', 'default' => 'SiteKit'],
                ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'default' => 'Built with reusable blocks'],
                ['key' => 'address', 'label' => 'Address', 'type' => 'textarea', 'default' => ''],
                ['key' => 'contactLine', 'label' => 'Contact / Details', 'type' => 'text', 'default' => ''],
                ['key' => 'blurb', 'label' => 'Blurb', 'type' => 'textarea', 'default' => 'Simple local website composition with reusable blocks.'],
                ['key' => 'legalLine', 'label' => 'Legal Note', 'type' => 'text', 'default' => 'Privacy-friendly, accessible, and locally exported.'],
                [
                    'key' => 'creditsSegments',
                    'label' => 'Credits Segments',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 12,
                    'fields' => [
                        ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'default' => 'Credit'],
                        ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => ''],
                    ],
                    'default' => [],
                ],
                ['key' => 'ctaLabel', 'label' => 'CTA Label', 'type' => 'text', 'default' => 'Contact'],
                ['key' => 'ctaUrl', 'label' => 'CTA URL', 'type' => 'url', 'default' => '#contact'],
                [
                    'key' => 'links',
                    'label' => 'Links',
                    'type' => 'repeater',
                    'min' => 0,
                    'max' => 8,
                    'fields' => [
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Menu'],
                        ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => '#'],
                    ],
                    'default' => [],
                ],
                [
                    'key' => 'columns',
                    'label' => 'Columns (Legacy)',
                    'type' => 'repeater',
                    'min' => 1,
                    'max' => 5,
                    'fields' => [
                        ['key' => 'title', 'label' => 'Column Title', 'type' => 'text', 'default' => 'Resources'],
                        [
                            'key' => 'links',
                            'label' => 'Links',
                            'type' => 'repeater',
                            'min' => 1,
                            'max' => 8,
                            'fields' => [
                                ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Link'],
                                ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => '#'],
                            ],
                            'default' => [
                                ['label' => 'Overview', 'url' => '#'],
                                ['label' => 'Docs', 'url' => '#'],
                            ],
                        ],
                    ],
                    'default' => [
                        [
                            'title' => 'Product',
                            'links' => [
                                ['label' => 'Features', 'url' => '#features'],
                                ['label' => 'Pricing', 'url' => '#pricing'],
                            ],
                        ],
                        [
                            'title' => 'Company',
                            'links' => [
                                ['label' => 'About', 'url' => '#'],
                                ['label' => 'Contact', 'url' => '#contact'],
                            ],
                        ],
                    ],
                ],
                ['key' => 'copyright', 'label' => 'Copyright', 'type' => 'text', 'default' => '(c) 2026 SiteKit. All rights reserved.'],
            ],
        ],
    ];

    $headingFieldMap = app_block_heading_field_map();
    foreach ($registry as $type => $definition) {
        if (isset($headingFieldMap[$type])) {
            $config = is_array($headingFieldMap[$type]) ? $headingFieldMap[$type] : [];
            $schema = is_array($registry[$type]['schema'] ?? null) ? $registry[$type]['schema'] : [];
            $registry[$type]['schema'] = app_schema_insert_after_key(
                $schema,
                (string) ($config['after'] ?? 'heading'),
                app_heading_level_schema_field((string) ($config['default'] ?? 'h2'))
            );
        }
        $registry[$type]['defaults'] = app_schema_defaults($registry[$type]['schema']);
        if (isset($definition['defaults']) && is_array($definition['defaults'])) {
            $registry[$type]['defaults'] = app_merge_block_data($registry[$type]['defaults'], $definition['defaults']);
        }
    }

    $registryByLocale[$locale] = app_localize_data($registry, $locale);
    return $registryByLocale[$locale];
}

function app_schema_defaults(array $schema): array
{
    $values = [];
    foreach ($schema as $field) {
        $key = (string) ($field['key'] ?? '');
        if ($key === '') {
            continue;
        }

        $type = (string) ($field['type'] ?? 'text');
        if ($type === 'repeater') {
            if (array_key_exists('default', $field) && is_array($field['default'])) {
                $values[$key] = $field['default'];
            } else {
                $values[$key] = [];
            }
            continue;
        }

        $values[$key] = $field['default'] ?? '';
    }

    return $values;
}

function app_block_heading_field_map(): array
{
    return [
        'truststrip' => ['after' => 'heading', 'default' => 'h2'],
        'features' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'services' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'cards' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'stats' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'gallery' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'carousel' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'datepicker' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'faq' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'location' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'testimonials' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'content' => ['after' => 'sectionTitle', 'default' => 'h2'],
        'cta' => ['after' => 'heading', 'default' => 'h2'],
        'contact' => ['after' => 'headline', 'default' => 'h2'],
        'socialbar' => ['after' => 'heading', 'default' => 'h3'],
        'footer' => ['after' => 'heading', 'default' => 'h4'],
    ];
}

function app_heading_level_schema_field(string $default = 'h2'): array
{
    $normalizedDefault = strtolower(trim($default));
    if (!in_array($normalizedDefault, ['h2', 'h3', 'h4', 'h5', 'h6'], true)) {
        $normalizedDefault = 'h2';
    }

    return [
        'key' => 'headingLevel',
        'label' => 'Heading Level',
        'type' => 'select',
        'default' => $normalizedDefault,
        'options' => [
            ['value' => 'h2', 'label' => 'H2'],
            ['value' => 'h3', 'label' => 'H3'],
            ['value' => 'h4', 'label' => 'H4'],
            ['value' => 'h5', 'label' => 'H5'],
            ['value' => 'h6', 'label' => 'H6'],
        ],
    ];
}

function app_schema_insert_after_key(array $schema, string $afterKey, array $field): array
{
    $targetKey = trim((string) ($field['key'] ?? ''));
    if ($targetKey === '') {
        return $schema;
    }

    foreach ($schema as $existingField) {
        if ((string) ($existingField['key'] ?? '') === $targetKey) {
            return $schema;
        }
    }

    $result = [];
    $inserted = false;
    foreach ($schema as $entry) {
        $result[] = $entry;
        if ((string) ($entry['key'] ?? '') === $afterKey) {
            $result[] = $field;
            $inserted = true;
        }
    }

    if (!$inserted) {
        $result[] = $field;
    }

    return $result;
}

function app_merge_block_data(array $defaults, array $overrides): array
{
    $result = $defaults;

    foreach ($overrides as $key => $value) {
        if (is_array($value)) {
            if (array_is_list($value)) {
                $result[$key] = $value;
                continue;
            }

            $base = [];
            if (isset($result[$key]) && is_array($result[$key]) && !array_is_list($result[$key])) {
                $base = $result[$key];
            }

            $result[$key] = app_merge_block_data($base, $value);
            continue;
        }

        $result[$key] = $value;
    }

    return $result;
}

function app_block_definition(string $type): ?array
{
    $registry = app_block_registry();
    return $registry[$type] ?? null;
}

function app_is_resolved_image_field_key(string $key): bool
{
    return preg_match('/resolvedimageurl$/i', trim($key)) === 1;
}

function app_block_registry_for_client(): array
{
    $registry = app_block_registry();
    $stripResolvedField = static function (array $schema) use (&$stripResolvedField): array {
        $filtered = [];
        foreach ($schema as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (app_is_resolved_image_field_key((string) ($field['key'] ?? ''))) {
                continue;
            }
            if (isset($field['fields']) && is_array($field['fields'])) {
                $field['fields'] = $stripResolvedField($field['fields']);
            }
            $filtered[] = $field;
        }
        return $filtered;
    };
    $stripResolvedValues = static function ($value) use (&$stripResolvedValues) {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        $next = [];
        foreach ($value as $key => $item) {
            if (!$isList && app_is_resolved_image_field_key((string) $key)) {
                continue;
            }
            $next[$key] = $stripResolvedValues($item);
        }
        return $next;
    };

    foreach ($registry as $type => $definition) {
        if (!is_array($definition)) {
            continue;
        }
        $schema = is_array($definition['schema'] ?? null) ? $definition['schema'] : [];
        $registry[$type]['schema'] = $stripResolvedField($schema);
        if (array_key_exists('defaults', $definition)) {
            $registry[$type]['defaults'] = $stripResolvedValues($definition['defaults']);
        }
    }

    return array_values($registry);
}

function app_strip_runtime_image_fields_deep($value)
{
    if (!is_array($value)) {
        return $value;
    }

    $isList = array_is_list($value);
    $next = [];
    foreach ($value as $key => $item) {
        if (!$isList && app_is_resolved_image_field_key((string) $key)) {
            continue;
        }
        $next[$key] = app_strip_runtime_image_fields_deep($item);
    }

    return $next;
}

function app_merge_structured_value_with_baseline($baseline, $override)
{
    if (is_array($override)) {
        if (array_is_list($override)) {
            $baseArray = (is_array($baseline) && array_is_list($baseline)) ? $baseline : [];
            $items = [];
            foreach ($override as $index => $entry) {
                $items[] = app_merge_structured_value_with_baseline($baseArray[$index] ?? null, $entry);
            }
            return $items;
        }

        $baseObject = (is_array($baseline) && !array_is_list($baseline)) ? $baseline : [];
        $next = $baseObject;
        foreach ($override as $key => $value) {
            $next[$key] = app_merge_structured_value_with_baseline($baseObject[$key] ?? null, $value);
        }
        return $next;
    }

    return $override;
}

function app_blocks_match_baseline_shape(array $rawBlocks, array $baselineBlocks): bool
{
    if (count($rawBlocks) !== count($baselineBlocks)) {
        return false;
    }

    foreach ($rawBlocks as $index => $rawBlock) {
        if (!is_array($rawBlock)) {
            return false;
        }
        $baselineBlock = $baselineBlocks[$index] ?? null;
        if (!is_array($baselineBlock)) {
            return false;
        }

        $rawType = trim((string) ($rawBlock['type'] ?? ''));
        $baselineType = trim((string) ($baselineBlock['type'] ?? ''));
        if ($rawType === '' || $rawType !== $baselineType) {
            return false;
        }

        $rawVariant = isset($rawBlock['variant']) ? (int) $rawBlock['variant'] : (int) ($baselineBlock['variant'] ?? app_default_block_variant($rawType));
        $baselineVariant = isset($baselineBlock['variant']) ? (int) $baselineBlock['variant'] : app_default_block_variant($baselineType);
        if ($rawVariant !== $baselineVariant) {
            return false;
        }
    }

    return true;
}

function app_merge_blocks_with_baseline(array $baselineBlocks, array $rawBlocks): array
{
    if (!app_blocks_match_baseline_shape($rawBlocks, $baselineBlocks)) {
        return $rawBlocks;
    }

    $topLevelKeys = [
        'uid',
        'type',
        'variant',
        'hidden',
        'animation',
        'widthPercent',
        'backgroundColor',
        'backgroundOpacity',
        'cardBackgroundOpacity',
        'foregroundOpacity',
        'verticalPaddingScale',
        'fontScale',
        'cardColor',
        'cardBorderWidth',
        'cardBorderColor',
        'cardBorderStyle',
    ];

    $merged = [];
    foreach ($rawBlocks as $index => $rawBlock) {
        $baselineBlock = is_array($baselineBlocks[$index] ?? null) ? $baselineBlocks[$index] : [];
        $next = $baselineBlock;
        foreach ($topLevelKeys as $key) {
            if (array_key_exists($key, $rawBlock)) {
                $next[$key] = $rawBlock[$key];
            }
        }
        if (isset($rawBlock['data']) && is_array($rawBlock['data'])) {
            $next['data'] = app_merge_structured_value_with_baseline($baselineBlock['data'] ?? [], $rawBlock['data']);
        }
        $merged[] = $next;
    }

    return $merged;
}

function app_prune_unchanged_structured_value_pair($current, $baseline): array
{
    if (is_array($current)) {
        if (array_is_list($current)) {
            $items = [];
            foreach ($current as $index => $entry) {
                $baselineEntry = (is_array($baseline) && array_is_list($baseline)) ? ($baseline[$index] ?? null) : null;
                [$keepEntry, $value] = app_prune_unchanged_structured_value_pair($entry, $baselineEntry);
                if (!$keepEntry) {
                    $items[] = is_array($entry) ? app_strip_runtime_image_fields_deep($entry) : $entry;
                    continue;
                }
                $items[] = $value;
            }
            return [true, $items];
        }

        $baselineObject = (is_array($baseline) && !array_is_list($baseline)) ? $baseline : [];
        $next = [];
        foreach ($current as $key => $value) {
            [$keepChild, $childValue] = app_prune_unchanged_structured_value_pair($value, $baselineObject[$key] ?? null);
            if (!$keepChild) {
                continue;
            }
            if (is_array($childValue) && !array_is_list($childValue) && $childValue === []) {
                continue;
            }
            $next[$key] = $childValue;
        }
        return [$next !== [], $next];
    }

    if ($current === $baseline) {
        return [false, null];
    }

    return [true, $current];
}

function app_exportable_block_snapshot(array $block, ?array $baselineBlock, array $theme): array
{
    $type = trim((string) ($block['type'] ?? ''));
    if ($type === '') {
        return [];
    }

    $definition = app_block_definition($type);
    if ($definition === null) {
        return [];
    }

    $variants = is_array($definition['variants'] ?? null) ? $definition['variants'] : [app_default_block_variant($type)];
    $fallbackVariant = app_default_block_variant($type);
    if (!in_array($fallbackVariant, $variants, true)) {
        $fallbackVariant = (int) ($variants[0] ?? 1);
    }
    $variant = isset($block['variant']) ? (int) $block['variant'] : $fallbackVariant;
    if (!in_array($variant, $variants, true)) {
        $variant = $fallbackVariant;
    }

    $snapshot = [
        'type' => $type,
        'variant' => $variant,
    ];

    $baselineType = trim((string) ($baselineBlock['type'] ?? ''));
    $baselineVariant = ($baselineType === $type && isset($baselineBlock['variant'])) ? (int) $baselineBlock['variant'] : $variant;
    $baselineWidthPercent = app_normalize_block_width_percent(
        $baselineBlock['widthPercent'] ?? null,
        app_default_block_width_percent($type, $baselineVariant)
    );
    $widthPercent = app_normalize_block_width_percent(
        $block['widthPercent'] ?? null,
        app_default_block_width_percent($type, $variant)
    );
    if ($widthPercent !== $baselineWidthPercent) {
        $snapshot['widthPercent'] = $widthPercent;
    }

    $hidden = (bool) ($block['hidden'] ?? false);
    $baselineHidden = (bool) ($baselineBlock['hidden'] ?? false);
    if ($hidden !== $baselineHidden) {
        $snapshot['hidden'] = $hidden;
    }

    $backgroundOpacity = max(0, min(100, (float) ($block['backgroundOpacity'] ?? app_default_block_background_opacity($type, $theme))));
    $baselineBackgroundOpacity = max(0, min(100, (float) ($baselineBlock['backgroundOpacity'] ?? app_default_block_background_opacity($type, $theme))));
    if ($backgroundOpacity !== $baselineBackgroundOpacity) {
        $snapshot['backgroundOpacity'] = $backgroundOpacity;
    }

    $baselineHasBackgroundColor = is_array($baselineBlock) && array_key_exists('backgroundColor', $baselineBlock);
    $baselineLegacyCardOpacity = array_key_exists('cardBackgroundOpacity', $baselineBlock ?? [])
        ? $baselineBlock['cardBackgroundOpacity']
        : (!$baselineHasBackgroundColor ? ($baselineBlock['backgroundOpacity'] ?? null) : null);
    $cardBackgroundOpacity = max(0, min(100, (float) ($block['cardBackgroundOpacity'] ?? app_default_block_card_background_opacity($type, $theme))));
    $baselineCardBackgroundOpacity = max(0, min(100, (float) ($baselineLegacyCardOpacity ?? app_default_block_card_background_opacity($type, $theme))));
    if ($cardBackgroundOpacity !== $baselineCardBackgroundOpacity) {
        $snapshot['cardBackgroundOpacity'] = $cardBackgroundOpacity;
    }

    $foregroundOpacity = max(0, min(100, (float) ($block['foregroundOpacity'] ?? 100)));
    $baselineForegroundOpacity = max(0, min(100, (float) ($baselineBlock['foregroundOpacity'] ?? 100)));
    if ($foregroundOpacity !== $baselineForegroundOpacity) {
        $snapshot['foregroundOpacity'] = $foregroundOpacity;
    }

    $fontScale = max(25, min(300, (float) ($block['fontScale'] ?? 100)));
    $baselineFontScale = max(25, min(300, (float) ($baselineBlock['fontScale'] ?? 100)));
    if ($fontScale !== $baselineFontScale) {
        $snapshot['fontScale'] = $fontScale;
    }

    $verticalPaddingScale = max(0, min(300, (float) ($block['verticalPaddingScale'] ?? app_default_block_vertical_padding_scale($type))));
    $baselineVerticalPaddingScale = max(0, min(300, (float) ($baselineBlock['verticalPaddingScale'] ?? app_default_block_vertical_padding_scale($type))));
    if ($verticalPaddingScale !== $baselineVerticalPaddingScale) {
        $snapshot['verticalPaddingScale'] = $verticalPaddingScale;
    }

    $backgroundColor = app_normalize_block_background_color((string) ($block['backgroundColor'] ?? ''));
    $baselineBackgroundColor = app_normalize_block_background_color((string) ($baselineBlock['backgroundColor'] ?? ''));
    if ($backgroundColor !== '') {
        if ($backgroundColor !== $baselineBackgroundColor) {
            $snapshot['backgroundColor'] = $backgroundColor;
        }
    } elseif ($baselineBackgroundColor !== '') {
        $snapshot['backgroundColor'] = '';
    }

    $cardColor = app_normalize_block_card_color((string) ($block['cardColor'] ?? ''));
    $baselineCardColor = app_normalize_block_card_color((string) ($baselineBlock['cardColor'] ?? ''));
    if ($cardColor !== '') {
        if ($cardColor !== $baselineCardColor) {
            $snapshot['cardColor'] = $cardColor;
        }
    } elseif ($baselineCardColor !== '') {
        $snapshot['cardColor'] = '';
    }

    if (array_key_exists('cardBorderWidth', $block)) {
        $cardBorderWidth = app_normalize_block_card_border_width($block['cardBorderWidth'], $theme);
        $baselineCardBorderWidth = array_key_exists('cardBorderWidth', $baselineBlock ?? [])
            ? app_normalize_block_card_border_width($baselineBlock['cardBorderWidth'], $theme)
            : null;
        if ($baselineCardBorderWidth === null || $cardBorderWidth !== $baselineCardBorderWidth) {
            $snapshot['cardBorderWidth'] = $cardBorderWidth;
        }
    }

    $cardBorderColor = app_normalize_block_card_border_color((string) ($block['cardBorderColor'] ?? ''));
    $baselineCardBorderColor = app_normalize_block_card_border_color((string) ($baselineBlock['cardBorderColor'] ?? ''));
    if ($cardBorderColor !== '') {
        if ($cardBorderColor !== $baselineCardBorderColor) {
            $snapshot['cardBorderColor'] = $cardBorderColor;
        }
    } elseif ($baselineCardBorderColor !== '') {
        $snapshot['cardBorderColor'] = '';
    }

    $cardBorderStyle = app_normalize_block_card_border_style($block['cardBorderStyle'] ?? '');
    $baselineCardBorderStyle = app_normalize_block_card_border_style($baselineBlock['cardBorderStyle'] ?? '');
    if ($cardBorderStyle !== '') {
        if ($cardBorderStyle !== $baselineCardBorderStyle) {
            $snapshot['cardBorderStyle'] = $cardBorderStyle;
        }
    } elseif ($baselineCardBorderStyle !== '') {
        $snapshot['cardBorderStyle'] = '';
    }

    $animation = app_normalize_block_animation($block['animation'] ?? 'none');
    $baselineAnimation = app_normalize_block_animation($baselineBlock['animation'] ?? 'none');
    if ($animation !== $baselineAnimation) {
        $snapshot['animation'] = $animation;
    }

    $currentData = app_strip_runtime_image_fields_deep(is_array($block['data'] ?? null) ? $block['data'] : []);
    $baselineData = app_strip_runtime_image_fields_deep(
        is_array($baselineBlock['data'] ?? null)
            ? $baselineBlock['data']
            : (is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [])
    );
    [$keepData, $prunedData] = app_prune_unchanged_structured_value_pair($currentData, $baselineData);
    if ($keepData && is_array($prunedData) && $prunedData !== []) {
        $snapshot['data'] = $prunedData;
    }

    return $snapshot;
}

function app_fill_image_defaults(array $data, array $theme): array
{
    $firstImage = app_theme_image_urls($theme)[0] ?? '';
    if ($firstImage === '') {
        return $data;
    }

    $walker = function ($value) use (&$walker, $firstImage) {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            foreach ($value as $k => $item) {
                $value[$k] = $walker($item);
            }
            if ($isAssoc) {
                $mode = (string) ($value['imageMode'] ?? '');
                if ($mode === '') {
                    return $value;
                }

                if ($mode === 'themeRandom' && (($value['resolvedImageUrl'] ?? '') === '')) {
                    $value['resolvedImageUrl'] = $firstImage;
                    if (array_key_exists('imageUrl', $value) && (($value['imageUrl'] ?? '') === '')) {
                        $value['imageUrl'] = $firstImage;
                    } elseif (array_key_exists('url', $value) && is_string($value['url']) && trim($value['url']) === '') {
                        $value['url'] = $firstImage;
                    }
                }
            }
        }

        return $value;
    };

    return $walker($data);
}

function app_create_block(string $type, int $variant = 1, array $theme = [], array $data = []): ?array
{
    $definition = app_block_definition($type);
    if ($definition === null) {
        return null;
    }

    $variants = $definition['variants'] ?? [1];
    $fallbackVariant = app_default_block_variant($type);
    if (!in_array($fallbackVariant, $variants, true)) {
        $fallbackVariant = (int) $variants[0];
    }
    $variant = in_array($variant, $variants, true) ? $variant : $fallbackVariant;

    $baseData = $definition['defaults'] ?? [];
    $mergedData = app_merge_block_data($baseData, $data);
    if (!empty($theme)) {
        $mergedData = app_fill_image_defaults($mergedData, $theme);
    }
    $defaultWidthPercent = app_default_block_width_percent($type, $variant);

    return [
        'uid' => app_uuid(),
        'type' => $type,
        'variant' => $variant,
        'hidden' => false,
        'widthPercent' => $defaultWidthPercent,
        'backgroundOpacity' => app_default_block_background_opacity($type, $theme),
        'cardBackgroundOpacity' => app_default_block_card_background_opacity($type, $theme),
        'foregroundOpacity' => 100,
        'fontScale' => 100,
        'verticalPaddingScale' => app_default_block_vertical_padding_scale($type),
        'data' => $mergedData,
    ];
}

function app_normalize_block_width_percent($value, int $fallback = 80): int
{
    $raw = is_numeric($value) ? (float) $value : (float) $fallback;
    $raw = max(20, min(100, $raw));
    $steps = [20, 30, 40, 50, 60, 70, 80, 90, 100];
    $nearest = $steps[0];
    $distance = abs($raw - $nearest);
    foreach ($steps as $step) {
        $nextDistance = abs($raw - $step);
        if ($nextDistance <= $distance) {
            $nearest = $step;
            $distance = $nextDistance;
        }
    }

    return $nearest;
}

function app_default_block_width_percent(string $type, int $variant = 1): int
{
    if ($type === 'hero' && in_array($variant, [3, 4, 6, 7], true)) {
        return 100;
    }

    return in_array($type, ['header', 'footer', 'socialbar', 'carousel'], true) ? 100 : 80;
}

function app_default_block_variant(string $type): int
{
    return $type === 'hero' ? 4 : 1;
}

function app_migrate_legacy_block_data(string $type, int $variant, array $data): array
{
    if ($type === 'gallery' && isset($data['images']) && is_array($data['images'])) {
        foreach ($data['images'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $legacyImageUrl = trim((string) ($item['imageUrl'] ?? ''));
            if ($legacyImageUrl !== '' && trim((string) ($item['url'] ?? '')) === '') {
                $data['images'][$index]['url'] = $legacyImageUrl;
            }
            unset($data['images'][$index]['imageUrl']);
        }
    }

    $layoutMode = strtolower(trim((string) ($data['layoutMode'] ?? '')));
    if ($layoutMode === '') {
        return $data;
    }

    $layoutAliases = [
        'banner:3' => [
            'zion-caption' => 'caption-stack',
        ],
        'gallery:6' => [
            'zion-collage' => 'editorial-collage',
        ],
        'gallery:8' => [
            'zion-instagram' => 'social-grid',
        ],
        'cta:6' => [
            'zion-newsletter' => 'newsletter-split',
        ],
        'testimonials:8' => [
            'zion-review-stage' => 'review-stage',
        ],
    ];

    $key = $type . ':' . $variant;
    if (!isset($layoutAliases[$key][$layoutMode])) {
        return $data;
    }

    $data['layoutMode'] = $layoutAliases[$key][$layoutMode];
    return $data;
}

function app_default_block_background_opacity(string $type, array $theme = []): int
{
    return 100;
}

function app_default_block_card_background_opacity(string $type, array $theme = []): int
{
    $opacity = $theme['elements']['card']['opacity'] ?? ($theme['defaults']['elements']['card']['opacity'] ?? null);
    if (!is_numeric($opacity)) {
        return 50;
    }

    return (int) max(0, min(100, round(((float) $opacity) * 100)));
}

function app_default_block_vertical_padding_scale(string $type): int
{
    return in_array($type, ['header', 'footer'], true) ? 0 : 100;
}

function app_normalize_block_animation($value): string
{
    if (!is_string($value)) {
        return 'none';
    }

    $normalized = strtolower(trim($value));
    $allowed = ['none', 'fade-left', 'fade-right', 'fade-up', 'fade-down'];

    return in_array($normalized, $allowed, true) ? $normalized : 'none';
}

function app_is_valid_css_color_reference(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return false;
    }
    if (strtolower($trimmed) === 'transparent') {
        return true;
    }

    return preg_match('/^var\(--[a-zA-Z0-9\-]+\)$/', $trimmed) === 1
        || preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $trimmed) === 1
        || preg_match('/^(rgb|rgba|hsl|hsla)\(.+\)$/', $trimmed) === 1;
}

function app_normalize_block_card_color($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $trimmed = trim($value);
    if (strtolower($trimmed) === 'theme-gradient') {
        return 'theme-gradient';
    }
    return app_is_valid_css_color_reference($trimmed) ? $trimmed : '';
}

function app_normalize_block_background_color($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $trimmed = trim($value);
    if (strtolower($trimmed) === 'theme-gradient') {
        return 'theme-gradient';
    }
    return app_is_valid_css_color_reference($trimmed) ? $trimmed : '';
}

function app_normalize_block_heading_color($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $trimmed = trim($value);
    return app_is_valid_css_color_reference($trimmed) ? $trimmed : '';
}

function app_normalize_block_heading_colors($value, $legacyH1 = null): array
{
    $levels = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    $source = is_array($value) ? $value : [];
    $normalized = [];

    foreach ($levels as $level) {
        if (!array_key_exists($level, $source)) {
            continue;
        }
        $color = app_normalize_block_heading_color($source[$level]);
        if ($color !== '') {
            $normalized[$level] = $color;
        }
    }

    $legacyColor = app_normalize_block_heading_color($legacyH1);
    if ($legacyColor !== '' && !isset($normalized['h1'])) {
        $normalized['h1'] = $legacyColor;
    }

    return $normalized;
}

function app_default_block_card_color(array $theme): string
{
    $raw = isset($theme['elements']['card']['color']) && is_string($theme['elements']['card']['color'])
        ? $theme['elements']['card']['color']
        : '';
    $normalized = app_normalize_block_card_color($raw);
    return $normalized !== '' ? $normalized : 'var(--surface)';
}

function app_block_card_color_is_default(string $value, array $theme): bool
{
    $normalized = strtolower(app_normalize_block_card_color($value));
    if ($normalized === '') {
        return true;
    }

    return $normalized === strtolower(app_default_block_card_color($theme));
}

function app_normalize_block_card_border_color($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $trimmed = trim($value);
    return app_is_valid_css_color_reference($trimmed) ? $trimmed : '';
}

function app_default_block_card_border_color(array $theme): string
{
    $raw = isset($theme['elements']['card']['borderColor']) && is_string($theme['elements']['card']['borderColor'])
        ? $theme['elements']['card']['borderColor']
        : '';
    $normalized = app_normalize_block_card_border_color($raw);
    return $normalized !== '' ? $normalized : 'var(--border)';
}

function app_default_block_card_border_width(array $theme): int
{
    $raw = $theme['elements']['borderWidth'] ?? ($theme['defaults']['elements']['borderWidth'] ?? null);
    if (!is_numeric($raw)) {
        return 1;
    }

    return (int) max(0, min(16, round((float) $raw)));
}

function app_normalize_block_card_border_width($value, array $theme = []): int
{
    $fallback = app_default_block_card_border_width($theme);
    $raw = is_numeric($value) ? (float) $value : (float) $fallback;
    return (int) max(0, min(16, round($raw)));
}

function app_normalize_block_card_border_style($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $normalized = strtolower(trim($value));
    $allowed = ['solid', 'dashed', 'dotted', 'double', 'none'];
    return in_array($normalized, $allowed, true) ? $normalized : '';
}

function app_theme_defaults(): array
{
    return [
        'theme' => [
            'id' => 'default',
            'name' => 'Default',
            'colors' => [
                'background' => '#f8fafc',
                'surface' => '#ffffff',
                'primary' => '#1d4ed8',
                'secondary' => '#1e293b',
                'text' => '#0f172a',
                'textMuted' => '#475569',
                'border' => '#cbd5e1',
            ],
            'effects' => [
                'shadow' => '0 8px 28px rgba(15, 23, 42, 0.08)',
            ],
        ],
        'palette' => [
            'base' => '#1d4ed8',
            'lighter' => '#93c5fd',
            'darker' => '#1e3a8a',
            'complementary' => '#f97316',
            'splitComplementary' => '#14b8a6',
            'triadic' => '#8b5cf6',
            'background' => '#f8fafc',
        ],
        'gradient' => [
            'accent' => 'var(--complementary)',
            'angle' => 135,
            'glow' => 0,
            'gradStart' => 'var(--base)',
            'gradEnd' => 'var(--background)',
        ],
        'typography' => [
            'mode' => 'theme',
            'heading' => [
                'family' => '"Outfit", sans-serif',
                'size' => 60,
                'weight' => 700,
                'lineHeight' => 1.2,
                'letterSpacing' => 0,
                'color' => '#0f172a',
                'h2Color' => '#0f172a',
                'h3PlusColor' => '#0f172a',
            ],
            'body' => [
                'family' => '"Inter", sans-serif',
                'size' => 16,
                'weight' => 400,
                'lineHeight' => 1.6,
                'letterSpacing' => 0,
                'color' => '#0f172a',
            ],
            'link' => [
                'color' => '#1d4ed8',
            ],
        ],
        'images' => [
            ['url' => 'https://picsum.photos/id/1048/1200/700'],
            ['url' => 'https://picsum.photos/id/1057/1200/700'],
        ],
        'metadata' => [
            'createdAt' => date('Y-m-d'),
        ],
        'elements' => [
            'card' => [
                'opacity' => 1,
                'radius' => 16,
                'color' => 'var(--surface)',
                'shadow' => 'var(--theme-shadow)',
                'hoverShadow' => 'var(--theme-shadow)',
            ],
            'button' => [
                'color' => 'var(--primary)',
                'opacity' => 1,
                'borderColor' => 'var(--primary)',
                'secondaryBackground' => 'transparent',
                'radius' => 10,
                'shadow' => 'none',
                'hoverShadow' => 'none',
            ],
            'input' => [
                'background' => 'var(--surface)',
            ],
            'hr' => [
                'color' => 'var(--border)',
            ],
            'borderWidth' => 1,
        ],
    ];
}

function app_theme_deep_merge(array $base, array $overrides): array
{
    foreach ($overrides as $key => $value) {
        if (
            is_array($value)
            && isset($base[$key])
            && is_array($base[$key])
            && !array_is_list($value)
            && !array_is_list($base[$key])
        ) {
            $base[$key] = app_theme_deep_merge($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function app_is_theme_catalog_json(array $payload): bool
{
    $hasDefaults = isset($payload['defaults']) && is_array($payload['defaults']);
    $hasThemeTokens = isset($payload['colors']) || isset($payload['effects']) || isset($payload['shapes']) || $hasDefaults;
    $hasTypography = isset($payload['typography']) || ($hasDefaults && isset($payload['defaults']['typography']) && is_array($payload['defaults']['typography']));

    return $hasThemeTokens && $hasTypography;
}

function app_theme_parse_pixel_number($value): ?float
{
    if (is_numeric($value)) {
        return (float) $value;
    }

    if (!is_string($value)) {
        return null;
    }

    $normalized = preg_replace('/px$/i', '', trim($value));
    if ($normalized === null || !is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

function app_theme_payload_from_catalog_entry(array $payload): array
{
    $theme = app_theme_defaults();
    $picsumId = isset($payload['picsumId']) ? trim((string) $payload['picsumId']) : '';

    $theme = app_theme_deep_merge($theme, [
        'theme' => [
            'id' => (string) ($payload['id'] ?? ''),
            'name' => (string) ($payload['name'] ?? ($payload['id'] ?? 'Standard Theme')),
            'picsumId' => $picsumId,
            'colors' => isset($payload['colors']) && is_array($payload['colors']) ? $payload['colors'] : [],
            'effects' => isset($payload['effects']) && is_array($payload['effects']) ? $payload['effects'] : [],
        ],
        'palette' => isset($payload['palette']) && is_array($payload['palette']) ? $payload['palette'] : [],
        'gradient' => isset($payload['gradient']) && is_array($payload['gradient']) ? $payload['gradient'] : [],
        'typography' => isset($payload['typography']) && is_array($payload['typography']) ? $payload['typography'] : [],
        'elements' => isset($payload['elements']) && is_array($payload['elements']) ? $payload['elements'] : [],
        'images' => $picsumId !== ''
            ? [[
                'id' => $picsumId,
                'source' => 'picsum',
                'url' => 'https://picsum.photos/id/' . rawurlencode($picsumId) . '/1200/480',
            ]]
            : [],
    ]);

    $shapes = isset($payload['shapes']) && is_array($payload['shapes']) ? $payload['shapes'] : [];
    $cardRadius = app_theme_parse_pixel_number($shapes['borderRadius'] ?? null);
    $buttonRadius = app_theme_parse_pixel_number($shapes['buttonRadius'] ?? null);
    $borderWidth = app_theme_parse_pixel_number($shapes['borderWidth'] ?? null);
    if ($cardRadius !== null) {
        $theme['elements']['card']['radius'] = $cardRadius;
    }
    if ($buttonRadius !== null) {
        $theme['elements']['button']['radius'] = $buttonRadius;
    }
    if ($borderWidth !== null) {
        $theme['elements']['borderWidth'] = $borderWidth;
    }

    if (isset($payload['defaults']) && is_array($payload['defaults'])) {
        $theme = app_theme_deep_merge($theme, $payload['defaults']);
    }

    return $theme;
}

function app_normalize_theme_json(array $payload): array
{
    $defaults = app_theme_defaults();
    if (!isset($payload['theme']) && app_is_theme_catalog_json($payload)) {
        $payload = app_theme_payload_from_catalog_entry($payload);
    }
    if (!app_is_theme_json($payload)) {
        return $defaults;
    }

    $theme = app_theme_deep_merge($defaults, $payload);
    if (isset($payload['defaults']) && is_array($payload['defaults'])) {
        $theme = app_theme_deep_merge($theme, $payload['defaults']);
    }

    if (!isset($theme['theme']) || !is_array($theme['theme'])) {
        $theme['theme'] = $defaults['theme'];
    }
    if (!isset($theme['theme']['colors']) || !is_array($theme['theme']['colors'])) {
        $theme['theme']['colors'] = $defaults['theme']['colors'];
    }
    if (!isset($theme['theme']['effects']) || !is_array($theme['theme']['effects'])) {
        $theme['theme']['effects'] = $defaults['theme']['effects'];
    }
    if (!isset($theme['palette']) || !is_array($theme['palette'])) {
        $theme['palette'] = $defaults['palette'];
    }
    if (!isset($theme['gradient']) || !is_array($theme['gradient'])) {
        $theme['gradient'] = $defaults['gradient'];
    }
    if (!isset($theme['typography']) || !is_array($theme['typography'])) {
        $theme['typography'] = $defaults['typography'];
    }
    if (!isset($theme['typography']['heading']) || !is_array($theme['typography']['heading'])) {
        $theme['typography']['heading'] = $defaults['typography']['heading'];
    }
    if (!isset($theme['typography']['body']) || !is_array($theme['typography']['body'])) {
        $theme['typography']['body'] = $defaults['typography']['body'];
    }
    if (!isset($theme['typography']['link']) || !is_array($theme['typography']['link'])) {
        $theme['typography']['link'] = $defaults['typography']['link'];
    }
    if (!isset($theme['elements']) || !is_array($theme['elements'])) {
        $theme['elements'] = $defaults['elements'];
    }
    if (!isset($theme['elements']['card']) || !is_array($theme['elements']['card'])) {
        $theme['elements']['card'] = $defaults['elements']['card'];
    }
    if (!isset($theme['elements']['button']) || !is_array($theme['elements']['button'])) {
        $theme['elements']['button'] = $defaults['elements']['button'];
    }
    if (!isset($theme['elements']['input']) || !is_array($theme['elements']['input'])) {
        $theme['elements']['input'] = $defaults['elements']['input'];
    }
    if (!isset($theme['elements']['hr']) || !is_array($theme['elements']['hr'])) {
        $theme['elements']['hr'] = $defaults['elements']['hr'];
    }
    if (!isset($theme['images']) || !is_array($theme['images'])) {
        $theme['images'] = $defaults['images'];
    }
    if (!isset($theme['metadata']) || !is_array($theme['metadata'])) {
        $theme['metadata'] = $defaults['metadata'];
    }

    return $theme;
}

function app_default_theme(): array
{
    $candidate = app_read_json_file(__DIR__ . '/../ThemeChooser_output.json');
    if (app_is_theme_json($candidate)) {
        return app_normalize_theme_json($candidate);
    }

    return app_theme_defaults();
}

function app_default_page(array $theme): array
{
    $pageLocale = app_current_locale();
    $page = [
        'title' => app_tr('Starter Page'),
        'slug' => 'starter-page',
        'locale' => $pageLocale,
        'showInHeaderNav' => true,
        'showInFooterNav' => true,
        'sharedContact' => app_normalize_shared_contact_profile(null, $pageLocale),
        'canvas' => app_default_canvas_settings(),
        'blocks' => [],
    ];

    $starter = [
        ['type' => 'header', 'variant' => 1],
        ['type' => 'hero', 'variant' => 4],
        ['type' => 'features', 'variant' => 1],
        ['type' => 'stats', 'variant' => 1],
        ['type' => 'gallery', 'variant' => 1],
        ['type' => 'cta', 'variant' => 1],
        ['type' => 'contact', 'variant' => 1],
        ['type' => 'socialbar', 'variant' => 1],
        ['type' => 'footer', 'variant' => 1],
    ];

    foreach ($starter as $item) {
        $block = app_create_block($item['type'], $item['variant'], $theme);
        if ($block !== null) {
            $page['blocks'][] = $block;
        }
    }

    return $page;
}

function app_default_canvas_settings(): array
{
    return [
        'lineHeightScale' => 100,
        'spacingXScale' => 100,
        'spacingYScale' => 150,
        'elementSpacingScale' => 100,
        'gradientOpacity' => 100,
        'linkAnimation' => 'none',
        'containerMaxWidthDesktop' => 80,
        'containerMaxWidthTablet' => 90,
        'containerMaxWidthMobile' => 100,
        'footerFollowContainerMaxWidth' => true,
    ];
}

function app_normalize_canvas_link_animation_value($value): string
{
    $defaults = app_default_canvas_settings();
    $raw = trim((string) $value);
    if ($raw === '') {
        return (string) $defaults['linkAnimation'];
    }

    $normalizedKey = strtolower((string) preg_replace('/[^a-z]+/i', '', $raw));
    $map = [
        'none' => 'none',
        'underline' => 'underline',
        'fadeunderline' => 'underlineSweep',
        'underlinesweep' => 'underlineSweep',
        'splitlift' => 'splitLift',
        'ripplecircle' => 'rippleCircle',
        'dotreveal' => 'dotReveal',
        'pillrise' => 'pillRise',
        'bracketsslide' => 'bracketsSlide',
    ];

    return $map[$normalizedKey] ?? (string) $defaults['linkAnimation'];
}

function app_normalize_canvas_settings($input): array
{
    $defaults = app_default_canvas_settings();
    if (!is_array($input)) {
        return $defaults;
    }

    $lineHeightScale = (float) ($input['lineHeightScale'] ?? $defaults['lineHeightScale']);
    $spacingXScale = (float) ($input['spacingXScale'] ?? $defaults['spacingXScale']);
    $spacingYScale = (float) ($input['spacingYScale'] ?? $defaults['spacingYScale']);
    $elementSpacingScale = (float) ($input['elementSpacingScale'] ?? $defaults['elementSpacingScale']);
    $gradientOpacity = (float) ($input['gradientOpacity'] ?? $defaults['gradientOpacity']);
    $containerMaxWidthDesktop = (float) ($input['containerMaxWidthDesktop'] ?? $defaults['containerMaxWidthDesktop']);
    $containerMaxWidthTablet = (float) ($input['containerMaxWidthTablet'] ?? $defaults['containerMaxWidthTablet']);
    $containerMaxWidthMobile = (float) ($input['containerMaxWidthMobile'] ?? $defaults['containerMaxWidthMobile']);
    $footerFollowContainerMaxWidth = app_normalize_site_page_nav_visibility(
        $input['footerFollowContainerMaxWidth'] ?? $defaults['footerFollowContainerMaxWidth'],
        (bool) $defaults['footerFollowContainerMaxWidth']
    );
    $linkAnimation = app_normalize_canvas_link_animation_value($input['linkAnimation'] ?? $defaults['linkAnimation']);

    return [
        'lineHeightScale' => max(50, min(300, $lineHeightScale)),
        'spacingXScale' => max(50, min(300, $spacingXScale)),
        'spacingYScale' => max(50, min(300, $spacingYScale)),
        'elementSpacingScale' => max(25, min(300, $elementSpacingScale)),
        'gradientOpacity' => max(0, min(100, $gradientOpacity)),
        'containerMaxWidthDesktop' => max(20, min(100, $containerMaxWidthDesktop)),
        'containerMaxWidthTablet' => max(20, min(100, $containerMaxWidthTablet)),
        'containerMaxWidthMobile' => max(20, min(100, $containerMaxWidthMobile)),
        'footerFollowContainerMaxWidth' => $footerFollowContainerMaxWidth,
        'linkAnimation' => $linkAnimation,
    ];
}

function app_is_theme_json(array $payload): bool
{
    return isset($payload['theme']) && is_array($payload['theme']) && isset($payload['palette']) && isset($payload['typography']);
}

function app_normalize_site_page_nav_visibility($value, bool $fallback = true): bool
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return $fallback;
        }
        if (in_array($normalized, ['false', '0', 'no', 'off'], true)) {
            return false;
        }
        if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
    }

    return (bool) $value;
}

function app_normalize_site_page(array $incomingPage, array $theme, int $index = 1): array
{
    $fallbackTitle = app_tr('Page {index}', ['index' => $index]);
    $pageLocale = app_normalize_locale((string) ($incomingPage['locale'] ?? app_current_locale()));
    $title = trim((string) ($incomingPage['title'] ?? $incomingPage['pageTitle'] ?? $incomingPage['name'] ?? $fallbackTitle));
    if ($title === '') {
        $title = $fallbackTitle;
    }

    $slug = app_slugify((string) ($incomingPage['slug'] ?? $title));
    if ($slug === '') {
        $slug = 'page-' . $index;
    }

    $id = app_slugify((string) ($incomingPage['id'] ?? $incomingPage['pageId'] ?? $slug));
    if ($id === '') {
        $id = 'page-' . $index;
    }

    $menuLabel = trim((string) ($incomingPage['menuLabel'] ?? $incomingPage['label'] ?? $title));
    if ($menuLabel === '') {
        $menuLabel = $title;
    }

    $showInHeaderNav = app_normalize_site_page_nav_visibility($incomingPage['showInHeaderNav'] ?? null, true);
    $showInFooterNav = app_normalize_site_page_nav_visibility($incomingPage['showInFooterNav'] ?? null, true);
    $sharedContact = app_normalize_shared_contact_profile($incomingPage['sharedContact'] ?? null, $pageLocale);
    $presetId = app_slugify((string) ($incomingPage['presetId'] ?? ''));
    $canvas = app_normalize_canvas_settings($incomingPage['canvas'] ?? null);
    $hasExplicitBlocks = array_key_exists('blocks', $incomingPage) && is_array($incomingPage['blocks']);
    $blocks = app_normalize_blocks($incomingPage['blocks'] ?? [], $theme);
    $safeBlocks = $hasExplicitBlocks ? $blocks : app_default_page($theme)['blocks'];

    if (
        $presetId !== ''
        && function_exists('app_build_page_from_preset')
        && function_exists('app_blocks_match_baseline_shape')
        && function_exists('app_merge_blocks_with_baseline')
    ) {
        $baselinePage = app_build_page_from_preset($presetId, $theme, [
            'id' => $id,
            'pageId' => $id,
            'label' => $menuLabel,
            'menuLabel' => $menuLabel,
            'pageTitle' => $title,
            'title' => $title,
            'slug' => $slug,
            'locale' => $pageLocale,
            'canvas' => $canvas,
        ]);

        if (is_array($baselinePage)) {
            if ($hasExplicitBlocks && app_blocks_match_baseline_shape($incomingPage['blocks'] ?? [], $baselinePage['blocks'] ?? [])) {
                $safeBlocks = app_normalize_blocks(app_merge_blocks_with_baseline($baselinePage['blocks'] ?? [], $incomingPage['blocks'] ?? []), $theme);
            } elseif (!$hasExplicitBlocks) {
                $safeBlocks = app_normalize_blocks($baselinePage['blocks'] ?? [], $theme);
            }
        }
    }

    if ($safeBlocks === [] && !$hasExplicitBlocks) {
        $safeBlocks = app_default_page($theme)['blocks'];
    }

    return [
        'id' => $id,
        'title' => $title,
        'menuLabel' => $menuLabel,
        'slug' => $slug,
        'locale' => $pageLocale,
        'showInHeaderNav' => $showInHeaderNav,
        'showInFooterNav' => $showInFooterNav,
        'sharedContact' => $sharedContact,
        'presetId' => $presetId,
        'canvas' => $canvas,
        'blocks' => $safeBlocks,
    ];
}

function app_normalize_site_pages($pages, array $theme): array
{
    if (!is_array($pages)) {
        return [];
    }

    $normalized = [];
    $seenIds = [];
    $seenSlugs = [];
    foreach ($pages as $index => $page) {
        if (!is_array($page)) {
            continue;
        }

        $item = app_normalize_site_page($page, $theme, $index + 1);

        $baseId = $item['id'];
        $nextId = $baseId;
        $counter = 2;
        while (isset($seenIds[$nextId])) {
            $nextId = $baseId . '-' . $counter;
            $counter += 1;
        }
        $item['id'] = $nextId;
        $seenIds[$nextId] = true;

        $baseSlug = $item['slug'];
        $nextSlug = $baseSlug;
        $slugCounter = 2;
        while (isset($seenSlugs[$nextSlug])) {
            $nextSlug = $baseSlug . '-' . $slugCounter;
            $slugCounter += 1;
        }
        $item['slug'] = $nextSlug;
        $seenSlugs[$nextSlug] = true;

        $normalized[] = $item;
    }

    return $normalized;
}

function app_site_shared_header_block(array $site, array $theme): ?array
{
    $sharedBlocks = $site['sharedBlocks'] ?? null;
    if (!is_array($sharedBlocks)) {
        return null;
    }

    $rawHeader = $sharedBlocks['header'] ?? null;
    if (!is_array($rawHeader)) {
        return null;
    }
    if ((string) ($rawHeader['type'] ?? '') !== 'header') {
        return null;
    }

    $normalized = app_normalize_blocks([$rawHeader], $theme);
    if ($normalized === []) {
        return null;
    }
    $header = $normalized[0];
    if ((string) ($header['type'] ?? '') !== 'header') {
        return null;
    }

    return $header;
}

function app_site_shared_footer_block(array $site, array $theme): ?array
{
    $sharedBlocks = $site['sharedBlocks'] ?? null;
    if (!is_array($sharedBlocks)) {
        return null;
    }

    $rawFooter = $sharedBlocks['footer'] ?? null;
    if (!is_array($rawFooter)) {
        return null;
    }
    if ((string) ($rawFooter['type'] ?? '') !== 'footer') {
        return null;
    }

    $normalized = app_normalize_blocks([$rawFooter], $theme);
    if ($normalized === []) {
        return null;
    }
    $footer = $normalized[0];
    if ((string) ($footer['type'] ?? '') !== 'footer') {
        return null;
    }

    return $footer;
}

function app_site_navigation_links_from_pages(array $pages, string $target = 'header'): array
{
    $links = [];
    $flagKey = $target === 'footer' ? 'showInFooterNav' : 'showInHeaderNav';
    foreach ($pages as $index => $page) {
        if (!is_array($page)) {
            continue;
        }
        if (!app_normalize_site_page_nav_visibility($page[$flagKey] ?? null, true)) {
            continue;
        }

        $label = trim((string) ($page['title'] ?? $page['menuLabel'] ?? app_tr('Page')));
        if ($label === '') {
            $label = app_tr('Page');
        }

        $pageId = app_slugify((string) ($page['id'] ?? $page['pageId'] ?? ''));
        $pageSlug = app_slugify((string) ($page['slug'] ?? $label));

        if ($pageId === '') {
            $pageId = 'page-' . ($index + 1);
        }
        if ($pageSlug === '') {
            $pageSlug = $pageId;
        }

        $links[] = [
            'label' => $label,
            'url' => '#' . $pageSlug,
            'pageId' => $pageId,
            'pageSlug' => $pageSlug,
        ];
    }

    return $links;
}

function app_site_navigation_links_match_existing(array $existingLinks, array $computedLinks): bool
{
    if ($existingLinks === []) {
        return true;
    }
    if ($computedLinks === []) {
        return false;
    }

    $matched = 0;
    $examined = 0;

    foreach ($existingLinks as $link) {
        if (!is_array($link)) {
            continue;
        }

        $label = trim((string) ($link['label'] ?? ''));
        $rawUrl = trim((string) ($link['url'] ?? ''));
        $pageId = app_slugify((string) ($link['pageId'] ?? $link['id'] ?? ''));
        $pageSlug = app_slugify((string) ($link['pageSlug'] ?? $link['slug'] ?? ''));
        $urlToken = app_slugify(ltrim($rawUrl, '#'));

        if ($label === '' && $rawUrl === '' && $pageId === '' && $pageSlug === '') {
            continue;
        }

        $examined += 1;
        $found = false;
        foreach ($computedLinks as $computed) {
            if (!is_array($computed)) {
                continue;
            }

            $computedLabel = app_slugify((string) ($computed['label'] ?? ''));
            $computedId = app_slugify((string) ($computed['pageId'] ?? ''));
            $computedSlug = app_slugify((string) ($computed['pageSlug'] ?? ''));

            if (
                ($pageId !== '' && $pageId === $computedId)
                || ($pageSlug !== '' && $pageSlug === $computedSlug)
                || ($urlToken !== '' && $urlToken === $computedSlug)
                || ($label !== '' && app_slugify($label) === $computedLabel)
            ) {
                $found = true;
                break;
            }
        }

        if (!$found && $rawUrl !== '' && !str_starts_with($rawUrl, '#')) {
            return false;
        }
        if ($found) {
            $matched += 1;
        }
    }

    if ($examined === 0) {
        return true;
    }

    return $matched > 0 && $matched >= min(count($computedLinks), $examined);
}

function app_apply_site_navigation_links_to_pages(array $pages): array
{
    if (count($pages) < 2) {
        return $pages;
    }

    $headerLinks = app_site_navigation_links_from_pages($pages, 'header');
    $footerLinks = app_site_navigation_links_from_pages($pages, 'footer');

    foreach ($pages as $pageIndex => $page) {
        if (!is_array($page)) {
            continue;
        }

        $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        foreach ($blocks as $blockIndex => $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            if (!in_array($type, ['header', 'footer'], true)) {
                continue;
            }

            if (!isset($block['data']) || !is_array($block['data'])) {
                $block['data'] = [];
            }
            $computedLinks = $type === 'footer' ? $footerLinks : $headerLinks;
            $existingLinks = is_array($block['data']['links'] ?? null) ? $block['data']['links'] : [];
            if (!array_key_exists('links', $block['data']) || app_site_navigation_links_match_existing($existingLinks, $computedLinks)) {
                $block['data']['links'] = $computedLinks;
            }
            $blocks[$blockIndex] = $block;
        }

        $page['blocks'] = $blocks;
        $pages[$pageIndex] = $page;
    }

    return $pages;
}

function app_apply_shared_header_to_pages(array $pages, array $sharedHeader): array
{
    if ((string) ($sharedHeader['type'] ?? '') !== 'header') {
        return $pages;
    }
    $headerTemplate = $sharedHeader;

    foreach ($pages as $index => $page) {
        if (!is_array($page)) {
            continue;
        }
        $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        $nextBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if ((string) ($block['type'] ?? '') === 'header') {
                continue;
            }
            $nextBlocks[] = $block;
        }

        $header = $headerTemplate;
        $header['uid'] = app_uuid();
        array_unshift($nextBlocks, $header);
        $page['blocks'] = $nextBlocks;
        $pages[$index] = $page;
    }

    return $pages;
}

function app_apply_shared_footer_to_pages(array $pages, array $sharedFooter): array
{
    if ((string) ($sharedFooter['type'] ?? '') !== 'footer') {
        return $pages;
    }
    $footerTemplate = $sharedFooter;

    foreach ($pages as $index => $page) {
        if (!is_array($page)) {
            continue;
        }
        $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        $nextBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            if ((string) ($block['type'] ?? '') === 'footer') {
                continue;
            }
            $nextBlocks[] = $block;
        }

        $footer = $footerTemplate;
        $footer['uid'] = app_uuid();
        $nextBlocks[] = $footer;
        $page['blocks'] = $nextBlocks;
        $pages[$index] = $page;
    }

    return $pages;
}

function app_dry_shared_header_payload(array $payload): array
{
    $site = is_array($payload['site'] ?? null) ? $payload['site'] : [];
    $sitePages = is_array($site['pages'] ?? null) ? $site['pages'] : [];
    if ($sitePages === []) {
        return $payload;
    }

    $activePageId = app_slugify((string) ($site['activePageId'] ?? ''));
    $sharedHeader = null;
    $sharedFooter = null;
    if ($activePageId !== '') {
        foreach ($sitePages as $page) {
            if (!is_array($page)) {
                continue;
            }
            if (app_slugify((string) ($page['id'] ?? '')) !== $activePageId) {
                continue;
            }
            $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $blockType = (string) ($block['type'] ?? '');
                if ($blockType === 'header' && $sharedHeader === null) {
                    $sharedHeader = $block;
                } elseif ($blockType === 'footer' && $sharedFooter === null) {
                    $sharedFooter = $block;
                }
                if ($sharedHeader !== null && $sharedFooter !== null) {
                    break 2;
                }
            }
        }
    }

    if ($sharedHeader === null || $sharedFooter === null) {
        foreach ($sitePages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $blockType = (string) ($block['type'] ?? '');
                if ($blockType === 'header' && $sharedHeader === null) {
                    $sharedHeader = $block;
                } elseif ($blockType === 'footer' && $sharedFooter === null) {
                    $sharedFooter = $block;
                }
                if ($sharedHeader !== null && $sharedFooter !== null) {
                    break 2;
                }
            }
        }
    }

    if (!is_array($sharedHeader) && !is_array($sharedFooter)) {
        return $payload;
    }

    foreach ($sitePages as $index => $page) {
        if (!is_array($page)) {
            continue;
        }
        $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
        $page['blocks'] = array_values(array_filter($blocks, static function ($block): bool {
            if (!is_array($block)) {
                return false;
            }
            $type = (string) ($block['type'] ?? '');
            return $type !== 'header' && $type !== 'footer';
        }));
        $sitePages[$index] = $page;
    }
    $payload['site']['pages'] = $sitePages;

    if (!isset($payload['site']['sharedBlocks']) || !is_array($payload['site']['sharedBlocks'])) {
        $payload['site']['sharedBlocks'] = [];
    }
    if (is_array($sharedHeader)) {
        $payload['site']['sharedBlocks']['header'] = $sharedHeader;
    }
    if (is_array($sharedFooter)) {
        $payload['site']['sharedBlocks']['footer'] = $sharedFooter;
    }

    if (isset($payload['page']) && is_array($payload['page'])) {
        $pageBlocks = is_array($payload['page']['blocks'] ?? null) ? $payload['page']['blocks'] : [];
        $payload['page']['blocks'] = array_values(array_filter($pageBlocks, static function ($block): bool {
            if (!is_array($block)) {
                return false;
            }
            $type = (string) ($block['type'] ?? '');
            return $type !== 'header' && $type !== 'footer';
        }));
    }

    return $payload;
}

function app_normalize_site_assets($assets): array
{
    if (!is_array($assets)) {
        return [];
    }

    $normalized = [];
    foreach ($assets as $asset) {
        if (!is_array($asset)) {
            continue;
        }

        $id = trim((string) ($asset['id'] ?? ''));
        $dataUrl = trim((string) ($asset['dataUrl'] ?? ''));
        if ($id === '' || preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=\r\n]+$#i', $dataUrl) !== 1) {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'filename' => trim((string) ($asset['filename'] ?? ($id . '.webp'))),
            'mime' => trim((string) ($asset['mime'] ?? 'image/webp')),
            'dataUrl' => preg_replace('/\s+/', '', $dataUrl) ?? $dataUrl,
            'size' => max(0, (int) round((float) ($asset['size'] ?? 0))),
            'width' => max(0, (int) round((float) ($asset['width'] ?? 0))),
            'height' => max(0, (int) round((float) ($asset['height'] ?? 0))),
            'createdAt' => trim((string) ($asset['createdAt'] ?? '')),
        ];
    }

    return array_values($normalized);
}

function app_sitekit_payload_kind(array $payload): string
{
    if (app_is_theme_json($payload) || app_is_theme_catalog_json($payload)) {
        return 'theme';
    }

    return 'project';
}

function app_sitekit_validate_scalar_field(array $container, string $key, string $path, int $maxLength, bool $allowEmpty = true): void
{
    if (!array_key_exists($key, $container) || $container[$key] === null) {
        return;
    }

    $value = $container[$key];
    if (!is_scalar($value)) {
        throw new RuntimeException($path . ' must be a string-compatible scalar.');
    }

    $text = (string) $value;
    if (!$allowEmpty && trim($text) === '') {
        throw new RuntimeException($path . ' is required.');
    }
    if (strlen($text) > $maxLength) {
        throw new RuntimeException($path . ' exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' length limit.');
    }
}

function app_sitekit_validate_boolish($value, string $path): void
{
    if ($value === null) {
        return;
    }
    if (is_bool($value)) {
        return;
    }
    if ((is_int($value) || is_float($value)) && ($value === 0 || $value === 1 || $value === 0.0 || $value === 1.0)) {
        return;
    }
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['', '0', '1', 'false', 'true', 'no', 'yes', 'off', 'on'], true)) {
            return;
        }
    }

    throw new RuntimeException($path . ' must be boolean-like.');
}

function app_sitekit_validate_numericish($value, string $path): void
{
    if ($value === null || $value === '') {
        return;
    }
    if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
        return;
    }

    throw new RuntimeException($path . ' must be numeric.');
}

function app_sitekit_validate_json_tree($value, string $path, int $depth = 0, ?int $stringLimit = null): void
{
    $limits = app_sitekit_import_limits();
    if ($depth > (int) $limits['maxJsonTreeDepth']) {
        throw new RuntimeException($path . ' exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' nesting limit.');
    }

    if (is_array($value)) {
        if (count($value) > (int) $limits['maxJsonObjectItems']) {
            throw new RuntimeException($path . ' exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' item limit.');
        }
        foreach ($value as $key => $child) {
            if (!is_int($key) && !is_string($key)) {
                throw new RuntimeException($path . ' contains an invalid key type.');
            }
            if (is_string($key) && strlen($key) > 120) {
                throw new RuntimeException($path . ' contains a key that exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' length limit.');
            }
            $childPath = is_int($key) ? ($path . '[' . $key . ']') : ($path . '.' . $key);
            app_sitekit_validate_json_tree($child, $childPath, $depth + 1, $stringLimit);
        }
        return;
    }

    if (is_string($value)) {
        $maxLength = $stringLimit ?? (int) $limits['maxStringBytes'];
        if (strlen($value) > $maxLength) {
            throw new RuntimeException($path . ' exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' string size limit.');
        }
        return;
    }

    if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
        return;
    }

    throw new RuntimeException($path . ' contains an unsupported value type.');
}

function app_sitekit_validate_theme_payload(array $theme, string $path): void
{
    app_sitekit_validate_json_tree($theme, $path);
}

function app_sitekit_validate_canvas_payload(array $canvas, string $path): void
{
    $numericKeys = [
        'lineHeightScale',
        'spacingXScale',
        'spacingYScale',
        'elementSpacingScale',
        'gradientOpacity',
        'containerMaxWidthDesktop',
        'containerMaxWidthTablet',
        'containerMaxWidthMobile',
    ];
    foreach ($numericKeys as $key) {
        if (array_key_exists($key, $canvas)) {
            app_sitekit_validate_numericish($canvas[$key], $path . '.' . $key);
        }
    }
    if (array_key_exists('footerFollowContainerMaxWidth', $canvas)) {
        app_sitekit_validate_boolish($canvas['footerFollowContainerMaxWidth'], $path . '.footerFollowContainerMaxWidth');
    }
    if (array_key_exists('linkAnimation', $canvas)) {
        app_sitekit_validate_scalar_field($canvas, 'linkAnimation', $path . '.linkAnimation', 40);
    }
    app_sitekit_validate_json_tree($canvas, $path, 0, 256);
}

function app_sitekit_validate_shared_contact_payload(array $contact, string $path): void
{
    foreach (['email', 'phone', 'locationLabel', 'locationNote', 'latitude', 'longitude'] as $key) {
        app_sitekit_validate_scalar_field($contact, $key, $path . '.' . $key, 255);
    }
    app_sitekit_validate_scalar_field($contact, 'address', $path . '.address', 4000);
    app_sitekit_validate_json_tree($contact, $path, 0, 4000);
}

function app_sitekit_validate_block_payload(array $block, string $path): void
{
    app_sitekit_validate_scalar_field($block, 'uid', $path . '.uid', 120);
    app_sitekit_validate_scalar_field($block, 'type', $path . '.type', 60, false);

    $type = trim((string) ($block['type'] ?? ''));
    $definition = app_block_definition($type);
    if ($definition === null) {
        throw new RuntimeException($path . '.type references an unknown block type.');
    }

    if (array_key_exists('variant', $block)) {
        app_sitekit_validate_numericish($block['variant'], $path . '.variant');
        $variant = (int) $block['variant'];
        $variants = is_array($definition['variants'] ?? null) ? $definition['variants'] : [app_default_block_variant($type)];
        if (!in_array($variant, $variants, true)) {
            throw new RuntimeException($path . '.variant is not allowed for block type "' . $type . '".');
        }
    }

    foreach (['widthPercent', 'backgroundOpacity', 'foregroundOpacity', 'fontScale', 'verticalPaddingScale', 'cardBackgroundOpacity', 'cardBorderWidth'] as $key) {
        if (array_key_exists($key, $block)) {
            app_sitekit_validate_numericish($block[$key], $path . '.' . $key);
        }
    }
    foreach (['hidden', 'fullWidth'] as $key) {
        if (array_key_exists($key, $block)) {
            app_sitekit_validate_boolish($block[$key], $path . '.' . $key);
        }
    }
    foreach (['backgroundColor', 'cardColor', 'cardBorderColor'] as $key) {
        if (!array_key_exists($key, $block) || $block[$key] === null || $block[$key] === '') {
            continue;
        }
        if (!is_scalar($block[$key])) {
            throw new RuntimeException($path . '.' . $key . ' must be a string.');
        }
        $value = trim((string) $block[$key]);
        $normalized = $key === 'backgroundColor'
            ? app_normalize_block_background_color($value)
            : ($key === 'cardColor' ? app_normalize_block_card_color($value) : app_normalize_block_card_border_color($value));
        if ($normalized === '') {
            throw new RuntimeException($path . '.' . $key . ' is not a valid SiteKit spec ' . app_sitekit_spec_version() . ' color value.');
        }
    }
    if (array_key_exists('cardBorderStyle', $block) && $block['cardBorderStyle'] !== null && $block['cardBorderStyle'] !== '') {
        if (!is_scalar($block['cardBorderStyle']) || app_normalize_block_card_border_style((string) $block['cardBorderStyle']) === '') {
            throw new RuntimeException($path . '.cardBorderStyle is invalid.');
        }
    }
    if (array_key_exists('animation', $block) && $block['animation'] !== null && $block['animation'] !== '') {
        if (!is_scalar($block['animation'])) {
            throw new RuntimeException($path . '.animation must be a string.');
        }
        $rawAnimation = strtolower(trim((string) $block['animation']));
        if (app_normalize_block_animation($rawAnimation) !== $rawAnimation) {
            throw new RuntimeException($path . '.animation is invalid.');
        }
    }

    if (array_key_exists('data', $block)) {
        if (!is_array($block['data'])) {
            throw new RuntimeException($path . '.data must be an object.');
        }
        app_sitekit_validate_json_tree($block['data'], $path . '.data', 0, (int) app_sitekit_import_limits()['maxBlockDataStringBytes']);
    }
}

function app_sitekit_validate_page_payload(array $page, string $path): void
{
    foreach (['id', 'title', 'menuLabel', 'slug', 'locale', 'presetId'] as $key) {
        app_sitekit_validate_scalar_field($page, $key, $path . '.' . $key, 160, $key !== 'title' && $key !== 'slug');
    }
    foreach (['showInHeaderNav', 'showInFooterNav'] as $key) {
        if (array_key_exists($key, $page)) {
            app_sitekit_validate_boolish($page[$key], $path . '.' . $key);
        }
    }
    if (array_key_exists('sharedContact', $page)) {
        if (!is_array($page['sharedContact'])) {
            throw new RuntimeException($path . '.sharedContact must be an object.');
        }
        app_sitekit_validate_shared_contact_payload($page['sharedContact'], $path . '.sharedContact');
    }
    if (array_key_exists('canvas', $page)) {
        if (!is_array($page['canvas'])) {
            throw new RuntimeException($path . '.canvas must be an object.');
        }
        app_sitekit_validate_canvas_payload($page['canvas'], $path . '.canvas');
    }
    if (array_key_exists('blocks', $page)) {
        if (!is_array($page['blocks'])) {
            throw new RuntimeException($path . '.blocks must be an array.');
        }
        if (count($page['blocks']) > (int) app_sitekit_import_limits()['maxBlocksPerPage']) {
            throw new RuntimeException($path . '.blocks exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' block limit.');
        }
        foreach ($page['blocks'] as $index => $block) {
            if (!is_array($block)) {
                throw new RuntimeException($path . '.blocks[' . $index . '] must be an object.');
            }
            app_sitekit_validate_block_payload($block, $path . '.blocks[' . $index . ']');
        }
    }
}

function app_sitekit_validate_site_assets_payload(array $assets, string $path): void
{
    $limits = app_sitekit_import_limits();
    if (count($assets) > (int) $limits['maxAssetsPerSite']) {
        throw new RuntimeException($path . ' exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' asset limit.');
    }

    foreach ($assets as $index => $asset) {
        if (!is_array($asset)) {
            throw new RuntimeException($path . '[' . $index . '] must be an object.');
        }
        app_sitekit_validate_scalar_field($asset, 'id', $path . '[' . $index . '].id', 120, false);
        app_sitekit_validate_scalar_field($asset, 'filename', $path . '[' . $index . '].filename', 255);
        app_sitekit_validate_scalar_field($asset, 'mime', $path . '[' . $index . '].mime', 120);
        app_sitekit_validate_scalar_field($asset, 'createdAt', $path . '[' . $index . '].createdAt', 64);
        foreach (['size', 'width', 'height'] as $key) {
            if (array_key_exists($key, $asset)) {
                app_sitekit_validate_numericish($asset[$key], $path . '[' . $index . '].' . $key);
            }
        }

        $dataUrl = trim((string) ($asset['dataUrl'] ?? ''));
        if ($dataUrl === '') {
            throw new RuntimeException($path . '[' . $index . '].dataUrl is required.');
        }
        if (strlen($dataUrl) > (int) $limits['maxAssetDataUrlChars']) {
            throw new RuntimeException($path . '[' . $index . '].dataUrl exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' asset size limit.');
        }
        if (preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=\r\n]+$#i', $dataUrl) !== 1) {
            throw new RuntimeException($path . '[' . $index . '].dataUrl must be a base64-encoded image data URL.');
        }
    }
}

function app_validate_sitekit_payload(array $payload): void
{
    app_sitekit_validate_json_tree($payload, 'payload');

    if (app_sitekit_payload_kind($payload) === 'theme') {
        app_sitekit_validate_theme_payload($payload, 'payload');
        return;
    }

    if (isset($payload['version']) && $payload['version'] !== null) {
        app_sitekit_validate_scalar_field($payload, 'version', 'payload.version', 32, false);
    }

    if (isset($payload['theme'])) {
        if (!is_array($payload['theme'])) {
            throw new RuntimeException('payload.theme must be an object.');
        }
        app_sitekit_validate_theme_payload($payload['theme'], 'payload.theme');
    }

    $hasPage = isset($payload['page']) && is_array($payload['page']);
    $hasSitePages = isset($payload['site']['pages']) && is_array($payload['site']['pages']) && count($payload['site']['pages']) > 0;
    if (!$hasPage && !$hasSitePages) {
        throw new RuntimeException('SiteKit spec ' . app_sitekit_spec_version() . ' requires a page payload or site.pages.');
    }

    if (isset($payload['page'])) {
        if (!is_array($payload['page'])) {
            throw new RuntimeException('payload.page must be an object.');
        }
        app_sitekit_validate_page_payload($payload['page'], 'payload.page');
    }

    if (isset($payload['site'])) {
        if (!is_array($payload['site'])) {
            throw new RuntimeException('payload.site must be an object.');
        }
        app_sitekit_validate_scalar_field($payload['site'], 'name', 'payload.site.name', 160);
        app_sitekit_validate_scalar_field($payload['site'], 'typeId', 'payload.site.typeId', 120);
        app_sitekit_validate_scalar_field($payload['site'], 'activePageId', 'payload.site.activePageId', 120);
        app_sitekit_validate_scalar_field($payload['site'], 'baseUrl', 'payload.site.baseUrl', 2048, true);

        if (isset($payload['site']['canvas'])) {
            if (!is_array($payload['site']['canvas'])) {
                throw new RuntimeException('payload.site.canvas must be an object.');
            }
            app_sitekit_validate_canvas_payload($payload['site']['canvas'], 'payload.site.canvas');
        }

        if (isset($payload['site']['pages'])) {
            if (!is_array($payload['site']['pages'])) {
                throw new RuntimeException('payload.site.pages must be an array.');
            }
            if (count($payload['site']['pages']) > (int) app_sitekit_import_limits()['maxPages']) {
                throw new RuntimeException('payload.site.pages exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' page limit.');
            }
            foreach ($payload['site']['pages'] as $index => $page) {
                if (!is_array($page)) {
                    throw new RuntimeException('payload.site.pages[' . $index . '] must be an object.');
                }
                app_sitekit_validate_page_payload($page, 'payload.site.pages[' . $index . ']');
            }
        }

        if (isset($payload['site']['assets'])) {
            if (!is_array($payload['site']['assets'])) {
                throw new RuntimeException('payload.site.assets must be an array.');
            }
            app_sitekit_validate_site_assets_payload($payload['site']['assets'], 'payload.site.assets');
        }

        if (isset($payload['site']['sharedBlocks'])) {
            if (!is_array($payload['site']['sharedBlocks'])) {
                throw new RuntimeException('payload.site.sharedBlocks must be an object.');
            }
            if (count($payload['site']['sharedBlocks']) > (int) app_sitekit_import_limits()['maxSharedBlocks']) {
                throw new RuntimeException('payload.site.sharedBlocks exceeds the SiteKit spec ' . app_sitekit_spec_version() . ' shared block limit.');
            }
            foreach (['header', 'footer'] as $key) {
                if (!isset($payload['site']['sharedBlocks'][$key])) {
                    continue;
                }
                if (!is_array($payload['site']['sharedBlocks'][$key])) {
                    throw new RuntimeException('payload.site.sharedBlocks.' . $key . ' must be an object.');
                }
                app_sitekit_validate_block_payload($payload['site']['sharedBlocks'][$key], 'payload.site.sharedBlocks.' . $key);
            }
        }
    }
}

function app_import_sitekit_payload(array $payload): array
{
    app_validate_sitekit_payload($payload);
    return app_normalize_page_json($payload);
}

function app_normalize_page_json(array $payload): array
{
    $theme = app_default_theme();

    if (isset($payload['theme']) && is_array($payload['theme'])) {
        if (app_is_theme_json($payload['theme'])) {
            $theme = app_normalize_theme_json($payload['theme']);
        } elseif (app_is_theme_json($payload)) {
            $theme = app_normalize_theme_json($payload);
        }
    } elseif (app_is_theme_json($payload)) {
        $theme = app_normalize_theme_json($payload);
    }

    $site = is_array($payload['site'] ?? null) ? $payload['site'] : [];
    $pageCanvasInput = (isset($payload['page']) && is_array($payload['page'])) ? ($payload['page']['canvas'] ?? null) : null;
    $siteName = trim((string) ($site['name'] ?? $site['label'] ?? $payload['siteName'] ?? ''));
    $siteTypeId = app_slugify((string) ($site['typeId'] ?? $site['siteTypeId'] ?? $payload['siteTypeId'] ?? ''));
    $siteBaseUrl = trim((string) ($site['baseUrl'] ?? ''));
    $siteCanvasInput = null;
    if (isset($site['canvas']) && is_array($site['canvas'])) {
        $siteCanvasInput = $site['canvas'];
    } elseif (isset($site['canvasControls']) && is_array($site['canvasControls'])) {
        $siteCanvasInput = $site['canvasControls'];
    }
    $hasSiteCanvas = is_array($siteCanvasInput);

    $sitePages = app_normalize_site_pages($site['pages'] ?? null, $theme);
    if ($sitePages === [] && isset($payload['page']) && is_array($payload['page'])) {
        $sitePages[] = app_normalize_site_page($payload['page'], $theme, 1);
    }
    if ($sitePages === []) {
        $sitePages = app_normalize_site_pages([app_default_page($theme)], $theme);
    }
    if ($hasSiteCanvas) {
        $siteCanvas = app_normalize_canvas_settings($siteCanvasInput);
        foreach ($sitePages as &$sitePage) {
            if (is_array($sitePage)) {
                $sitePage['canvas'] = $siteCanvas;
            }
        }
        unset($sitePage);
    }
    $sharedHeader = app_site_shared_header_block($site, $theme);
    if (is_array($sharedHeader)) {
        $sitePages = app_apply_shared_header_to_pages($sitePages, $sharedHeader);
    }
    $sharedFooter = app_site_shared_footer_block($site, $theme);
    if (is_array($sharedFooter)) {
        $sitePages = app_apply_shared_footer_to_pages($sitePages, $sharedFooter);
    }
    $sitePages = app_apply_site_navigation_links_to_pages($sitePages);

    $activePageId = app_slugify((string) ($site['activePageId'] ?? $site['currentPageId'] ?? $payload['activePageId'] ?? ''));
    if ($activePageId === '' && isset($payload['page']) && is_array($payload['page'])) {
        $activeSlug = app_slugify((string) ($payload['page']['slug'] ?? ''));
        if ($activeSlug !== '') {
            foreach ($sitePages as $sitePage) {
                if ((string) ($sitePage['slug'] ?? '') === $activeSlug) {
                    $activePageId = (string) ($sitePage['id'] ?? '');
                    break;
                }
            }
        }
    }
    if ($activePageId === '') {
        $activePageId = (string) ($sitePages[0]['id'] ?? 'page-1');
    }

    $activePage = $sitePages[0];
    foreach ($sitePages as $sitePage) {
        if ((string) ($sitePage['id'] ?? '') === $activePageId) {
            $activePage = $sitePage;
            break;
        }
    }

    $siteCanvas = $hasSiteCanvas
        ? app_normalize_canvas_settings($siteCanvasInput)
        : app_normalize_canvas_settings($activePage['canvas'] ?? $pageCanvasInput);

    $page = [
        'title' => (string) ($activePage['title'] ?? 'Untitled Page'),
        'slug' => (string) ($activePage['slug'] ?? 'untitled-page'),
        'locale' => app_normalize_locale((string) ($activePage['locale'] ?? app_current_locale())),
        'sharedContact' => app_normalize_shared_contact_profile($activePage['sharedContact'] ?? null, (string) ($activePage['locale'] ?? app_current_locale())),
        'canvas' => $siteCanvas,
        'blocks' => app_normalize_blocks($activePage['blocks'] ?? [], $theme),
    ];
    if ($page['blocks'] === []) {
        $page['blocks'] = app_default_page($theme)['blocks'];
    }

    if ($siteName === '') {
        $siteName = (string) ($site['id'] ?? 'Site');
        if ($siteName === '') {
            $siteName = 'Site';
        }
    }

    $version = (isset($payload['site']) && is_array($payload['site'])) || count($sitePages) > 1 ? '2.0' : '1.0';

    return [
        'version' => $version,
        'theme' => $theme,
        'site' => [
            'name' => $siteName,
            'typeId' => $siteTypeId,
            'activePageId' => $activePageId,
            'baseUrl' => $siteBaseUrl,
            'canvas' => $siteCanvas,
            'assets' => app_normalize_site_assets($site['assets'] ?? null),
            'pages' => $sitePages,
        ],
        'page' => $page,
    ];
}

function app_strip_editor_uids(array $payload): array
{
    $stripBlockUids = static function ($blocks): array {
        if (!is_array($blocks)) {
            return [];
        }

        $next = [];
        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }
            unset($block['uid']);
            $next[$index] = $block;
        }

        return array_values($next);
    };

    if (isset($payload['page']) && is_array($payload['page']) && isset($payload['page']['blocks'])) {
        $payload['page']['blocks'] = $stripBlockUids($payload['page']['blocks']);
    }

    if (isset($payload['site']) && is_array($payload['site']) && isset($payload['site']['pages']) && is_array($payload['site']['pages'])) {
        foreach ($payload['site']['pages'] as $index => $page) {
            if (!is_array($page)) {
                continue;
            }
            $page['blocks'] = $stripBlockUids($page['blocks'] ?? []);
            $payload['site']['pages'][$index] = $page;
        }
    }

    return $payload;
}

function app_exportable_site_page_snapshot(array $page, array $theme): array
{
    $snapshot = [];
    foreach (['id', 'title', 'menuLabel', 'slug', 'locale', 'showInHeaderNav', 'showInFooterNav', 'sharedContact', 'presetId', 'canvas'] as $key) {
        if (array_key_exists($key, $page)) {
            $snapshot[$key] = $page[$key];
        }
    }

    $baselineBlocks = null;
    $presetId = app_slugify((string) ($page['presetId'] ?? ''));
    if ($presetId !== '') {
        $baselinePage = app_build_page_from_preset($presetId, $theme, [
            'id' => (string) ($page['id'] ?? ''),
            'pageId' => (string) ($page['id'] ?? ''),
            'label' => (string) ($page['menuLabel'] ?? $page['title'] ?? ''),
            'menuLabel' => (string) ($page['menuLabel'] ?? $page['title'] ?? ''),
            'pageTitle' => (string) ($page['title'] ?? ''),
            'title' => (string) ($page['title'] ?? ''),
            'slug' => (string) ($page['slug'] ?? ''),
            'locale' => (string) ($page['locale'] ?? app_current_locale()),
            'canvas' => is_array($page['canvas'] ?? null) ? $page['canvas'] : null,
        ]);
        if (is_array($baselinePage) && app_blocks_match_baseline_shape(
            is_array($page['blocks'] ?? null) ? $page['blocks'] : [],
            is_array($baselinePage['blocks'] ?? null) ? $baselinePage['blocks'] : []
        )) {
            $baselineBlocks = is_array($baselinePage['blocks'] ?? null) ? $baselinePage['blocks'] : null;
        }
    }

    $blocks = [];
    foreach ((is_array($page['blocks'] ?? null) ? $page['blocks'] : []) as $index => $block) {
        if (!is_array($block)) {
            continue;
        }
        $baselineBlock = is_array($baselineBlocks[$index] ?? null) ? $baselineBlocks[$index] : null;
        $snapshotBlock = app_exportable_block_snapshot($block, $baselineBlock, $theme);
        if ($snapshotBlock !== []) {
            $blocks[] = $snapshotBlock;
        }
    }
    $snapshot['blocks'] = $blocks;

    return $snapshot;
}

function app_exportable_shared_blocks_snapshot(array $sharedBlocks, array $theme): array
{
    $snapshot = [];
    foreach (['header', 'footer'] as $key) {
        if (!is_array($sharedBlocks[$key] ?? null)) {
            continue;
        }
        $blockSnapshot = app_exportable_block_snapshot($sharedBlocks[$key], null, $theme);
        if ($blockSnapshot !== []) {
            $snapshot[$key] = $blockSnapshot;
        }
    }

    return $snapshot;
}

function app_exportable_page_json(array $payload): array
{
    $normalized = app_dry_shared_header_payload(app_strip_editor_uids(app_import_sitekit_payload($payload)));
    $theme = is_array($normalized['theme'] ?? null) ? $normalized['theme'] : app_default_theme();

    if (isset($normalized['site']) && is_array($normalized['site'])) {
        if (isset($normalized['site']['pages']) && is_array($normalized['site']['pages'])) {
            $normalized['site']['pages'] = array_values(array_filter(array_map(
                static fn ($page): array => is_array($page) ? app_exportable_site_page_snapshot($page, $theme) : [],
                $normalized['site']['pages']
            )));
        }

        if (isset($normalized['site']['sharedBlocks']) && is_array($normalized['site']['sharedBlocks'])) {
            $normalized['site']['sharedBlocks'] = app_exportable_shared_blocks_snapshot($normalized['site']['sharedBlocks'], $theme);
        }
    }

    if (isset($normalized['site']['activePageId']) && is_array($normalized['site']['pages'] ?? null)) {
        $activePageId = (string) ($normalized['site']['activePageId'] ?? '');
        foreach ($normalized['site']['pages'] as $page) {
            if (!is_array($page) || (string) ($page['id'] ?? '') !== $activePageId) {
                continue;
            }
            $normalized['page'] = $page;
            break;
        }
    }

    if (isset($normalized['page']) && is_array($normalized['page'])) {
        $normalized['page'] = app_exportable_site_page_snapshot($normalized['page'], $theme);
    }

    return $normalized;
}

function app_normalize_blocks($blocks, array $theme): array
{
    if (!is_array($blocks)) {
        return [];
    }

    $normalized = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $type = (string) ($block['type'] ?? '');
        $definition = app_block_definition($type);
        if ($definition === null) {
            continue;
        }

        $variants = $definition['variants'] ?? [1];
        $fallbackVariant = app_default_block_variant($type);
        if (!in_array($fallbackVariant, $variants, true)) {
            $fallbackVariant = (int) $variants[0];
        }
        $variant = (int) ($block['variant'] ?? $fallbackVariant);
        if (!in_array($variant, $variants, true)) {
            $variant = $fallbackVariant;
        }

        $uid = (string) ($block['uid'] ?? app_uuid());
        $hidden = (bool) ($block['hidden'] ?? false);
        $legacyFullWidth = (bool) ($block['fullWidth'] ?? false);
        $defaultWidthPercent = app_default_block_width_percent($type, $variant);
        $widthPercent = app_normalize_block_width_percent($block['widthPercent'] ?? ($legacyFullWidth ? 100 : $defaultWidthPercent), $defaultWidthPercent);
        $backgroundColor = app_normalize_block_background_color((string) ($block['backgroundColor'] ?? ''));
        $backgroundOpacity = max(0, min(100, (float) ($block['backgroundOpacity'] ?? app_default_block_background_opacity($type, $theme))));
        $foregroundOpacity = max(0, min(100, (float) ($block['foregroundOpacity'] ?? 100)));
        $fontScale = max(25, min(300, (float) ($block['fontScale'] ?? 100)));
        $verticalPaddingScale = max(0, min(300, (float) ($block['verticalPaddingScale'] ?? app_default_block_vertical_padding_scale($type))));
        $cardColor = app_normalize_block_card_color((string) ($block['cardColor'] ?? ''));
        $cardBorderColor = app_normalize_block_card_border_color((string) ($block['cardBorderColor'] ?? ''));
        $cardBorderStyle = app_normalize_block_card_border_style($block['cardBorderStyle'] ?? '');
        $cardBorderWidth = array_key_exists('cardBorderWidth', $block)
            ? app_normalize_block_card_border_width($block['cardBorderWidth'], $theme)
            : null;
        $animation = app_normalize_block_animation($block['animation'] ?? 'none');
        $legacyCardOpacity = array_key_exists('cardBackgroundOpacity', $block)
            ? $block['cardBackgroundOpacity']
            : (!array_key_exists('backgroundColor', $block)
                ? ($block['backgroundOpacity'] ?? null)
                : null);
        $cardBackgroundOpacity = max(0, min(100, (float) ($legacyCardOpacity ?? app_default_block_card_background_opacity($type, $theme))));

        $data = $definition['defaults'] ?? [];
        if (isset($block['data']) && is_array($block['data'])) {
            $data = app_merge_block_data($data, $block['data']);
        }

        $data = app_migrate_legacy_block_data($type, $variant, $data);
        $data = app_fill_image_defaults($data, $theme);
        $headingColors = app_normalize_block_heading_colors(
            $data['headingColors'] ?? null,
            $data['headingColor'] ?? null
        );
        if ($headingColors !== []) {
            $data['headingColors'] = $headingColors;
        } else {
            unset($data['headingColors']);
        }
        unset($data['headingColor']);

        $normalizedBlock = [
            'uid' => $uid,
            'type' => $type,
            'variant' => $variant,
            'hidden' => $hidden,
            'widthPercent' => $widthPercent,
            'backgroundOpacity' => $backgroundOpacity,
            'cardBackgroundOpacity' => $cardBackgroundOpacity,
            'foregroundOpacity' => $foregroundOpacity,
            'fontScale' => $fontScale,
            'verticalPaddingScale' => $verticalPaddingScale,
            'data' => $data,
        ];
        if ($backgroundColor !== '') {
            $normalizedBlock['backgroundColor'] = $backgroundColor;
        }
        if ($cardColor !== '' && !app_block_card_color_is_default($cardColor, $theme)) {
            $normalizedBlock['cardColor'] = $cardColor;
        }
        if ($cardBorderWidth !== null) {
            $normalizedBlock['cardBorderWidth'] = $cardBorderWidth;
        }
        if ($cardBorderColor !== '') {
            $normalizedBlock['cardBorderColor'] = $cardBorderColor;
        }
        if ($cardBorderStyle !== '') {
            $normalizedBlock['cardBorderStyle'] = $cardBorderStyle;
        }
        if ($animation !== 'none') {
            $normalizedBlock['animation'] = $animation;
        }
        $normalized[] = $normalizedBlock;
    }

    return $normalized;
}

function app_image_source_list_path(): string
{
    return __DIR__ . '/../data/image_sources.json';
}

function app_static_image_dir_path(): string
{
    return dirname(__DIR__) . '/static/images';
}

function app_static_image_list_path(): string
{
    return app_static_image_dir_path() . '/imagelist.txt';
}

function app_parse_static_image_list_line(string $line): array
{
    $raw = trim($line);
    if ($raw === '' || str_starts_with($raw, '#')) {
        return ['', []];
    }

    $filenamePart = $raw;
    $aliasPart = '';
    foreach (['|', "\t", ' => ', ' :: ', ' - '] as $separator) {
        if (strpos($raw, $separator) === false) {
            continue;
        }
        [$left, $right] = explode($separator, $raw, 2);
        if (strpos($left, '.') !== false) {
            $filenamePart = trim($left);
            $aliasPart = trim($right);
            break;
        }
    }

    if ($aliasPart === '' && strpos($raw, ':') !== false) {
        [$left, $right] = explode(':', $raw, 2);
        if (strpos($left, '.') !== false) {
            $filenamePart = trim($left);
            $aliasPart = trim($right);
        }
    }

    $filename = basename($filenamePart);
    $aliases = [];
    if ($aliasPart !== '') {
        foreach (preg_split('/[;,]+/', $aliasPart) ?: [] as $alias) {
            $normalized = trim((string) $alias);
            if ($normalized !== '') {
                $aliases[] = $normalized;
            }
        }
    }

    return [$filename, array_values(array_unique($aliases))];
}

function app_static_image_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $imageDir = app_static_image_dir_path();
    if (!is_dir($imageDir)) {
        $catalog = [];
        return $catalog;
    }

    $listedAliases = [];
    $filenames = [];
    $listPath = app_static_image_list_path();
    if (is_file($listPath)) {
        $lines = @file($listPath, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            [$filename, $aliases] = app_parse_static_image_list_line((string) $line);
            if ($filename === '' || strtolower($filename) === 'imagelist.txt') {
                continue;
            }
            $filenames[] = $filename;
            if ($aliases !== []) {
                $listedAliases[$filename] = isset($listedAliases[$filename])
                    ? array_values(array_unique(array_merge($listedAliases[$filename], $aliases)))
                    : $aliases;
            }
        }
    }

    $diskFiles = @scandir($imageDir) ?: [];
    foreach ($diskFiles as $entry) {
        $filename = basename((string) $entry);
        if ($filename === '' || $filename === '.' || $filename === '..' || strtolower($filename) === 'imagelist.txt') {
            continue;
        }
        $fullPath = $imageDir . '/' . $filename;
        if (is_file($fullPath)) {
            $filenames[] = $filename;
        }
    }

    $catalog = [];
    $seen = [];
    foreach ($filenames as $filename) {
        if (isset($seen[$filename])) {
            continue;
        }
        $seen[$filename] = true;
        $fullPath = $imageDir . '/' . $filename;
        if (!is_file($fullPath)) {
            continue;
        }
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $label = ucwords(str_replace(['-', '_'], ' ', $basename));
        $size = @filesize($fullPath);
        $modifiedAt = @filemtime($fullPath);
        $dimensions = @getimagesize($fullPath);
        $catalog[] = [
            'filename' => $filename,
            'url' => 'static/images/' . rawurlencode($filename),
            'label' => $label,
            'keywords' => $listedAliases[$filename] ?? [],
            'size' => is_int($size) ? $size : 0,
            'width' => is_array($dimensions) && isset($dimensions[0]) ? (int) $dimensions[0] : 0,
            'height' => is_array($dimensions) && isset($dimensions[1]) ? (int) $dimensions[1] : 0,
            'modifiedAt' => is_int($modifiedAt) && $modifiedAt > 0 ? gmdate('c', $modifiedAt) : '',
        ];
    }

    return $catalog;
}

function app_image_source_list(): array
{
    $raw = app_read_json_file(app_image_source_list_path(), []);
    if (!is_array($raw)) {
        $raw = [];
    }

    $urls = [];
    foreach ($raw as $row) {
        if (is_string($row)) {
            $safe = app_safe_url($row);
            if ($safe !== '') {
                $urls[] = $safe;
            }
            continue;
        }

        if (is_array($row)) {
            $safe = app_safe_url((string) ($row['url'] ?? ''));
            if ($safe !== '') {
                $urls[] = $safe;
            }
        }
    }

    foreach (app_static_image_catalog() as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $safe = app_safe_url((string) ($entry['url'] ?? ''));
        if ($safe !== '') {
            $urls[] = $safe;
        }
    }

    return array_values(array_unique($urls));
}
