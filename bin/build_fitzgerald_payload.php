<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/blocks.php';
require_once __DIR__ . '/../inc/presets.php';

function fitzgerald_site_url(string $path = '/'): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return 'https://fitzgerald.tonicsiteshop.com/';
    }

    return 'https://fitzgerald.tonicsiteshop.com/' . ltrim($path, '/');
}

function fitzgerald_showit_url(string $path): string
{
    return 'https://static.showit.co/' . ltrim($path, '/');
}

function fitzgerald_theme(): array
{
    return [
        'theme' => [
            'id' => 'fitzgerald_showit',
            'name' => 'Fitzgerald',
            'colors' => [
                'background' => '#f9f9f5',
                'surface' => '#fffdf9',
                'primary' => '#253320',
                'secondary' => '#070707',
                'text' => '#070707',
                'textMuted' => '#5d5a56',
                'border' => '#d8d2ca',
            ],
            'effects' => [
                'shadow' => 'none',
            ],
        ],
        'palette' => [
            'base' => '#253320',
            'lighter' => '#f4f1ea',
            'darker' => '#070707',
            'complementary' => '#b8b39f',
            'splitComplementary' => '#d8d2ca',
            'triadic' => '#e6e1d8',
            'background' => '#f9f9f5',
        ],
        'gradient' => [
            'accent' => 'var(--complementary)',
            'angle' => 180,
            'glow' => 0,
            'gradStart' => '#f9f9f5',
            'gradEnd' => '#f1ede7',
        ],
        'typography' => [
            'mode' => 'theme',
            'heading' => [
                'family' => '"Cormorant Garamond", Georgia, serif',
                'size' => 78,
                'weight' => 400,
                'lineHeight' => 1.02,
                'letterSpacing' => 0.08,
                'color' => '#070707',
                'h2Color' => '#070707',
                'h3PlusColor' => '#070707',
            ],
            'body' => [
                'family' => '"Inter", "Helvetica Neue", Arial, sans-serif',
                'size' => 17,
                'weight' => 300,
                'lineHeight' => 1.7,
                'letterSpacing' => 0.02,
                'color' => '#5d5a56',
            ],
            'link' => [
                'color' => '#070707',
            ],
            'fontFamily' => '"Inter", "Helvetica Neue", Arial, sans-serif',
            'fontSizeBase' => '17px',
            'headingFont' => '"Cormorant Garamond", Georgia, serif',
            'bodyFont' => '"Inter", "Helvetica Neue", Arial, sans-serif',
        ],
        'metadata' => [
            'createdAt' => '2026-04-11',
            'source' => fitzgerald_site_url('/'),
        ],
        'elements' => [
            'card' => [
                'opacity' => 0.94,
                'radius' => 4,
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
                'background' => '#fffdf9',
            ],
            'hr' => [
                'color' => '#d8d2ca',
            ],
            'borderWidth' => 1,
        ],
        'images' => [
            ['url' => fitzgerald_showit_url('2400/RRr87m7tT3qx49LSSfitsg/51489/lecollectif-34_cropped.jpg')],
            ['url' => fitzgerald_showit_url('800/XmFeL3LGQc-c0-ZfNMoAIA/51489/lecollectif-123.jpg')],
            ['url' => fitzgerald_showit_url('1200/51Y0sHxQQDudXJPm8KgVBQ/51489/lecollectif-150.jpg')],
            ['url' => fitzgerald_showit_url('file/7Hj3x5JQRmiLPMwwFFNV-A/51489/lecollectif-44.jpg')],
            ['url' => fitzgerald_showit_url('file/51qKNGppTOiUb9bbsUpy6w/51489/lecollectif-77.jpg')],
        ],
    ];
}

function fitzgerald_block(string $type, int $variant, array $theme, array $data = [], array $overrides = []): array
{
    $block = app_create_block($type, $variant, $theme, $data);
    if (!is_array($block)) {
        throw new RuntimeException('Unable to create block: ' . $type . ' v' . $variant);
    }

    foreach ($overrides as $key => $value) {
        $block[$key] = $value;
    }

    return $block;
}

function fitzgerald_strip_empty_payload_values($value)
{
    if (is_array($value)) {
        if (array_is_list($value)) {
            $items = [];
            foreach ($value as $entry) {
                $clean = fitzgerald_strip_empty_payload_values($entry);
                if ($clean === null) {
                    continue;
                }
                if (is_array($clean) && $clean === []) {
                    continue;
                }
                $items[] = $clean;
            }
            return $items;
        }

        $next = [];
        foreach ($value as $key => $entry) {
            if ($key === 'presetId') {
                continue;
            }
            $clean = fitzgerald_strip_empty_payload_values($entry);
            if ($clean === null) {
                continue;
            }
            if (is_array($clean) && $clean === []) {
                continue;
            }
            $next[$key] = $clean;
        }
        return $next;
    }

    if (is_string($value) && $value === '') {
        return null;
    }

    return $value;
}

$theme = fitzgerald_theme();

$headerLinks = [
    ['label' => 'Home', 'url' => fitzgerald_site_url('/')],
    ['label' => 'About', 'url' => fitzgerald_site_url('/about')],
    ['label' => 'Portfolio', 'url' => fitzgerald_site_url('/portfolio')],
    ['label' => 'Services', 'url' => fitzgerald_site_url('/services')],
    ['label' => 'Experience', 'url' => fitzgerald_site_url('/experience')],
    ['label' => 'Blog', 'url' => fitzgerald_site_url('/blog-home-demo-delete')],
    ['label' => 'Inquire', 'url' => fitzgerald_site_url('/contact')],
];

$footerLinks = [
    ['label' => 'Home', 'url' => fitzgerald_site_url('/')],
    ['label' => 'About', 'url' => fitzgerald_site_url('/about')],
    ['label' => 'Portfolio', 'url' => fitzgerald_site_url('/portfolio')],
    ['label' => 'Services', 'url' => fitzgerald_site_url('/services')],
    ['label' => 'Experience', 'url' => fitzgerald_site_url('/experience')],
    ['label' => 'Resources', 'url' => fitzgerald_site_url('/resources')],
    ['label' => 'The Blog', 'url' => fitzgerald_site_url('/blog-home-demo-delete')],
    ['label' => 'Inquire', 'url' => fitzgerald_site_url('/contact')],
];

$topFilmstrip = [
    fitzgerald_showit_url('1200/ai5XdUBZSeKvWPT60PoGjA/51489/tec_petaja_photography_lecollectif-30.jpg'),
    fitzgerald_showit_url('1200/1EUFZnqYRM28QCoKhxwUwA/51489/tec_petaja_photography_lecollectif-20.jpg'),
    fitzgerald_showit_url('1200/L6WKgP5OSMKcFH_HJ8VD3Q/51489/tec_petaja_photography_lecollectif-31.jpg'),
    fitzgerald_showit_url('1200/i1E689XkRiO_1lAy5xkWpg/51489/tec_petaja_photography_lecollectif-24.jpg'),
    fitzgerald_showit_url('1200/aBHZ0eelQTy7oSYokVU4Pw/51489/tec_petaja_photography_lecollectif-21.jpg'),
    fitzgerald_showit_url('1200/V7SGFYwbRtSQScDvwOdgfQ/51489/tec_petaja_photography_lecollectif-10.jpg'),
    fitzgerald_showit_url('1200/lSkMqVV8R9O4pcq6uySnuA/51489/tec_petaja_photography_lecollectif-19.jpg'),
];

$bottomFilmstrip = [
    fitzgerald_showit_url('1200/IDY737hxToW7fEfYkcwUpQ/51489/amanda-tom-10.jpg'),
    fitzgerald_showit_url('1200/w_MkqHEATnebd2_1eLsFqA/51489/amanda-tom-50.jpg'),
    fitzgerald_showit_url('1200/VHsjzl9XTNqByPPe0h_T6g/51489/tec_petaja_photography_lecollectif-28.jpg'),
    fitzgerald_showit_url('1200/ai5XdUBZSeKvWPT60PoGjA/51489/tec_petaja_photography_lecollectif-30.jpg'),
    fitzgerald_showit_url('1200/1EUFZnqYRM28QCoKhxwUwA/51489/tec_petaja_photography_lecollectif-20.jpg'),
    fitzgerald_showit_url('1200/V7SGFYwbRtSQScDvwOdgfQ/51489/tec_petaja_photography_lecollectif-10.jpg'),
    fitzgerald_showit_url('1200/lSkMqVV8R9O4pcq6uySnuA/51489/tec_petaja_photography_lecollectif-19.jpg'),
    fitzgerald_showit_url('1200/s4zL7cyjQN6c9C_gtJJyYg/51489/tec_petaja_photography_lecollectif-22.jpg'),
];

$footerStripImages = [
    fitzgerald_showit_url('270/aBHZ0eelQTy7oSYokVU4Pw/51489/tec_petaja_photography_lecollectif-21.jpg'),
    fitzgerald_showit_url('270/VHsjzl9XTNqByPPe0h_T6g/51489/tec_petaja_photography_lecollectif-28.jpg'),
    fitzgerald_showit_url('270/4D8sTbmRThqSpqHi3fC9Gw/51489/tec_petaja_photography_lecollectif-5.jpg'),
    fitzgerald_showit_url('270/7bvmLOGoQimD9sUGjKsxLw/51489/screen_shot_2024-04-17_at_10_55_19_am.png'),
    fitzgerald_showit_url('270/5HGF1XZ8S5K8taOe0J7ICw/51489/tec_petaja_photography_lecollectif-36.jpg'),
    fitzgerald_showit_url('270/ayYOwBu7TpizP6lwgDbnPQ/51489/tec_petaja_photography_lecollectif-18.jpg'),
    fitzgerald_showit_url('270/1sm11aFRTjuCjzgHvwLYpA/51489/6.jpg'),
];

$blocks = [];

$blocks[] = fitzgerald_block('header', 6, $theme, [
    'brand' => 'Fitzgerald',
    'brandUrl' => fitzgerald_site_url('/'),
    'navLayout' => 'split-brand-center',
    'splitNavAt' => 4,
    'splitHideTagline' => true,
    'splitHideCta' => true,
    'tagline' => '',
    'sticky' => true,
    'ctaLabel' => '',
    'ctaUrl' => fitzgerald_site_url('/contact'),
    'links' => $headerLinks,
    'textColor' => '#070707',
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 92,
    'fontScale' => 82,
    'verticalPaddingScale' => 0,
]);

$blocks[] = fitzgerald_block('hero', 5, $theme, [
    'heading' => 'The New Romantics',
    'subheading' => 'Acclaimed international wedding film photography for the new romantics.',
    'ctaLabel' => 'Explore the Work',
    'ctaUrl' => fitzgerald_site_url('/portfolio'),
    'secondaryLabel' => '',
    'secondaryUrl' => '',
    'imageMode' => 'manual',
    'imageFit' => 'cover',
    'imagePosition' => 'center',
    'imageUrl' => fitzgerald_showit_url('2400/RRr87m7tT3qx49LSSfitsg/51489/lecollectif-34_cropped.jpg'),
    'parallax' => true,
    'bullets' => [],
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
    'verticalPaddingScale' => 0,
    'widthPercent' => 100,
]);

$blocks[] = fitzgerald_block('content', 2, $theme, [
    'sectionTitle' => 'We make timeless images',
    'sectionTitleSecondary' => 'for brides and grooms with classic style.',
    'columnOneText' => 'Welcome to wedding photography done differently.',
    'columnTwoText' => 'Ut tbh nostrud succulents cillum waistcoat neutra labore reprehenderit gatekeep non readymade.',
    'sideLabel' => 'studio fitz',
    'imageColumn' => 3,
    'imageMode' => 'manual',
    'imageUrl' => fitzgerald_showit_url('800/XmFeL3LGQc-c0-ZfNMoAIA/51489/lecollectif-123.jpg'),
    'decorImageUrl' => fitzgerald_showit_url('file/RNDYiLclRy2z3g_twERQtw/51489/fp_mark_frame.svg'),
    'items' => [],
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
    'verticalPaddingScale' => 10,
]);

$blocks[] = fitzgerald_block('services', 8, $theme, [
    'sectionTitle' => ' ',
    'sectionSubtitle' => '',
    'columns' => 1,
    'items' => [
        [
            'title' => 'Weddings',
            'text' => 'Signature coverage',
            'price' => 'Signature',
            'icon' => '',
            'linkLabel' => 'View Service',
            'linkUrl' => fitzgerald_site_url('/specific-service'),
        ],
        [
            'title' => 'Portraits',
            'text' => 'Refined editorial sets',
            'price' => 'Editorial',
            'icon' => '',
            'linkLabel' => 'View Service',
            'linkUrl' => fitzgerald_site_url('/specific-service'),
        ],
        [
            'title' => 'Editorial',
            'text' => 'Campaign and print',
            'price' => 'Campaign',
            'icon' => '',
            'linkLabel' => 'View Service',
            'linkUrl' => fitzgerald_site_url('/specific-service'),
        ],
        [
            'title' => 'Brands',
            'text' => 'Fashion-minded storytelling',
            'price' => 'Commercial',
            'icon' => '',
            'linkLabel' => 'View Service',
            'linkUrl' => fitzgerald_site_url('/specific-service'),
        ],
    ],
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
    'fontScale' => 132,
    'verticalPaddingScale' => 0,
]);

$blocks[] = fitzgerald_block('cta', 8, $theme, [
    'heading' => 'Ways to Work Together',
    'text' => "We're known for professional, timely communication and stunning imagery, because you shouldn't have to choose.",
    'buttonLabel' => 'Fitzgerald Weddings',
    'buttonUrl' => fitzgerald_site_url('/specific-service'),
    'secondaryLabel' => 'The Experience',
    'secondaryUrl' => fitzgerald_site_url('/experience'),
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
    'verticalPaddingScale' => 0,
]);

$blocks[] = fitzgerald_block('cta', 8, $theme, [
    'heading' => 'Roselyn Fitzgerald',
    'text' => 'As an internationally-lauded wedding photographer with decades of experience, Roselyn (Rose, for short) brings her signature timeless, editorial style and classic, romantic aesthetic to modern love stories.',
    'buttonLabel' => 'Learn More',
    'buttonUrl' => fitzgerald_site_url('/about'),
    'secondaryLabel' => '',
    'secondaryUrl' => '',
], [
    'backgroundColor' => '#253320',
    'cardColor' => '#253320',
    'cardBackgroundOpacity' => 100,
    'verticalPaddingScale' => 12,
]);

$blocks[] = fitzgerald_block('cta', 3, $theme, [
    'heading' => 'We make stunning images of your most timeless moments',
    'text' => 'Talk a little about what makes you different right here. Ut tbh nostrud DSA succulents cillum waistcoat neutra labore reprehenderit gatekeep.',
    'buttonLabel' => '',
    'buttonUrl' => '',
    'secondaryLabel' => '',
    'secondaryUrl' => '',
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
    'fontScale' => 92,
]);

$blocks[] = fitzgerald_block('testimonials', 8, $theme, [
    'sectionTitle' => 'Kind words from',
    'sectionSubtitle' => '',
    'columns' => 1,
    'items' => [
        [
            'quote' => "No one sees light or captures a fleeting moment like Fitzgerald. I can’t imagine someone truly seeing our day like they did.",
            'name' => 'Jen & Aaron',
            'role' => 'Past clients',
            'rating' => 5,
        ],
    ],
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
]);

$blocks[] = fitzgerald_block('carousel', 2, $theme, [
    'eyebrow' => 'Explore',
    'sectionTitle' => "FITZGERALD\nWEDDINGS",
    'sectionSubtitle' => 'Talk a little about what makes you different right here. Ut tbh nostrud DSA succulents cillum waistcoat neutra labore reprehenderit gatekeep.',
    'buttonLabel' => 'Browse the Work',
    'buttonUrl' => fitzgerald_site_url('/portfolio'),
    'slides' => array_map(static fn (string $url): array => [
        'title' => '',
        'text' => '',
        'label' => '',
        'imageMode' => 'manual',
        'imageUrl' => $url,
    ], $topFilmstrip),
    'secondarySlides' => array_map(static fn (string $url): array => [
        'title' => '',
        'text' => '',
        'label' => '',
        'imageMode' => 'manual',
        'imageUrl' => $url,
    ], $bottomFilmstrip),
], [
    'backgroundColor' => '#070707',
    'cardColor' => '#070707',
    'cardBackgroundOpacity' => 100,
    'verticalPaddingScale' => 0,
    'widthPercent' => 100,
]);

$blocks[] = fitzgerald_block('cards', 8, $theme, [
    'sectionTitle' => 'Featured Projects',
    'sectionSubtitle' => '',
    'columns' => 2,
    'cards' => [
        [
            'badge' => '01',
            'title' => "Ben & Rachel's Modern Wedding at Blackberry Farm",
            'text' => 'A restrained, elegant story with a cinematic lead image and room for the editorial details to breathe.',
            'linkUrl' => fitzgerald_site_url('/single-post-demo-delete'),
            'linkLabel' => 'View More',
            'imageMode' => 'manual',
            'imageUrl' => fitzgerald_showit_url('400/gWuMW6Q_QHaPRtMdTCa7sw/51489/lecollectif-102.jpg'),
        ],
        [
            'badge' => '02',
            'title' => 'These Could Also Be Blog Post Titles If Preferred',
            'text' => 'The live template pairs visual storytelling with editorial-style titles and short, quiet descriptions.',
            'linkUrl' => fitzgerald_site_url('/single-post-demo-delete'),
            'linkLabel' => 'View More',
            'imageMode' => 'manual',
            'imageUrl' => fitzgerald_showit_url('1200/1EUFZnqYRM28QCoKhxwUwA/51489/tec_petaja_photography_lecollectif-20.jpg'),
        ],
        [
            'badge' => '03',
            'title' => 'Behind the Scenes of This 3.4 Million Dollar Wedding',
            'text' => 'A cleaner, drier SiteKit mapping that keeps the visual hierarchy without inflating the payload.',
            'linkUrl' => fitzgerald_site_url('/single-post-demo-delete'),
            'linkLabel' => 'View More',
            'imageMode' => 'manual',
            'imageUrl' => fitzgerald_showit_url('1200/i1E689XkRiO_1lAy5xkWpg/51489/tec_petaja_photography_lecollectif-24.jpg'),
        ],
    ],
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
]);

$blocks[] = fitzgerald_block('cta', 6, $theme, [
    'layoutMode' => 'overlay-card',
    'eyebrow' => 'your inbox just got much, much prettier',
    'heading' => 'The Newsletter',
    'text' => 'A quiet opt-in section with the original Fitzgerald background image and a single clear action.',
    'imageMode' => 'manual',
    'imageUrl' => fitzgerald_showit_url('2400/yRAnEeoeS_uZfll9ORlubQ/51489/lecollectif-67.jpg'),
    'buttonLabel' => 'Free Download',
    'buttonUrl' => fitzgerald_site_url('/long-form-freebie'),
    'secondaryLabel' => '',
    'secondaryUrl' => '',
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
]);

$blocks[] = fitzgerald_block('cards', 1, $theme, [
    'sectionTitle' => 'More to Explore',
    'sectionSubtitle' => '',
    'columns' => 2,
    'cards' => [
        [
            'badge' => 'Journal',
            'title' => 'My 5 Favorite Venues in Southern Tennessee',
            'text' => '',
            'linkUrl' => fitzgerald_site_url('/single-post-demo-delete'),
            'linkLabel' => 'Read on the Blog',
            'imageMode' => 'manual',
            'imageUrl' => fitzgerald_showit_url('file/7Hj3x5JQRmiLPMwwFFNV-A/51489/lecollectif-44.jpg'),
        ],
        [
            'badge' => 'Journal',
            'title' => "You Don't Need *This* $$$ Wedding Vendor",
            'text' => '',
            'linkUrl' => fitzgerald_site_url('/single-post-demo-delete'),
            'linkLabel' => 'Read on the Blog',
            'imageMode' => 'manual',
            'imageUrl' => fitzgerald_showit_url('file/51qKNGppTOiUb9bbsUpy6w/51489/lecollectif-77.jpg'),
        ],
    ],
], [
    'backgroundColor' => '#eae7e1',
    'cardBackgroundOpacity' => 0,
]);

$blocks[] = fitzgerald_block('cta', 6, $theme, [
    'layoutMode' => 'overlay-card',
    'eyebrow' => "let's make magic",
    'heading' => "Romance isn't dead",
    'text' => "We'd love to work with you. In order to create from a place of inspiration, we accept a limited number of commissions each year. Inquire for collections and to reserve your date.",
    'imageMode' => 'manual',
    'imageUrl' => fitzgerald_showit_url('1200/51Y0sHxQQDudXJPm8KgVBQ/51489/lecollectif-150.jpg'),
    'buttonLabel' => "Let's Do This",
    'buttonUrl' => fitzgerald_site_url('/contact'),
    'secondaryLabel' => '',
    'secondaryUrl' => '',
], [
    'backgroundColor' => '#253320',
    'cardBackgroundOpacity' => 0,
]);

$blocks[] = fitzgerald_block('gallery', 8, $theme, [
    'layoutMode' => 'social-grid',
    'sectionTitle' => ' ',
    'introText' => '',
    'images' => array_map(static function (string $url, int $index): array {
        return [
            'imageMode' => 'manual',
            'url' => $url,
            'alt' => 'Footer image ' . ($index + 1),
            'caption' => '',
        ];
    }, $footerStripImages, array_keys($footerStripImages)),
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
    'verticalPaddingScale' => 0,
    'fontScale' => 70,
]);

$blocks[] = fitzgerald_block('footer', 8, $theme, [
    'heading' => 'Fitzgerald',
    'brand' => '',
    'tagline' => 'Luxury wedding photographer for the stylish, soulful, and romantic. Based in England, traveling worldwide.',
    'blurb' => '',
    'legalLine' => '',
    'copyright' => '',
    'creditsSegments' => [
        ['text' => '© Tonic Site Shop 2024 | '],
        ['text' => 'Site Credit', 'url' => fitzgerald_site_url('/site-credit')],
    ],
    'ctaLabel' => '',
    'ctaUrl' => '',
    'links' => $footerLinks,
    'textColor' => '#070707',
], [
    'backgroundColor' => '#f9f9f5',
    'cardBackgroundOpacity' => 0,
    'verticalPaddingScale' => 0,
    'fontScale' => 88,
]);

$page = [
    'id' => 'home',
    'title' => 'Home',
    'menuLabel' => 'Home',
    'slug' => 'home',
    'locale' => 'en',
    'showInHeaderNav' => true,
    'showInFooterNav' => true,
    'sharedContact' => app_normalize_shared_contact_profile([
        'email' => 'hello@fitzgeraldweddings.com',
        'phone' => '',
        'address' => '',
        'locationLabel' => 'England',
        'locationNote' => 'Traveling worldwide',
        'latitude' => '',
        'longitude' => '',
    ], 'en'),
    'canvas' => [
        'lineHeightScale' => 100,
        'spacingXScale' => 100,
        'spacingYScale' => 120,
        'elementSpacingScale' => 100,
        'gradientOpacity' => 100,
        'linkAnimation' => 'none',
        'containerMaxWidthDesktop' => 94,
        'containerMaxWidthTablet' => 96,
        'containerMaxWidthMobile' => 100,
        'footerFollowContainerMaxWidth' => false,
    ],
    'blocks' => $blocks,
];

$payload = [
    'version' => '2.0',
    'theme' => $theme,
    'site' => [
        'name' => 'Fitzgerald',
        'typeId' => 'fitzgerald-showit',
        'activePageId' => 'home',
        'baseUrl' => 'https://fitzgerald.tonicsiteshop.com/',
        'canvas' => $page['canvas'],
        'pages' => [$page],
    ],
    'page' => $page,
];

$exportable = fitzgerald_strip_empty_payload_values(app_exportable_page_json($payload));
$json = json_encode($exportable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
file_put_contents(__DIR__ . '/../fitzgerald.json', $json . PHP_EOL);

fwrite(STDOUT, "Wrote fitzgerald.json (" . strlen($json) . " bytes)\n");
