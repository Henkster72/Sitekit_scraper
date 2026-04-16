<?php
declare(strict_types=1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/blocks.php';

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function attr($value): string
{
    return esc($value);
}

function resolve_color(string $value, array $theme = []): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^var\(--[a-zA-Z0-9\-]+\)$/', $value) === 1) {
        return $value;
    }

    if (preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $value) === 1) {
        return $value;
    }

    if (preg_match('/^(rgb|rgba|hsl|hsla)\(.+\)$/', $value) === 1) {
        return $value;
    }

    return $value;
}

function app_theme_value(array $theme, array $path, $default = '')
{
    $value = $theme;
    foreach ($path as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function app_font_family_name(string $family): string
{
    $family = trim($family);
    if ($family === '') {
        return '';
    }

    if (preg_match('/["\']([^"\']+)["\']/', $family, $matches) === 1) {
        $candidate = trim($matches[1]);
    } else {
        $parts = explode(',', $family);
        $candidate = trim((string) ($parts[0] ?? ''));
    }

    $candidate = trim($candidate, '"\' ');
    $generic = ['serif', 'sans-serif', 'monospace', 'system-ui', 'cursive', 'fantasy'];

    return in_array(strtolower($candidate), $generic, true) ? '' : $candidate;
}

function google_fonts_links(array $theme): string
{
    $heading = (string) app_theme_value($theme, ['typography', 'heading', 'family'], '');
    $body = (string) app_theme_value($theme, ['typography', 'body', 'family'], '');

    $families = [];
    foreach ([$heading, $body] as $family) {
        $name = app_font_family_name($family);
        if ($name !== '' && !in_array($name, $families, true)) {
            $families[] = $name;
        }
    }

    if ($families === []) {
        return '';
    }

    $queries = [];
    foreach ($families as $name) {
        $encoded = str_replace('%20', '+', rawurlencode($name));
        $queries[] = 'family=' . $encoded . ':wght@300;400;500;600;700;800;900';
    }

    $href = 'https://fonts.googleapis.com/css2?' . implode('&', $queries) . '&display=swap';

    return '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="' . attr($href) . '" rel="stylesheet">';
}

function app_resolve_theme_css_value(string $value, array $vars, int $depth = 0): string
{
    $trimmed = trim($value);
    if ($trimmed === '' || $depth > 8) {
        return $trimmed;
    }

    if (preg_match('/^var\((--[a-zA-Z0-9\-]+)\)$/', $trimmed, $matches) === 1) {
        $token = $matches[1];
        if (isset($vars[$token])) {
            return app_resolve_theme_css_value((string) $vars[$token], $vars, $depth + 1);
        }
    }

    return $trimmed;
}

function app_color_to_rgb(string $value): ?array
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return null;
    }

    if (preg_match('/^#([a-f0-9]{3})$/', $value, $matches) === 1) {
        $hex = $matches[1];
        return [
            hexdec(str_repeat($hex[0], 2)),
            hexdec(str_repeat($hex[1], 2)),
            hexdec(str_repeat($hex[2], 2)),
        ];
    }

    if (preg_match('/^#([a-f0-9]{6}|[a-f0-9]{8})$/', $value, $matches) === 1) {
        $hex = substr($matches[1], 0, 6);
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    if (preg_match('/^rgba?\((.+)\)$/', $value, $matches) !== 1) {
        return null;
    }

    $parts = array_map('trim', explode(',', $matches[1]));
    if (count($parts) < 3) {
        return null;
    }

    $rgb = [];
    for ($index = 0; $index < 3; $index += 1) {
        $part = $parts[$index];
        if (str_ends_with($part, '%')) {
            $percentage = (float) rtrim($part, '%');
            $rgb[] = (int) max(0, min(255, round(($percentage / 100) * 255)));
            continue;
        }
        if (!is_numeric($part)) {
            return null;
        }
        $rgb[] = (int) max(0, min(255, round((float) $part)));
    }

    return $rgb;
}

function app_relative_luminance(array $rgb): float
{
    $channels = array_map(static function ($channel): float {
        $value = max(0, min(255, (int) $channel)) / 255;
        if ($value <= 0.03928) {
            return $value / 12.92;
        }
        return (($value + 0.055) / 1.055) ** 2.4;
    }, $rgb);

    return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
}

function app_pick_readable_text_color(string $background, string $dark = '#111827', string $light = '#ffffff'): string
{
    $rgb = app_color_to_rgb($background);
    if ($rgb === null) {
        return $dark;
    }

    return app_relative_luminance($rgb) > 0.52 ? $dark : $light;
}

function theme_css_vars(array $theme): string
{
    $palette = is_array($theme['palette'] ?? null) ? $theme['palette'] : [];
    $colors = is_array($theme['theme']['colors'] ?? null) ? $theme['theme']['colors'] : [];
    $gradient = is_array($theme['gradient'] ?? null) ? $theme['gradient'] : [];
    $elements = is_array($theme['elements'] ?? null) ? $theme['elements'] : [];

    $heading = is_array($theme['typography']['heading'] ?? null) ? $theme['typography']['heading'] : [];
    $body = is_array($theme['typography']['body'] ?? null) ? $theme['typography']['body'] : [];
    $link = is_array($theme['typography']['link'] ?? null) ? $theme['typography']['link'] : [];

    $card = is_array($elements['card'] ?? null) ? $elements['card'] : [];
    $button = is_array($elements['button'] ?? null) ? $elements['button'] : [];
    $input = is_array($elements['input'] ?? null) ? $elements['input'] : [];
    $hr = is_array($elements['hr'] ?? null) ? $elements['hr'] : [];
    $themeColorValue = static function (string $key, string $fallback) use ($colors): string {
        $value = (string) ($colors[$key] ?? $fallback);
        $trimmed = trim($value);
        if ($trimmed === '' || strtolower($trimmed) === strtolower('var(--' . $key . ')')) {
            return $fallback;
        }
        return $value;
    };

    $buttonColor = (string) ($button['color'] ?? 'var(--primary)');
    if (strtolower(trim($buttonColor)) === 'var(--button-color)' || trim($buttonColor) === '') {
        $buttonColor = 'var(--primary)';
    }
    $cardColor = (string) ($card['color'] ?? 'var(--surface)');
    if (trim($cardColor) === '') {
        $cardColor = 'var(--surface)';
    }

    $themeShadow = trim((string) app_theme_value($theme, ['theme', 'effects', 'shadow'], ''));
    if ($themeShadow === '') {
        $themeShadow = 'none';
    }
    $cardShadow = trim((string) ($card['shadow'] ?? $themeShadow));
    if ($cardShadow === '') {
        $cardShadow = 'none';
    }
    $cardHoverShadow = trim((string) ($card['hoverShadow'] ?? $cardShadow));
    if ($cardHoverShadow === '') {
        $cardHoverShadow = $cardShadow;
    }
    $buttonShadow = trim((string) ($button['shadow'] ?? $themeShadow));
    if ($buttonShadow === '') {
        $buttonShadow = 'none';
    }
    $buttonHoverShadow = trim((string) ($button['hoverShadow'] ?? $buttonShadow));
    if ($buttonHoverShadow === '') {
        $buttonHoverShadow = $buttonShadow;
    }

    $buttonBorderColor = (string) ($button['borderColor'] ?? $buttonColor);
    if (trim($buttonBorderColor) === '') {
        $buttonBorderColor = $buttonColor;
    }
    $secondaryButtonBackground = (string) ($button['secondaryBackground'] ?? 'transparent');
    if (trim($secondaryButtonBackground) === '') {
        $secondaryButtonBackground = 'transparent';
    }

    $headingSize = max(16, (float) ($heading['size'] ?? 60));
    $headingH1Color = (string) ($heading['color'] ?? $themeColorValue('text', '#111827'));
    $headingH2Color = (string) ($heading['h2Color'] ?? $headingH1Color);
    $headingH3PlusColor = (string) ($heading['h3PlusColor'] ?? $headingH2Color);

    $vars = [
        '--base' => resolve_color((string) ($palette['base'] ?? '#2563eb')),
        '--lighter' => resolve_color((string) ($palette['lighter'] ?? '#93c5fd')),
        '--darker' => resolve_color((string) ($palette['darker'] ?? '#1e3a8a')),
        '--complementary' => resolve_color((string) ($palette['complementary'] ?? '#f97316')),
        '--splitComplementary' => resolve_color((string) ($palette['splitComplementary'] ?? '#14b8a6')),
        '--triadic' => resolve_color((string) ($palette['triadic'] ?? '#8b5cf6')),
        '--background' => resolve_color($themeColorValue('background', (string) ($palette['background'] ?? '#f8fafc'))),
        '--surface' => resolve_color($themeColorValue('surface', '#ffffff')),
        '--primary' => resolve_color($themeColorValue('primary', 'var(--base)')),
        '--secondary' => resolve_color($themeColorValue('secondary', 'var(--darker)')),
        '--text' => resolve_color($themeColorValue('text', '#111827')),
        '--textMuted' => resolve_color($themeColorValue('textMuted', '#64748b')),
        '--border' => resolve_color($themeColorValue('border', '#d1d5db')),
        '--theme-shadow' => $themeShadow,
        '--grad-angle' => (string) ((int) ($gradient['angle'] ?? 135)) . 'deg',
        '--grad-start' => resolve_color((string) ($gradient['gradStart'] ?? 'var(--primary)')),
        '--grad-end' => resolve_color((string) ($gradient['gradEnd'] ?? 'var(--background)')),
        '--accent' => resolve_color((string) ($gradient['accent'] ?? 'var(--complementary)')),
        '--card-opacity' => (string) ((float) ($card['opacity'] ?? 1)),
        '--card-radius' => (string) ((int) ($card['radius'] ?? 14)) . 'px',
        '--card-color' => resolve_color($cardColor),
        '--card-border-color' => resolve_color((string) ($card['borderColor'] ?? 'var(--border)')),
        '--card-shadow' => $cardShadow,
        '--card-shadow-hover' => $cardHoverShadow,
        '--button-radius' => (string) ((int) ($button['radius'] ?? 10)) . 'px',
        '--button-color' => resolve_color($buttonColor),
        '--button-opacity' => (string) max(0, min(1, (float) ($button['opacity'] ?? 1))),
        '--button-border-color' => resolve_color($buttonBorderColor),
        '--button-secondary-bg' => resolve_color($secondaryButtonBackground),
        '--button-secondary-border-color' => resolve_color((string) ($button['secondaryBorderColor'] ?? $buttonBorderColor)),
        '--button-shadow' => $buttonShadow,
        '--button-shadow-hover' => $buttonHoverShadow,
        '--input-background' => resolve_color((string) ($input['background'] ?? 'var(--surface)')),
        '--input-border-color' => resolve_color((string) ($input['borderColor'] ?? 'var(--border)')),
        '--hr-color' => resolve_color((string) ($hr['color'] ?? 'var(--border)')),
        '--border-width' => (string) ((int) ($elements['borderWidth'] ?? 1)) . 'px',
        '--font-heading' => (string) ($heading['family'] ?? '"Outfit", sans-serif'),
        '--font-body' => (string) ($body['family'] ?? '"Inter", sans-serif'),
        '--body-size' => (string) ((float) ($body['size'] ?? 16)) . 'px',
        '--body-color' => resolve_color((string) ($body['color'] ?? 'var(--text)')),
        '--link-color' => resolve_color((string) ($link['color'] ?? 'var(--primary)')),
        '--heading-weight' => (string) ((int) ($heading['weight'] ?? 700)),
        '--body-weight' => (string) ((int) ($body['weight'] ?? 400)),
        '--heading-line-height' => (string) ((float) ($heading['lineHeight'] ?? 1.2)),
        '--body-line-height' => (string) ((float) ($body['lineHeight'] ?? 1.6)),
        '--heading-letter-spacing' => (string) ((float) ($heading['letterSpacing'] ?? 0)) . 'px',
        '--body-letter-spacing' => (string) ((float) ($body['letterSpacing'] ?? 0)) . 'px',
        '--heading-size-h1' => rtrim(rtrim(number_format($headingSize, 2, '.', ''), '0'), '.') . 'px',
        '--heading-size-h2' => rtrim(rtrim(number_format($headingSize * 0.70, 2, '.', ''), '0'), '.') . 'px',
        '--heading-size-h3' => rtrim(rtrim(number_format($headingSize * 0.55, 2, '.', ''), '0'), '.') . 'px',
        '--heading-size-h4' => rtrim(rtrim(number_format($headingSize * 0.45, 2, '.', ''), '0'), '.') . 'px',
        '--heading-size-h5' => rtrim(rtrim(number_format($headingSize * 0.38, 2, '.', ''), '0'), '.') . 'px',
        '--heading-size-h6' => rtrim(rtrim(number_format($headingSize * 0.32, 2, '.', ''), '0'), '.') . 'px',
        '--heading-color-h1' => resolve_color($headingH1Color),
        '--heading-color-h2' => resolve_color($headingH2Color),
        '--heading-color-h3' => resolve_color($headingH3PlusColor),
        '--heading-color-h4' => resolve_color($headingH3PlusColor),
        '--heading-color-h5' => resolve_color($headingH3PlusColor),
        '--heading-color-h6' => resolve_color($headingH3PlusColor),
    ];

    $resolvedVars = [];
    foreach ($vars as $name => $value) {
        $resolvedVars[$name] = app_resolve_theme_css_value((string) $value, $vars);
    }

    $resolvedButtonColor = $resolvedVars['--button-color'] ?? (string) $vars['--button-color'];
    $resolvedSecondaryButtonBackground = $resolvedVars['--button-secondary-bg'] ?? (string) $vars['--button-secondary-bg'];
    $resolvedTextColor = $resolvedVars['--text'] ?? '#111827';

    $buttonTextColor = (string) ($button['textColor'] ?? app_pick_readable_text_color($resolvedButtonColor, $resolvedTextColor));
    $secondaryButtonTextColor = (string) ($button['secondaryTextColor'] ?? (
        strtolower($resolvedSecondaryButtonBackground) === 'transparent'
            ? (string) $vars['--text']
            : app_pick_readable_text_color($resolvedSecondaryButtonBackground, $resolvedTextColor)
    ));

    $vars['--button-text-color'] = resolve_color($buttonTextColor);
    $vars['--button-secondary-text-color'] = resolve_color($secondaryButtonTextColor);
    foreach ($vars as $name => $value) {
        $resolvedVars[$name] = app_resolve_theme_css_value((string) $value, $vars);
    }

    $lines = [];
    foreach ($resolvedVars as $name => $value) {
        $safeValue = str_replace(['<', '>'], '', (string) $value);
        $lines[] = $name . ':' . $safeValue;
    }

    return ':root{' . implode(';', $lines) . ';}';
}


function preview_base_css(): string
{
    return <<<'CSS'
*{box-sizing:border-box}
html,body{margin:0;padding:0;height:100%;overflow:hidden;background:var(--background);color:var(--body-color);font-family:var(--font-body);font-size:var(--body-size);font-weight:var(--body-weight);line-height:calc(var(--body-line-height) * var(--canvas-line-scale));letter-spacing:var(--body-letter-spacing);position:relative}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:0;background:linear-gradient(var(--grad-angle),var(--grad-start),var(--grad-end));opacity:var(--canvas-gradient-opacity)}
a{color:var(--link-color);text-decoration:none}
a:visited{color:var(--link-color)}
.preview-block .block a:not(.btn):not(.socialbar-btn):not(.preview-site-link):not(.preview-site-link-delete){
  color:var(--link-color);
}
.preview-block .block a:not(.btn):not(.socialbar-btn):not(.preview-site-link):not(.preview-site-link-delete):visited{
  color:var(--link-color);
}
body[data-link-animation]:not([data-link-animation="none"]) a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete){
  position:relative;
  display:inline-block;
  vertical-align:baseline;
  overflow:visible;
  color:var(--link-color);
  text-decoration:none;
  line-height:inherit;
  transition:color .3s ease;
}
body[data-link-animation]:not([data-link-animation="none"]) a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before,
body[data-link-animation]:not([data-link-animation="none"]) a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::after{
  pointer-events:none;
}
body[data-link-animation="underline"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover,
body[data-link-animation="underline"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible{
  text-decoration:underline;
  text-decoration-thickness:2px;
  text-underline-offset:.24em;
}
body[data-link-animation="underlineSweep"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before{
  content:"";
  position:absolute;
  left:0;
  top:auto;
  bottom:-.22em;
  width:100%;
  height:2px;
  background:currentColor;
  transform-origin:100% 50%;
  transform:scaleX(0);
  transition:transform .3s ease;
}
body[data-link-animation="underlineSweep"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::before,
body[data-link-animation="underlineSweep"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::before{
  transform-origin:0% 50%;
  transform:scaleX(1);
}
body[data-link-animation="splitLift"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before{
  content:"";
  position:absolute;
  left:0;
  top:auto;
  bottom:-.22em;
  width:100%;
  height:2px;
  background:currentColor;
  transform-origin:50% 100%;
  clip-path:polygon(0% 0%,0% 100%,0% 100%,0% 0%,100% 0%,100% 100%,0% 100%,0% 100%,100% 100%,100% 0%);
  transition:clip-path .3s ease,transform .3s cubic-bezier(.2,1,.8,1);
}
body[data-link-animation="splitLift"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::before,
body[data-link-animation="splitLift"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::before{
  transform:scale3d(1.08,3,1);
  clip-path:polygon(0% 0%,0% 100%,50% 100%,50% 0%,50% 0%,50% 100%,50% 100%,0% 100%,100% 100%,100% 0%);
}
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete){
  z-index:0;
  isolation:isolate;
  overflow:visible;
}
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before,
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::after{
  content:"";
  position:absolute;
  top:50%;
  left:50%;
  border-radius:50%;
  opacity:0;
  z-index:0;
  transition:transform .3s ease,opacity .3s ease;
}
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before{
  width:100px;
  height:100px;
  border:2px solid color-mix(in srgb,currentColor 28%,transparent);
  transform:translate(-50%,-50%) scale(.2);
}
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::after{
  width:90px;
  height:90px;
  border:6px solid color-mix(in srgb,currentColor 20%,transparent);
  transform:translate(-50%,-50%) scale(.8);
}
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::before,
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::after,
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::before,
body[data-link-animation="rippleCircle"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::after{
  opacity:.35;
  transform:translate(-50%,-50%) scale(1);
}
body[data-link-animation="dotReveal"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before{
  content:"•";
  position:absolute;
  top:calc(100% + .22em);
  left:50%;
  transform:translateX(-50%) scale(.68);
  font-size:.8em;
  line-height:1;
  opacity:0;
  color:currentColor;
  text-shadow:-.5em 0 currentColor,.5em 0 currentColor;
  transition:opacity .24s ease,transform .32s ease;
}
body[data-link-animation="dotReveal"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::before,
body[data-link-animation="dotReveal"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::before{
  opacity:.9;
  transform:translateX(-50%) scale(1);
}
body[data-link-animation="pillRise"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before{
  content:"";
  position:absolute;
  left:0;
  top:calc(100% + .16em);
  width:100%;
  height:3px;
  background:currentColor;
  border-radius:999px;
  transform:scale3d(1,.24,1);
  transform-origin:50% 100%;
  opacity:.92;
  transition:transform .28s cubic-bezier(.22,1,.36,1),opacity .2s ease;
}
body[data-link-animation="pillRise"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::before,
body[data-link-animation="pillRise"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::before{
  transform:scale3d(1.14,1,1);
  opacity:1;
}
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete){
  display:inline-flex;
  align-items:center;
}
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before,
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::after{
  display:inline-block;
  opacity:0;
  transition:transform .3s ease,opacity .2s ease;
}
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::before{
  content:"[";
  margin-right:.45em;
  transform:translateX(20px);
}
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete)::after{
  content:"]";
  margin-left:.45em;
  transform:translateX(-20px);
}
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::before,
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):hover::after,
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::before,
body[data-link-animation="bracketsSlide"] a:not(.btn):not(.socialbar-btn):not(.preview-site-link-delete):focus-visible::after{
  opacity:1;
  transform:translateX(0);
}
hr{border:0;border-top:var(--border-width) solid var(--hr-color);opacity:var(--block-fg-opacity)}
img{max-width:100%;display:block;height:auto}
button,input,select,textarea{font:inherit;color:inherit}
.pi{font-style:normal;display:inline-flex;align-items:center;justify-content:center}
.site-main{position:relative;z-index:1;height:100%;overflow-y:auto;overflow-x:hidden;scrollbar-gutter:stable}
.preview-block{position:relative;border:2px solid transparent;border-radius:0;margin:4px 0;transition:border-color .2s ease,box-shadow .2s ease;--block-background-opacity:1;--block-card-bg-opacity:1;--block-fg-opacity:1;--block-font-scale:1;--block-width-percent:75;--block-pad-y-scale:1;--block-container-max-scale:var(--canvas-container-max-scale,1)}
.preview-block.is-container-max-exempt{--block-container-max-scale:1}
.preview-block.is-sticky-header{position:sticky;top:0;z-index:14}
.preview-block .block-header.is-sticky-block{position:static}
.preview-block:hover{border-color:color-mix(in srgb,var(--primary) 28%,transparent)}
.preview-block.is-selected{border-color:var(--accent);box-shadow:0 0 0 2px color-mix(in srgb,var(--accent) 20%,transparent)}
.preview-block.is-dragging{opacity:.6}
.preview-block.drag-over-before{box-shadow:inset 0 4px 0 var(--accent)}
.preview-block.drag-over-after{box-shadow:inset 0 -4px 0 var(--accent)}
.preview-image-drop-target{position:relative}
.preview-image-drop-target.is-image-drop-over{outline:2px dashed var(--accent);outline-offset:3px;border-radius:12px}
.preview-image-drop-target.is-image-drop-over::after{content:"Drop image";position:absolute;right:10px;bottom:10px;z-index:12;padding:6px 10px;border-radius:999px;background:rgba(17,24,39,.92);color:#fff;font:700 11px/1 "Public Sans",system-ui,sans-serif;letter-spacing:.04em;pointer-events:none}
.preview-block-label{position:absolute;top:8px;left:8px;z-index:9;display:inline-flex;align-items:center;gap:6px;min-height:28px;padding:0 10px;border-radius:999px;border:1px solid rgba(255,255,255,.82);background:#111827;color:#fff;font:700 11px/1 "Public Sans",system-ui,sans-serif;letter-spacing:.04em;box-shadow:0 8px 20px rgba(15,23,42,.28);cursor:pointer;appearance:none;-webkit-appearance:none}
.preview-block-label .pi{font-size:12px;line-height:1}
.preview-block-actions{position:absolute;top:8px;right:8px;z-index:9;display:flex;gap:6px;opacity:0;transform:translateY(-4px);transition:opacity .16s ease,transform .16s ease}
.preview-block:hover .preview-block-actions,.preview-block.is-selected .preview-block-actions{opacity:1;transform:translateY(0)}
.preview-action{width:28px;height:28px;border-radius:999px;border:1px solid #111827;background:rgba(255,255,255,.98);color:#111827;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 8px 18px rgba(15,23,42,.18)}
.preview-action:hover{background:#111827;border-color:#111827;color:#fff}
.preview-action:focus-visible{outline:2px solid #111827;outline-offset:2px}
.preview-action.danger{border-color:#b91c1c;color:#b91c1c}
.preview-action.danger:hover{background:#dc2626;border-color:#dc2626;color:#fff}
.preview-editable-text{position:relative;cursor:text}
.preview-editable-text:focus{outline:1px dashed #111827;outline-offset:2px;background:rgba(255,255,255,.22)}
.preview-editable-text[data-edit-mode="multiline"]{white-space:normal}
.preview-block .block{position:relative;isolation:isolate;font-size:calc(1rem * var(--block-font-scale))}
.preview-block .block::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;background:var(--block-background-fill,transparent);opacity:var(--block-background-opacity)}
.preview-block .block > *{position:relative;z-index:1}
.preview-block .block :is(h1,h2,h3,h4,h5,h6,p,a,li,strong,small,span,label,input,textarea,select,button,i,.pi,svg){opacity:var(--block-fg-opacity)}
.preview-block .block .pi{font-size:1em}
h1,h2,h3,h4,h5,h6{margin:0 0 .5em;font-family:var(--font-heading);font-weight:var(--heading-weight);line-height:calc(var(--heading-line-height) * var(--canvas-line-scale));letter-spacing:var(--heading-letter-spacing)}
h1{font-size:min(var(--heading-size-h1),calc(1.25rem + 4.5vw));color:var(--block-heading-color-h1,var(--heading-color-h1))}
h2{font-size:min(var(--heading-size-h2),calc(1.15rem + 3.25vw));color:var(--block-heading-color-h2,var(--heading-color-h2))}
h3{font-size:min(var(--heading-size-h3),calc(1.05rem + 2.2vw));color:var(--block-heading-color-h3,var(--heading-color-h3))}
h4{font-size:min(var(--heading-size-h4),calc(1rem + 1.6vw));color:var(--block-heading-color-h4,var(--heading-color-h4))}
h5{font-size:min(var(--heading-size-h5),calc(.98rem + 1vw));color:var(--block-heading-color-h5,var(--heading-color-h5))}
h6{font-size:min(var(--heading-size-h6),calc(.95rem + .7vw));color:var(--block-heading-color-h6,var(--heading-color-h6))}
p{margin:0 0 calc(1em * var(--canvas-inner-space-scale));color:var(--body-color)}
ul,ol{margin:0;padding-left:1.2rem}
li{margin:calc(.25rem * var(--canvas-inner-space-scale)) 0}
small{color:var(--textMuted)}
.block{position:relative;padding:calc(30px * var(--canvas-space-y-scale) * var(--block-pad-y-scale)) 0}
.block.compact{padding:calc(14px * var(--canvas-space-y-scale) * var(--block-pad-y-scale)) 0}
.container{width:calc(100% * var(--block-container-max-scale) * (var(--block-width-percent) / 100));max-width:100%;margin:0 auto}
.preview-block.is-full-width{border-radius:0}
.preview-block.is-full-width .container{width:100%;max-width:none}
.preview-block.is-full-width .surface,.preview-block.is-full-width .cta-band,.preview-block.is-full-width .hero-wrap,.preview-block.is-full-width .hero-copy,.preview-block.is-full-width .hero-visual,.preview-block.is-full-width .hero-visual img,.preview-block.is-full-width .hero-bg-media,.preview-block.is-full-width .hero-bg-media img,.preview-block.is-full-width .placeholder-image,.preview-block.is-full-width .carousel-item,.preview-block.is-full-width .split-row,.preview-block.is-full-width .announcement,.preview-block.is-full-width .block-nav,.preview-block.is-full-width footer,.preview-block.is-full-width .form-grid input,.preview-block.is-full-width .form-grid textarea,.preview-block.is-full-width .date-month-panel,.preview-block.is-full-width .date-week-card,.preview-block.is-full-width .faq-item,.preview-block.is-full-width .faq-support,.preview-block.is-full-width .location-map-embed,.preview-block.is-full-width .location-map-embed iframe{border-radius:0 !important}
.preview-block.is-full-width .block-hero.block-v1 .hero-copy,
.preview-block.is-full-width .block-hero.block-v2 .hero-copy,
.preview-block.is-full-width .block-hero.block-v4 .hero-copy{border-radius:var(--card-radius) !important}
.preview-block:not(.is-full-width) .block-header .block-nav,
.preview-block:not(.is-full-width) .block-footer footer.surface,
.preview-block:not(.is-full-width) .block-socialbar .socialbar-wrap.surface{
  border:var(--border-width) solid var(--border);
  border-radius:var(--card-radius);
}
.preview-block:not(.is-full-width) .block-header .block-nav{overflow:hidden}
.preview-block.is-full-width .block-header .block-nav{
  border-style:solid;
  border-color:var(--border);
  border-width:0 0 var(--border-width) 0;
}
.preview-block.is-full-width .block-footer footer.surface,
.preview-block.is-full-width .block-socialbar .socialbar-wrap.surface{
  border-width:0;
}
.surface{position:relative;overflow:hidden;background:transparent;border:var(--block-card-border-width,var(--border-width)) var(--block-card-border-style,solid) var(--block-card-border-color,var(--card-border-color));border-radius:var(--card-radius);padding:16px;box-shadow:var(--card-shadow)}
.surface::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;background:var(--block-card-fill-solid,var(--block-card-color,var(--card-color)));opacity:var(--block-card-bg-opacity)}
.surface > *{position:relative;z-index:1}
.card{height:100%}
.badge{display:inline-flex;align-items:center;gap:6px;width:max-content;font-size:12px;font-weight:600;line-height:1;color:var(--primary);background:color-mix(in srgb,var(--primary) 12%,transparent);border-radius:999px;padding:5px 10px}
.btn{appearance:none;-webkit-appearance:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;border-radius:var(--button-radius);border:var(--border-width) solid transparent;padding:10px 16px;font-weight:600;text-decoration:none;box-shadow:var(--button-shadow);transition:transform .16s ease,border-color .16s ease,background-color .16s ease,color .16s ease,box-shadow .16s ease}
.btn:hover{text-decoration:none;transform:translateY(-1px);box-shadow:var(--button-shadow-hover)}
.btn:focus-visible{outline:2px solid color-mix(in srgb,var(--primary) 42%,transparent);outline-offset:2px}
.btn-primary{background:color-mix(in srgb,var(--button-color,var(--primary)) calc(var(--button-opacity) * 100%),transparent);border-color:var(--button-border-color,var(--button-color,var(--primary)));color:var(--button-text-color)}
.btn-primary:hover{background:color-mix(in srgb,var(--button-color,var(--primary)) calc(var(--button-opacity) * 78%),var(--secondary));border-color:color-mix(in srgb,var(--button-border-color,var(--button-color,var(--primary))) 84%,var(--secondary))}
.btn-secondary{background:var(--button-secondary-bg);border-color:var(--button-secondary-border-color,var(--button-border-color,var(--border)));color:var(--button-secondary-text-color)}
.btn-secondary:hover{background:color-mix(in srgb,var(--button-secondary-bg) 84%,var(--background));border-color:var(--button-border-color,var(--button-color,var(--primary)));color:var(--button-secondary-text-color)}
.btn-light{background:#fff;border-color:#fff;color:var(--secondary)}
.btn-light:hover{background:color-mix(in srgb,#fff 90%,var(--background));color:var(--primary)}
.grid{display:grid;gap:calc(14px * var(--canvas-inner-space-scale))}
.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
.grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}
.grid-dynamic{grid-template-columns:repeat(var(--grid-columns,3),minmax(0,1fr))}
.placeholder-image{display:flex;align-items:center;justify-content:center;width:100%;min-height:160px;border:var(--border-width) dashed color-mix(in srgb,var(--border) 70%,transparent);border-radius:calc(var(--card-radius) - 2px);background:color-mix(in srgb,var(--background) 90%,var(--surface));color:var(--textMuted)}
.placeholder-image .pi{font-size:calc(34px * var(--block-font-scale))}
.preview-block .hero-visual img,.preview-block .hero-bg-media img{opacity:var(--block-background-opacity)}
.divider-line{display:block;border-top-style:solid;border-top-color:var(--hr-color)}
.divider-wave{width:100%;height:18px;border-top:var(--border-width) solid var(--hr-color);border-radius:999px 999px 0 0;opacity:.8}
.form-grid{display:grid;gap:calc(10px * var(--canvas-inner-space-scale))}
.form-grid input,.form-grid textarea{width:100%;border:var(--border-width) solid var(--input-border-color);border-radius:var(--card-radius);background:var(--input-background);color:var(--body-color);padding:10px 12px}
.form-grid input:focus,.form-grid textarea:focus{outline:2px solid color-mix(in srgb,var(--primary) 32%,transparent);outline-offset:1px}
.preview-block[data-block-animation]:not([data-block-animation="none"]) :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image){transition:opacity .68s cubic-bezier(.22,1,.36,1),transform .68s cubic-bezier(.22,1,.36,1);will-change:opacity,transform}
html.has-runtime .preview-block[data-block-animation]:not([data-block-animation="none"]) :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image){opacity:0}
html.has-runtime .preview-block[data-block-animation="fade-left"] :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image){transform:translate3d(-56px,0,0)}
html.has-runtime .preview-block[data-block-animation="fade-right"] :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image){transform:translate3d(56px,0,0)}
html.has-runtime .preview-block[data-block-animation="fade-up"] :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image){transform:translate3d(0,56px,0)}
html.has-runtime .preview-block[data-block-animation="fade-down"] :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image){transform:translate3d(0,-56px,0)}
html.has-runtime .preview-block[data-block-animation]:not([data-block-animation="none"]) :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image).is-visible{opacity:1;transform:translate3d(0,0,0)}
[data-stagger] > *{transition-delay:var(--stagger-delay,0ms)}
@media (prefers-reduced-motion: reduce){.preview-block[data-block-animation]:not([data-block-animation="none"]) :is(.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image){opacity:1 !important;transform:none !important;transition:none !important}}
@media (max-width: 980px){:root{--canvas-container-max-scale:var(--canvas-container-max-tablet,1)}.grid-4{grid-template-columns:repeat(2,minmax(0,1fr))}.grid-3{grid-template-columns:repeat(2,minmax(0,1fr))}.grid-dynamic{grid-template-columns:repeat(min(2,var(--grid-columns,3)),minmax(0,1fr))}}
@media (max-width: 720px){:root{--canvas-container-max-scale:var(--canvas-container-max-mobile,1)}.grid-2,.grid-3,.grid-4,.grid-dynamic{grid-template-columns:1fr}.block{padding:calc(22px * var(--canvas-space-y-scale) * var(--block-pad-y-scale)) 0}}
CSS;
}

function app_canvas_css_vars(array $page): string
{
    $canvas = is_array($page['canvas'] ?? null) ? $page['canvas'] : [];
    return ':root{' . app_canvas_css_declarations($canvas) . '}';
}

function app_canvas_link_animation(array $canvas): string
{
    return app_normalize_canvas_link_animation_value($canvas['linkAnimation'] ?? 'none');
}

function app_canvas_css_declarations(array $canvas): string
{
    $normalized = app_normalize_canvas_settings($canvas);
    $lineHeightScale = max(50, min(300, (float) ($normalized['lineHeightScale'] ?? 100))) / 100;
    $spacingXScale = max(50, min(300, (float) ($normalized['spacingXScale'] ?? 100))) / 100;
    $spacingYScale = (max(50, min(300, (float) ($normalized['spacingYScale'] ?? 100))) / 100) * 2.5;
    $elementSpacingScale = max(25, min(300, (float) ($normalized['elementSpacingScale'] ?? 100))) / 100;
    $gradientOpacity = max(0, min(100, (float) ($normalized['gradientOpacity'] ?? 100))) / 100;
    $containerMaxDesktop = max(20, min(100, (float) ($normalized['containerMaxWidthDesktop'] ?? 100))) / 100;
    $containerMaxTablet = max(20, min(100, (float) ($normalized['containerMaxWidthTablet'] ?? 100))) / 100;
    $containerMaxMobile = max(20, min(100, (float) ($normalized['containerMaxWidthMobile'] ?? 100))) / 100;
    $padX = 24 * $spacingXScale;

    return '--canvas-line-scale:' . $lineHeightScale
        . ';--canvas-space-y-scale:' . $spacingYScale
        . ';--canvas-inner-space-scale:' . $elementSpacingScale
        . ';--canvas-pad-x:' . $padX . 'px'
        . ';--canvas-container-max-desktop:' . $containerMaxDesktop
        . ';--canvas-container-max-tablet:' . $containerMaxTablet
        . ';--canvas-container-max-mobile:' . $containerMaxMobile
        . ';--canvas-container-max-scale:var(--canvas-container-max-desktop)'
        . ';--canvas-gradient-opacity:' . $gradientOpacity . ';';
}

function app_inline_style_once(string $key, string $css): void
{
    if (!isset($GLOBALS['__app_inline_styles']) || !is_array($GLOBALS['__app_inline_styles'])) {
        $GLOBALS['__app_inline_styles'] = [];
    }
    if (isset($GLOBALS['__app_inline_styles'][$key])) {
        return;
    }
    $GLOBALS['__app_inline_styles'][$key] = true;
    $trimmed = trim($css);
    if ($trimmed === '') {
        return;
    }
    echo '<style data-inline-style="' . attr($key) . '">' . $trimmed . '</style>';
}

function app_inline_script_once(string $key, string $script): void
{
    if (!isset($GLOBALS['__app_inline_scripts']) || !is_array($GLOBALS['__app_inline_scripts'])) {
        $GLOBALS['__app_inline_scripts'] = [];
    }
    if (isset($GLOBALS['__app_inline_scripts'][$key])) {
        return;
    }
    $GLOBALS['__app_inline_scripts'][$key] = true;
    $trimmed = trim($script);
    if ($trimmed === '') {
        return;
    }
    echo '<script data-inline-script="' . attr($key) . '">' . $trimmed . '</script>';
}

function app_browser_page_title(string $siteName, string $pageTitle): string
{
    $site = trim($siteName);
    $page = trim($pageTitle);

    if ($site !== '' && $page !== '') {
        return $site . ' - ' . $page;
    }
    if ($page !== '') {
        return $page;
    }
    if ($site !== '') {
        return $site;
    }

    return app_tr('SiteKit Site');
}

function app_preview_inspector_group_attrs(string $groupId, string $label = ''): string
{
    $group = trim($groupId);
    if ($group === '') {
        return '';
    }

    $attrs = ' data-preview-inspector-group="' . attr($group) . '"';
    $trimmedLabel = trim($label);
    if ($trimmedLabel !== '') {
        $attrs .= ' data-preview-inspector-label="' . attr($trimmedLabel) . '"';
    }

    return $attrs;
}

function app_reset_inline_style_registry(): void
{
    $GLOBALS['__app_inline_styles'] = [];
    $GLOBALS['__app_inline_scripts'] = [];
}

function app_wetted_preview_overrides_css(bool $embeddedPreview = false): string
{
    if ($embeddedPreview) {
        return <<<'CSS'
.preview-block{border:none;margin:0;transition:none}
.preview-block.is-sticky-header{position:sticky;top:0;z-index:140}
.preview-block.is-sticky-header .block-header.is-sticky-block,
.preview-block.is-sticky-header .block-header .block-nav.is-sticky{
  position:static;
  top:auto;
  inset-block-start:auto;
}
.preview-block:hover,.preview-block.is-selected,.preview-block.drag-over-before,.preview-block.drag-over-after{border-color:transparent;box-shadow:none}
.preview-block-label,.preview-block-actions{display:none !important}
CSS;
    }

    return <<<'CSS'
html,body{height:auto;overflow:auto}
.site-main{height:auto;overflow:visible}
.preview-block{border:none;margin:0;transition:none}
.preview-block.is-sticky-header{position:sticky;top:0;z-index:140}
.preview-block.is-sticky-header .block-header.is-sticky-block,
.preview-block.is-sticky-header .block-header .block-nav.is-sticky{
  position:static;
  top:auto;
  inset-block-start:auto;
}
.preview-block:hover,.preview-block.is-selected,.preview-block.drag-over-before,.preview-block.drag-over-after{border-color:transparent;box-shadow:none}
.preview-block-label,.preview-block-actions{display:none !important}
CSS;
}

function app_wetted_site_pages_css(bool $embeddedPreview = false): string
{
    if ($embeddedPreview) {
        return <<<'CSS'
.sitekit-site-pages{position:relative;z-index:1;height:100%}
.sitekit-site-page{display:none;height:100%}
.sitekit-site-page.is-active{display:block;height:100%}
CSS;
    }

    return <<<'CSS'
.sitekit-site-pages{position:relative;z-index:1}
.sitekit-site-page{display:none}
.sitekit-site-page.is-active{display:block}
CSS;
}

function app_wetted_site_pages_runtime_script(): string
{
    return <<<'HTML'
<script>
(function () {
  const root = document.documentElement;
  const pages = Array.from(document.querySelectorAll('.sitekit-site-page[data-site-page-id]'));
  if (!pages.length) {
    return;
  }

  const byId = new Map();
  const bySlug = new Map();
  pages.forEach((page) => {
    const id = String(page.getAttribute('data-site-page-id') || '').trim().toLowerCase();
    const slug = String(page.getAttribute('data-site-page-slug') || '').trim().toLowerCase();
    if (id) {
      byId.set(id, page);
    }
    if (slug) {
      bySlug.set(slug, page);
    }
  });

  const resolve = (token) => {
    const normalized = String(token || '').trim().toLowerCase();
    if (!normalized) {
      return null;
    }
    return byId.get(normalized) || bySlug.get(normalized) || null;
  };

  const normalizeHrefToken = (token) => {
    return String(token || '')
      .trim()
      .toLowerCase()
      .replace(/^#+/, '')
      .replace(/\/+$/, '')
      .replace(/\.(html?|php)$/, '');
  };

  const resolveHref = (href) => {
    const raw = String(href || '').trim();
    if (!raw || raw === '#') {
      return null;
    }

    const directToken = normalizeHrefToken(raw);
    if (raw.startsWith('#')) {
      return resolve(directToken);
    }

    try {
      const url = new URL(raw, window.location.href);
      const hashToken = normalizeHrefToken(url.hash);
      if (hashToken) {
        const fromHash = resolve(hashToken);
        if (fromHash) {
          return fromHash;
        }
      }

      if (url.origin !== window.location.origin) {
        return null;
      }

      const pathTokens = String(url.pathname || '')
        .split('/')
        .map((segment) => normalizeHrefToken(segment))
        .filter(Boolean);

      for (let index = pathTokens.length - 1; index >= 0; index -= 1) {
        const target = resolve(pathTokens[index]);
        if (target) {
          return target;
        }
      }
    } catch (_error) {
      return resolve(directToken);
    }

    return resolve(directToken);
  };

  const applyCanvas = (page) => {
    const css = String(page.getAttribute('data-site-canvas') || '');
    css.split(';').forEach((part) => {
      const entry = String(part || '').trim();
      if (!entry) {
        return;
      }
      const idx = entry.indexOf(':');
      if (idx <= 0) {
        return;
      }
      const key = entry.slice(0, idx).trim();
      const value = entry.slice(idx + 1).trim();
      if (!key || !value) {
        return;
      }
      root.style.setProperty(key, value);
    });
    const linkAnimation = String(page.getAttribute('data-site-link-animation') || 'none').trim() || 'none';
    document.body.setAttribute('data-link-animation', linkAnimation);
  };

  const activate = (page, updateHash) => {
    pages.forEach((entry) => {
      entry.classList.toggle('is-active', entry === page);
    });
    applyCanvas(page);

    const pageId = String(page.getAttribute('data-site-page-id') || '').trim();
    const pageSlug = String(page.getAttribute('data-site-page-slug') || '').trim();
    const pageTitle = String(page.getAttribute('data-site-page-title') || '').trim();
    const siteName = String(page.getAttribute('data-site-name') || '').trim();

    if (pageTitle) {
      document.title = siteName ? `${siteName} - ${pageTitle}` : pageTitle;
    }
    if (pageId) {
      document.body.setAttribute('data-site-page-id', pageId);
    }
    if (pageSlug) {
      document.documentElement.setAttribute('data-page-slug', pageSlug);
    }
    const pageLocale = String(page.getAttribute('data-site-page-locale') || '').trim();
    if (pageLocale) {
      document.documentElement.setAttribute('lang', pageLocale);
    }
    if (updateHash && pageSlug) {
      const canMutateHistory = !String((window.location && window.location.href) || '').startsWith('about:');
      if (canMutateHistory && window.history && typeof window.history.replaceState === 'function') {
        try {
          window.history.replaceState(null, '', '#' + pageSlug);
        } catch (_error) {
          // Embedded srcdoc previews cannot always rewrite history safely.
        }
      } else if (canMutateHistory) {
        try {
          window.location.hash = pageSlug;
        } catch (_error) {
          // Ignore hash writes when the document URL is not mutable.
        }
      }
    }
    const activeScroller = page.querySelector('.site-main');
    if (activeScroller && typeof activeScroller.scrollTo === 'function') {
      activeScroller.scrollTo(0, 0);
    }
    window.scrollTo(0, 0);
    document.dispatchEvent(new CustomEvent('sitekit:pagechange', {
      detail: {
        pageId,
        pageSlug,
      },
    }));
  };

  document.addEventListener('click', (event) => {
    const anchor = event.target.closest('a[href]');
    if (!anchor) {
      return;
    }
    const href = String(anchor.getAttribute('href') || '');
    const target = resolveHref(href);
    if (!target) {
      return;
    }
    event.preventDefault();
    activate(target, true);
  });

  window.addEventListener('hashchange', () => {
    const token = String(window.location.hash || '').replace(/^#/, '').trim();
    const target = resolve(token);
    if (target) {
      activate(target, false);
    }
  });

  const currentHash = String(window.location.hash || '').replace(/^#/, '').trim();
  const fromHash = resolve(currentHash);
  const active = fromHash || pages.find((page) => page.classList.contains('is-active')) || pages[0];
  if (active) {
    activate(active, false);
  }
})();
</script>
HTML;
}

function app_preview_block_icon_class(string $type): string
{
    $map = [
        'header' => 'pi-hamburger',
        'banner' => 'pi-horn',
        'hero' => 'pi-star',
        'divider' => 'pi-circle',
        'truststrip' => 'pi-certificate',
        'features' => 'pi-widget',
        'services' => 'pi-servicehands',
        'cards' => 'pi-clipboard',
        'stats' => 'pi-graph',
        'gallery' => 'pi-image',
        'carousel' => 'pi-rightwardsarrow',
        'datepicker' => 'pi-calendar',
        'faq' => 'pi-question',
        'location' => 'pi-map',
        'testimonials' => 'pi-quote',
        'socialbar' => 'pi-share',
        'content' => 'pi-document',
        'cta' => 'pi-send',
        'contact' => 'pi-contact',
        'footer' => 'pi-openfolder',
    ];

    return $map[$type] ?? 'pi-widget';
}

function app_wetted_runtime_script(): string
{
    return <<<'HTML'
<script>
(function () {
  const clamp = (number, min, max) => Math.max(min, Math.min(max, number));
  const mainScroller = document.querySelector('.site-main');
  const scroller = mainScroller && (mainScroller.scrollHeight > mainScroller.clientHeight) ? mainScroller : window;

  const viewportAnimationTargetSelector = '.surface,.card,.block-heading,h1,h2,h3,h4,h5,h6,.cta-band,.hero-copy,.hero-visual,.announcement,.faq-support,.faq-item,.location-map-embed,.date-month-panel,.date-week-card,.carousel-item,.placeholder-image';
  const viewportAnimationEls = Array.from(document.querySelectorAll('.preview-block[data-block-animation]:not([data-block-animation="none"])'))
    .flatMap((block) => Array.from(block.querySelectorAll(viewportAnimationTargetSelector)));
  const prefersReducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  if (prefersReducedMotion) {
    viewportAnimationEls.forEach((el) => el.classList.add('is-visible'));
  } else if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.18 });
    viewportAnimationEls.forEach((el) => observer.observe(el));
  } else {
    viewportAnimationEls.forEach((el) => el.classList.add('is-visible'));
  }

  const runCounter = (el) => {
    const target = Number(el.getAttribute('data-target') || '0');
    if (!Number.isFinite(target)) {
      return;
    }
    const fullText = (el.textContent || '').trim();
    const suffix = fullText.replace(/^[-+]?\d+/, '');
    const duration = 760;
    const startAt = performance.now();
    const tick = (ts) => {
      const progress = Math.min((ts - startAt) / duration, 1);
      const value = Math.round(target * progress);
      el.textContent = String(value) + suffix;
      if (progress < 1) {
        requestAnimationFrame(tick);
      }
    };
    requestAnimationFrame(tick);
  };

  const counters = Array.from(document.querySelectorAll('[data-counter="1"]'));
  counters.forEach((el) => {
    const trigger = el.getAttribute('data-trigger') || 'onView';
    if (trigger === 'onLoad') {
      runCounter(el);
      return;
    }
    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            runCounter(el);
            obs.disconnect();
          }
        });
      }, { threshold: 0.35 });
      obs.observe(el);
    } else {
      runCounter(el);
    }
  });

  const heroWraps = Array.from(document.querySelectorAll('.block-hero .hero-wrap'));
  if (heroWraps.length) {
    const syncHeroCopyInset = () => {
      heroWraps.forEach((wrap) => {
        const copy = wrap.querySelector('.hero-copy');
        if (!copy) {
          return;
        }
        const parentWidth = wrap.clientWidth;
        const cardWidth = copy.offsetWidth;
        if (!parentWidth || !cardWidth) {
          return;
        }
        const maxInset = Math.max(0, Math.round((parentWidth * 0.5) - cardWidth));
        wrap.style.setProperty('--hero-copy-inset-max', maxInset + 'px');
      });
    };
    const requestHeroInsetSync = () => {
      window.requestAnimationFrame(syncHeroCopyInset);
    };
    window.addEventListener('resize', requestHeroInsetSync, { passive: true });
    document.addEventListener('sitekit:pagechange', requestHeroInsetSync);
    if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
      document.fonts.ready.then(requestHeroInsetSync).catch(() => {});
    }
    requestHeroInsetSync();
  }

  const heroRotators = Array.from(document.querySelectorAll('[data-hero-rotator="1"]'));
  heroRotators.forEach((rotator) => {
    const frames = Array.from(rotator.querySelectorAll('.hero-media-frame'));
    if (frames.length < 2) {
      return;
    }

    const interval = Math.max(2200, Number(rotator.getAttribute('data-hero-interval') || '5200'));
    const reducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const observerRoot = (scroller && scroller !== window) ? scroller : null;
    let activeIndex = Math.max(0, frames.findIndex((frame) => frame.classList.contains('is-active')));
    let timer = 0;
    let inView = true;

    const loadFrame = (index) => {
      const frame = frames[index];
      if (!frame) {
        return;
      }
      const pendingSrc = String(frame.getAttribute('data-src') || '').trim();
      if (!pendingSrc) {
        return;
      }
      frame.setAttribute('src', pendingSrc);
      frame.removeAttribute('data-src');
    };

    const isActivePage = () => {
      const page = rotator.closest('.sitekit-site-page');
      return !page || page.classList.contains('is-active');
    };

    const activateFrame = (index) => {
      activeIndex = ((index % frames.length) + frames.length) % frames.length;
      loadFrame(activeIndex);
      loadFrame((activeIndex + 1) % frames.length);
      frames.forEach((frame, frameIndex) => {
        frame.classList.toggle('is-active', frameIndex === activeIndex);
      });
    };

    const stop = () => {
      if (timer) {
        window.clearInterval(timer);
        timer = 0;
      }
    };

    const sync = () => {
      stop();
      if (reducedMotion || document.hidden || !inView || !isActivePage()) {
        return;
      }
      timer = window.setInterval(() => {
        activateFrame(activeIndex + 1);
      }, interval);
    };

    activateFrame(activeIndex);
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          inView = entry.isIntersecting;
          sync();
        });
      }, {
        root: observerRoot,
        threshold: 0.28,
      });
      observer.observe(rotator);
    }

    document.addEventListener('visibilitychange', sync);
    document.addEventListener('sitekit:pagechange', sync);
    sync();
  });

  const parallaxMedia = Array.from(document.querySelectorAll('[data-parallax-media="1"]'));
  if (parallaxMedia.length && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
    const clampParallax = (value, min, max) => Math.min(max, Math.max(min, value));
    let parallaxFrame = 0;

    const syncParallaxMedia = () => {
      parallaxFrame = 0;
      const viewportHeight = Math.max(1, window.innerHeight || document.documentElement.clientHeight || 0);
      parallaxMedia.forEach((node) => {
        const page = node.closest('.sitekit-site-page');
        if (page && !page.classList.contains('is-active')) {
          node.style.setProperty('--parallax-media-y', '0px');
          return;
        }

        const rect = node.getBoundingClientRect();
        const strength = Math.max(0, Number(node.getAttribute('data-parallax-strength') || '20'));
        if (!strength) {
          node.style.setProperty('--parallax-media-y', '0px');
          return;
        }

        const mid = rect.top + (rect.height * 0.5);
        const progress = (mid - (viewportHeight * 0.5)) / viewportHeight;
        const offset = clampParallax(progress * (-strength * 2), -strength, strength);
        node.style.setProperty('--parallax-media-y', offset.toFixed(2) + 'px');
      });
    };

    const requestParallaxSync = () => {
      if (parallaxFrame) {
        return;
      }
      parallaxFrame = window.requestAnimationFrame(syncParallaxMedia);
    };

    if (scroller && scroller !== window) {
      scroller.addEventListener('scroll', requestParallaxSync, { passive: true });
    }
    window.addEventListener('scroll', requestParallaxSync, { passive: true });
    window.addEventListener('resize', requestParallaxSync, { passive: true });
    document.addEventListener('sitekit:pagechange', requestParallaxSync);
    requestParallaxSync();
  }

  const filmstripViewports = Array.from(document.querySelectorAll('[data-filmstrip="1"]'));
  if (filmstripViewports.length) {
    class SiteKitFilmstrip {
      constructor(viewport) {
        const readNumber = (name, fallback) => {
          const raw = Number(viewport.getAttribute(name) || '');
          return Number.isFinite(raw) ? raw : fallback;
        };
        this.viewport = viewport;
        this.shell = viewport.closest('[data-filmstrip-shell]') || viewport.parentElement;
        this.track = viewport.querySelector('.filmstrip-track');
        this.originalCells = this.track ? Array.from(this.track.children) : [];
        this.total = this.originalCells.length;
        this.prevButton = this.shell ? this.shell.querySelector('[data-film-prev]') : null;
        this.nextButton = this.shell ? this.shell.querySelector('[data-film-next]') : null;
        this.clonesPerSide = 1;
        this.index = 0;
        this.step = 0;
        this.pointerId = null;
        this.pointerDown = false;
        this.dragging = false;
        this.dragOffset = 0;
        this.startX = 0;
        this.startY = 0;
        this.lastSamples = [];
        this.suppressClick = false;
        this.animating = false;
        this.resizeFrame = 0;
        this.startIndex = Math.max(0, readNumber('data-film-start-index', 0));
        this.maxFlickCards = Math.max(1, readNumber('data-film-max-flick-cards', 2));
        this.clonesMin = Math.max(1, readNumber('data-film-clones-min', 1));
        this.moveRounding = (viewport.getAttribute('data-film-move-rounding') || 'round') === 'ceil' ? 'ceil' : 'round';
        this.velocityFactor = Math.max(0, readNumber('data-film-velocity-factor', 70));
        this.velocityCap = Math.max(0, readNumber('data-film-velocity-cap', 45));
        this.warmImagesEnabled = viewport.getAttribute('data-film-warm-images') === '1';
        this.readyClass = (viewport.getAttribute('data-film-ready-class') || '').trim();
        this.motion = {
          dragStartThreshold: Math.max(0, readNumber('data-film-drag-threshold', 6)),
          flickDistanceRatio: Math.max(0, readNumber('data-film-flick-distance-ratio', 0.16)),
          flickVelocity: Math.max(0, readNumber('data-film-flick-velocity', 0.32)),
          flickPower: Math.max(0, readNumber('data-film-flick-power', 320)),
          allowClickAfterDrag: Math.max(0, readNumber('data-film-click-threshold', 8)),
          snapMinDuration: Math.max(120, readNumber('data-film-snap-min', 380)),
          snapMaxDuration: Math.max(160, readNumber('data-film-snap-max', 820)),
          snapBaseDuration: Math.max(120, readNumber('data-film-snap-base', 260)),
          snapPxFactor: Math.max(0, readNumber('data-film-snap-px-factor', 0.42)),
          ease: 'cubic-bezier(.16,1,.3,1)',
        };

        this.onPointerDown = this.onPointerDown.bind(this);
        this.onPointerMove = this.onPointerMove.bind(this);
        this.onPointerUp = this.onPointerUp.bind(this);
        this.onPointerCancel = this.onPointerCancel.bind(this);
        this.onTransitionEnd = this.onTransitionEnd.bind(this);
        this.onResize = this.onResize.bind(this);
        this.handlePrev = this.handlePrev.bind(this);
        this.handleNext = this.handleNext.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleNativeDragStart = this.handleNativeDragStart.bind(this);
      }

      clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
      }

      getGap() {
        const styles = window.getComputedStyle(this.track);
        return parseFloat(styles.columnGap || styles.gap || '0') || 0;
      }

      getReferenceCell() {
        return this.track.querySelector('.filmstrip-slide:not(.is-clone)') || this.originalCells[0] || null;
      }

      syncOriginalCells() {
        if (!this.track) {
          this.originalCells = [];
          this.total = 0;
          return false;
        }
        const sourceCells = Array.from(this.track.children).filter((cell) => {
          if (!(cell instanceof HTMLElement)) {
            return false;
          }
          if (cell.classList.contains('is-clone')) {
            return false;
          }
          return cell.getAttribute('data-film-source') === '1' || cell.hasAttribute('data-film-cell');
        });
        if (sourceCells.length > 0) {
          this.originalCells = sourceCells;
          this.total = sourceCells.length;
          return true;
        }
        if (this.originalCells.length > 0) {
          this.total = this.originalCells.length;
          return true;
        }
        this.total = 0;
        return false;
      }

      measureStep() {
        const reference = this.getReferenceCell();
        if (!reference) {
          this.step = 0;
          return 0;
        }
        this.step = reference.getBoundingClientRect().width + this.getGap();
        return this.step;
      }

      getNeededClonesPerSide() {
        if (!this.step) {
          return this.clonesMin;
        }
        const visible = Math.ceil(this.viewport.clientWidth / this.step);
        return Math.max(this.clonesMin, visible + 1);
      }

      realIndexFromTrackIndex(index = this.index) {
        let real = (index - this.clonesPerSide) % this.total;
        if (real < 0) {
          real += this.total;
        }
        return real;
      }

      trackIndexFromRealIndex(realIndex) {
        return this.clonesPerSide + realIndex;
      }

      createClone(realIndex) {
        if (!this.syncOriginalCells() || this.total < 1) {
          return null;
        }
        const source = this.originalCells[(realIndex + this.total) % this.total];
        if (!source) {
          return null;
        }
        const node = source.cloneNode(true);
        node.classList.add('is-clone');
        node.removeAttribute('data-film-source');
        node.setAttribute('aria-hidden', 'true');
        return node;
      }

      renderTrack(preserveReal = 0) {
        if (!this.track || !this.syncOriginalCells() || this.total < 1) {
          return;
        }
        const fragment = document.createDocumentFragment();
        for (let index = this.clonesPerSide; index >= 1; index -= 1) {
          const clone = this.createClone(this.total - index);
          if (clone) {
            fragment.append(clone);
          }
        }
        this.originalCells.forEach((cell) => fragment.append(cell));
        for (let index = 0; index < this.clonesPerSide; index += 1) {
          const clone = this.createClone(index);
          if (clone) {
            fragment.append(clone);
          }
        }
        this.track.replaceChildren(fragment);
        this.measureStep();
        this.index = this.trackIndexFromRealIndex(preserveReal);
        this.setTransition(false);
        this.setTranslate(this.getTranslateForIndex(this.index));
        void this.track.offsetWidth;
      }

      getTranslateForIndex(index) {
        return -(index * this.step);
      }

      setTranslate(value) {
        this.track.style.transform = `translate3d(${value}px,0,0)`;
      }

      setTransition(enabled, duration = this.motion.snapBaseDuration) {
        this.track.style.transition = enabled
          ? `transform ${duration}ms ${this.motion.ease}`
          : 'none';
      }

      getSnapDuration(fromIndex, toIndex, velocity = 0) {
        const distance = Math.abs(this.getTranslateForIndex(toIndex) - this.getTranslateForIndex(fromIndex));
        let duration = this.motion.snapBaseDuration + (distance * this.motion.snapPxFactor);
        duration -= Math.min(Math.abs(velocity) * this.velocityFactor, this.velocityCap);
        return Math.round(this.clamp(duration, this.motion.snapMinDuration, this.motion.snapMaxDuration));
      }

      waitForImageElement(img) {
        if (img.complete && img.naturalWidth > 0) {
          return img.decode ? img.decode().catch(() => {}) : Promise.resolve();
        }

        return new Promise((resolve) => {
          img.addEventListener('load', () => {
            if (img.decode) {
              img.decode().catch(() => {}).finally(resolve);
            } else {
              resolve();
            }
          }, { once: true });
          img.addEventListener('error', resolve, { once: true });
        });
      }

      warmImages() {
        if (!this.warmImagesEnabled || !this.track) {
          return Promise.resolve();
        }

        const imgs = Array.from(this.track.querySelectorAll('img'));
        imgs.forEach((img) => {
          img.loading = 'eager';
          img.decoding = 'async';
          try {
            img.fetchPriority = 'high';
          } catch (_err) {
            img.setAttribute('fetchpriority', 'high');
          }
        });

        const unique = [...new Set(imgs.map((img) => img.currentSrc || img.src).filter(Boolean))];

        return Promise.all(unique.map((src) => {
          const pre = new Image();
          pre.src = src;
          pre.decoding = 'async';

          if (pre.complete) {
            return pre.decode ? pre.decode().catch(() => {}) : Promise.resolve();
          }

          return new Promise((resolve) => {
            pre.onload = () => {
              if (pre.decode) {
                pre.decode().catch(() => {}).finally(resolve);
              } else {
                resolve();
              }
            };
            pre.onerror = resolve;
          });
        })).then(() => Promise.all(imgs.map((img) => this.waitForImageElement(img)))).then(() => {});
      }

      rebuildForViewport() {
        if (!this.track || !this.syncOriginalCells() || this.total < 2) {
          return Promise.resolve();
        }
        const preserveReal = this.realIndexFromTrackIndex();
        this.measureStep();
        const needed = this.getNeededClonesPerSide();
        if (needed !== this.clonesPerSide) {
          this.clonesPerSide = needed;
          this.renderTrack(preserveReal);
          return this.warmImages();
        }
        if (this.step) {
          this.jumpWithoutFlash(this.trackIndexFromRealIndex(preserveReal));
        }
        return Promise.resolve();
      }

      snapToIndex(nextIndex, animated = true, velocity = 0) {
        const fromIndex = this.index;
        this.index = nextIndex;
        this.animating = animated;
        if (animated) {
          this.setTransition(true, this.getSnapDuration(fromIndex, nextIndex, velocity));
        } else {
          this.setTransition(false);
        }
        this.setTranslate(this.getTranslateForIndex(this.index));
      }

      jumpWithoutFlash(nextIndex) {
        this.index = nextIndex;
        this.setTransition(false);
        this.setTranslate(this.getTranslateForIndex(this.index));
        void this.track.offsetWidth;
      }

      normalizeWrap() {
        if (this.index < this.clonesPerSide) {
          this.jumpWithoutFlash(this.index + this.total);
        } else if (this.index >= this.clonesPerSide + this.total) {
          this.jumpWithoutFlash(this.index - this.total);
        }
      }

      recordSample(x) {
        const now = performance.now();
        this.lastSamples.push({ x, t: now });
        const cutoff = now - 140;
        while (this.lastSamples.length > 2 && this.lastSamples[0].t < cutoff) {
          this.lastSamples.shift();
        }
      }

      getVelocity() {
        if (this.lastSamples.length < 2) {
          return 0;
        }
        const first = this.lastSamples[0];
        const last = this.lastSamples[this.lastSamples.length - 1];
        const deltaTime = last.t - first.t;
        if (deltaTime <= 0) {
          return 0;
        }
        return (last.x - first.x) / deltaTime;
      }

      handlePrev() {
        if (this.animating) {
          return;
        }
        this.snapToIndex(this.index - 1, true, 0);
      }

      handleNext() {
        if (this.animating) {
          return;
        }
        this.snapToIndex(this.index + 1, true, 0);
      }

      handleKeydown(event) {
        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          this.handlePrev();
        } else if (event.key === 'ArrowRight') {
          event.preventDefault();
          this.handleNext();
        }
      }

      onPointerDown(event) {
        if (event.button !== undefined && event.button !== 0) {
          return;
        }
        if (this.animating) {
          return;
        }

        this.pointerId = event.pointerId;
        this.pointerDown = true;
        this.dragging = false;
        this.dragOffset = 0;
        this.suppressClick = false;
        this.startX = event.clientX;
        this.startY = event.clientY;
        this.lastSamples = [];
        this.recordSample(event.clientX);
        this.viewport.setPointerCapture?.(this.pointerId);
      }

      onPointerMove(event) {
        if (!this.pointerDown || event.pointerId !== this.pointerId) {
          return;
        }
        event.preventDefault();

        const deltaX = event.clientX - this.startX;
        const deltaY = event.clientY - this.startY;
        if (!this.dragging) {
          if (Math.abs(deltaX) < this.motion.dragStartThreshold) {
            return;
          }
          if (Math.abs(deltaY) > Math.abs(deltaX)) {
            return;
          }
          this.dragging = true;
          this.viewport.classList.add('is-dragging');
          this.setTransition(false);
        }

        this.dragOffset = deltaX;
        this.recordSample(event.clientX);
        this.setTranslate(this.getTranslateForIndex(this.index) + this.dragOffset);
        if (Math.abs(deltaX) > this.motion.allowClickAfterDrag) {
          this.suppressClick = true;
        }
      }

      finishPointer(event) {
        if (!this.pointerDown || event.pointerId !== this.pointerId) {
          return;
        }

        this.viewport.releasePointerCapture?.(this.pointerId);
        this.pointerDown = false;

        if (!this.dragging) {
          return;
        }

        this.dragging = false;
        this.viewport.classList.remove('is-dragging');

        const velocity = this.getVelocity();
        const projected = this.dragOffset + (velocity * this.motion.flickPower);
        const cutoff = this.step * this.motion.flickDistanceRatio;

        let direction = 0;
        let cardsToMove = 0;
        const moveCards = (value) => {
          const ratio = Math.abs(value) / this.step;
          return this.moveRounding === 'ceil' ? Math.ceil(ratio) : Math.round(ratio);
        };
        if (projected <= -cutoff || velocity <= -this.motion.flickVelocity) {
          direction = 1;
          cardsToMove = this.clamp(Math.max(1, moveCards(projected)), 1, this.maxFlickCards);
        } else if (projected >= cutoff || velocity >= this.motion.flickVelocity) {
          direction = -1;
          cardsToMove = this.clamp(Math.max(1, moveCards(projected)), 1, this.maxFlickCards);
        }

        if (!direction) {
          this.snapToIndex(this.index, true, velocity);
        } else {
          this.snapToIndex(this.index + (direction * cardsToMove), true, velocity);
        }
      }

      onPointerUp(event) {
        this.finishPointer(event);
      }

      onPointerCancel(event) {
        if (!this.pointerDown || event.pointerId !== this.pointerId) {
          return;
        }
        this.viewport.releasePointerCapture?.(this.pointerId);
        this.pointerDown = false;
        this.dragging = false;
        this.viewport.classList.remove('is-dragging');
        this.snapToIndex(this.index, true, 0);
      }

      handleNativeDragStart(event) {
        event.preventDefault();
        event.stopPropagation();
      }

      onTransitionEnd(event) {
        if (event.propertyName !== 'transform') {
          return;
        }
        this.animating = false;
        this.normalizeWrap();
      }

      onResize() {
        if (!this.track || !this.syncOriginalCells() || this.total < 2) {
          return;
        }
        if (this.resizeFrame) {
          window.cancelAnimationFrame(this.resizeFrame);
        }
        this.resizeFrame = window.requestAnimationFrame(() => {
          this.rebuildForViewport().catch(() => {});
          if (this.readyClass && this.shell) {
            this.shell.classList.add(this.readyClass);
          }
        });
      }

      init() {
        if (!this.track || !this.syncOriginalCells() || this.total < 2) {
          return Promise.resolve();
        }

        this.measureStep();
        this.clonesPerSide = this.getNeededClonesPerSide();
        this.renderTrack(this.startIndex % this.total);
        return this.warmImages().then(() => {
          this.jumpWithoutFlash(this.trackIndexFromRealIndex(this.startIndex % this.total));
          if (this.readyClass && this.shell) {
            this.shell.classList.add(this.readyClass);
          }
          this.track.addEventListener('transitionend', this.onTransitionEnd);
          this.viewport.addEventListener('pointerdown', this.onPointerDown);
          this.viewport.addEventListener('pointermove', this.onPointerMove);
          this.viewport.addEventListener('pointerup', this.onPointerUp);
          this.viewport.addEventListener('pointercancel', this.onPointerCancel);
          this.viewport.addEventListener('keydown', this.handleKeydown);
          this.viewport.addEventListener('dragstart', this.handleNativeDragStart, true);
          this.viewport.addEventListener('click', (event) => {
            if (!this.suppressClick) {
              return;
            }
            event.preventDefault();
            event.stopPropagation();
            this.suppressClick = false;
          }, true);
          if (this.prevButton) {
            this.prevButton.addEventListener('click', this.handlePrev);
          }
          if (this.nextButton) {
            this.nextButton.addEventListener('click', this.handleNext);
          }
        }).catch(() => {});
      }
    }

    const filmstripInstances = filmstripViewports.map((viewport) => {
      const instance = new SiteKitFilmstrip(viewport);
      instance.init();
      return instance;
    });

    const requestFilmstripResize = () => {
      filmstripInstances.forEach((instance) => instance.onResize());
    };

    window.addEventListener('resize', requestFilmstripResize, { passive: true });
    document.addEventListener('sitekit:pagechange', requestFilmstripResize);
    requestFilmstripResize();
  }

  const stickyHeaders = Array.from(document.querySelectorAll('.preview-block.is-sticky-header'));
  if (stickyHeaders.length) {
    const stickyEntries = stickyHeaders.map((block) => {
      const placeholder = document.createElement('div');
      placeholder.setAttribute('data-sticky-header-spacer', '1');
      placeholder.style.display = 'block';
      placeholder.style.width = '100%';
      placeholder.style.height = '0px';
      block.parentNode.insertBefore(placeholder, block);
      return { block, placeholder };
    });

    const resetStickyHeader = (entry) => {
      entry.placeholder.style.height = '0px';
      entry.block.classList.remove('is-fixed-sticky');
      entry.block.style.position = '';
      entry.block.style.top = '';
      entry.block.style.left = '';
      entry.block.style.width = '';
      entry.block.style.zIndex = '';
    };

    const syncStickyHeaders = () => {
      stickyEntries.forEach((entry) => {
        const page = entry.block.closest('.sitekit-site-page');
        if (page && !page.classList.contains('is-active')) {
          resetStickyHeader(entry);
          return;
        }

        const anchorRect = entry.placeholder.getBoundingClientRect();
        if (anchorRect.top > 0) {
          resetStickyHeader(entry);
          return;
        }

        const width = Math.max(0, Math.round(anchorRect.width));
        const left = Math.round(anchorRect.left);
        const height = Math.max(0, Math.round(entry.block.offsetHeight));
        entry.placeholder.style.height = height + 'px';
        entry.block.classList.add('is-fixed-sticky');
        entry.block.style.position = 'fixed';
        entry.block.style.top = '0';
        entry.block.style.left = left + 'px';
        entry.block.style.width = width + 'px';
        entry.block.style.zIndex = '160';
      });
    };

    const requestStickySync = () => {
      window.requestAnimationFrame(syncStickyHeaders);
    };

    if (scroller && scroller !== window) {
      scroller.addEventListener('scroll', requestStickySync, { passive: true });
    }
    window.addEventListener('scroll', requestStickySync, { passive: true });
    window.addEventListener('resize', requestStickySync, { passive: true });
    document.addEventListener('sitekit:pagechange', requestStickySync);
    requestStickySync();
  }

  const tracks = Array.from(document.querySelectorAll('[data-carousel]'));
  tracks.forEach((track) => {
    const wrap = track.closest('[data-carousel-wrap]') || track.parentElement;
    const prev = wrap ? wrap.querySelector('[data-carousel-prev]') : null;
    const next = wrap ? wrap.querySelector('[data-carousel-next]') : null;
    const pagerDots = wrap ? Array.from(wrap.querySelectorAll('[data-carousel-dot]')) : [];
    const scrollDriven = track.getAttribute('data-scroll-driven') === '1';
    const flowDirection = (track.getAttribute('data-flow-direction') || 'ltr') === 'rtl' ? 'rtl' : 'ltr';
    const forwardSign = flowDirection === 'rtl' ? -1 : 1;
    const autoplay = (track.getAttribute('data-autoplay') === '1') && !scrollDriven;
    const interval = Math.max(1000, Number(track.getAttribute('data-interval') || '4200'));
    const transitionMs = Math.max(200, Number(track.getAttribute('data-transition-ms') || '720'));
    const prefersReducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const reduceOrHidden = () => prefersReducedMotion || document.hidden;
    let autoplayTimer = 0;
    let resumeTimer = 0;
    let normalizeTimer = 0;
    let driveTarget = track.scrollLeft;
    let driveCurrent = track.scrollLeft;
    let driveFrame = 0;
    let syncFrame = 0;
    let activeIndex = 0;

    const readGap = () => {
      const style = window.getComputedStyle(track);
      const value = Number.parseFloat(style.columnGap || style.gap || '0');
      return Number.isFinite(value) ? value : 0;
    };
    const baseItems = () => Array.from(track.querySelectorAll('.carousel-item:not([data-carousel-clone="1"])'));
    const clearLoopClone = () => {
      const clone = track.querySelector('.carousel-item[data-carousel-clone="1"]');
      if (clone) {
        clone.remove();
      }
    };
    const removeNestedIds = (root) => {
      const withId = root.querySelectorAll('[id]');
      withId.forEach((node) => node.removeAttribute('id'));
    };
    const removeEditableMarkers = (root) => {
      if (root.hasAttribute('data-field')) {
        root.removeAttribute('data-field');
      }
      if (root.hasAttribute('contenteditable')) {
        root.removeAttribute('contenteditable');
      }
      const editableNodes = root.querySelectorAll('[data-field],[contenteditable]');
      editableNodes.forEach((node) => {
        node.removeAttribute('data-field');
        node.removeAttribute('contenteditable');
      });
    };
    const ensureLoopClone = () => {
      if (scrollDriven) {
        clearLoopClone();
        return null;
      }
      const items = baseItems();
      if (items.length < 2) {
        clearLoopClone();
        return null;
      }
      let clone = track.querySelector('.carousel-item[data-carousel-clone="1"]');
      if (!clone) {
        clone = items[0].cloneNode(true);
        clone.setAttribute('data-carousel-clone', '1');
        clone.setAttribute('aria-hidden', 'true');
        clone.removeAttribute('id');
        removeNestedIds(clone);
        removeEditableMarkers(clone);
        track.appendChild(clone);
      }
      return clone;
    };
    let loopClone = ensureLoopClone();

    const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
    const loopEdge = () => loopClone ? loopClone.offsetLeft : maxScroll();
    const stepSize = () => {
      const first = baseItems()[0] || track.querySelector('.carousel-item');
      if (!first) {
        return Math.max(220, track.clientWidth * 0.88);
      }
      const width = first.getBoundingClientRect().width;
      return Math.max(120, width + readGap());
    };
    const slidePositions = () => baseItems().map((item) => Math.max(0, item.offsetLeft));
    const setActiveIndex = (index) => {
      const positions = slidePositions();
      const maxIndex = Math.max(0, positions.length - 1);
      activeIndex = clamp(index, 0, maxIndex);
      pagerDots.forEach((dot, dotIndex) => {
        const isActive = dotIndex === activeIndex;
        dot.classList.toggle('is-active', isActive);
        if (isActive) {
          dot.setAttribute('aria-current', 'true');
        } else {
          dot.removeAttribute('aria-current');
        }
      });
    };
    const resolveIndex = () => {
      const positions = slidePositions();
      if (!positions.length) {
        return 0;
      }
      if (loopClone && track.scrollLeft >= loopEdge() - (stepSize() * 0.45)) {
        return 0;
      }
      let bestIndex = 0;
      let bestDistance = Number.POSITIVE_INFINITY;
      positions.forEach((left, index) => {
        const distance = Math.abs(track.scrollLeft - left);
        if (distance < bestDistance) {
          bestDistance = distance;
          bestIndex = index;
        }
      });
      return bestIndex;
    };
    const syncActiveIndex = () => {
      setActiveIndex(resolveIndex());
    };
    const queueSync = () => {
      if (syncFrame) {
        return;
      }
      syncFrame = window.requestAnimationFrame(() => {
        syncFrame = 0;
        syncActiveIndex();
      });
    };

    const jumpTo = (left) => {
      const nextLeft = clamp(left, 0, maxScroll());
      if (typeof track.scrollTo === 'function') {
        track.scrollTo({ left: nextLeft, behavior: 'auto' });
      } else {
        track.scrollLeft = nextLeft;
      }
    };
    const smoothTo = (left) => {
      const nextLeft = clamp(left, 0, maxScroll());
      if (typeof track.scrollTo === 'function') {
        track.scrollTo({ left: nextLeft, behavior: reduceOrHidden() ? 'auto' : 'smooth' });
      } else {
        track.scrollLeft = nextLeft;
      }
    };
    const normalizeLoopPosition = () => {
      if (!loopClone) {
        return;
      }
      if (track.scrollLeft >= loopEdge() - 2) {
        jumpTo(0);
      }
    };
    const queueNormalize = () => {
      if (!loopClone) {
        return;
      }
      if (normalizeTimer) {
        clearTimeout(normalizeTimer);
      }
      normalizeTimer = window.setTimeout(normalizeLoopPosition, transitionMs + 90);
    };
    const goToIndex = (index, behavior) => {
      loopClone = ensureLoopClone();
      const positions = slidePositions();
      if (!positions.length) {
        return;
      }
      const lastIndex = positions.length - 1;
      if (index < 0) {
        index = lastIndex;
      }
      if (index > lastIndex) {
        setActiveIndex(0);
        if (loopClone) {
          if (behavior === 'auto' || reduceOrHidden()) {
            jumpTo(loopEdge());
            normalizeLoopPosition();
          } else {
            smoothTo(loopEdge());
            queueNormalize();
          }
          return;
        }
        index = 0;
      }
      const nextIndex = clamp(index, 0, lastIndex);
      const targetLeft = positions[nextIndex] || 0;
      if (behavior === 'auto' || reduceOrHidden()) {
        jumpTo(targetLeft);
      } else {
        smoothTo(targetLeft);
      }
      setActiveIndex(nextIndex);
    };
    const moveBy = (direction) => {
      const currentIndex = resolveIndex();
      goToIndex(currentIndex + direction, 'smooth');
    };

    const stopAutoplay = () => {
      if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = 0;
      }
    };
    const startAutoplay = () => {
      if (!autoplay || autoplayTimer || reduceOrHidden()) {
        return;
      }
      autoplayTimer = window.setInterval(() => {
        if (reduceOrHidden()) {
          stopAutoplay();
          return;
        }
        moveBy(forwardSign);
      }, interval);
    };
    const pauseAutoplay = () => {
      stopAutoplay();
      if (resumeTimer) {
        clearTimeout(resumeTimer);
      }
      if (!autoplay) {
        return;
      }
      resumeTimer = window.setTimeout(() => {
        startAutoplay();
      }, Math.max(interval, transitionMs + 300));
    };

    if (prev) {
      prev.addEventListener('click', () => {
        pauseAutoplay();
        moveBy(-forwardSign);
      });
    }
    if (next) {
      next.addEventListener('click', () => {
        pauseAutoplay();
        moveBy(forwardSign);
      });
    }
    pagerDots.forEach((dot, index) => {
      dot.addEventListener('click', () => {
        pauseAutoplay();
        goToIndex(index, 'smooth');
      });
    });

    if (scrollDriven) {
      clearLoopClone();
      const readDriveRatio = () => {
        const rect = track.getBoundingClientRect();
        const vh = window.innerHeight || 1;
        const start = vh * 0.92;
        const end = 0 - (rect.height * 0.45);
        const ratio = clamp((start - rect.top) / (start - end), 0, 1);
        return flowDirection === 'rtl' ? (1 - ratio) : ratio;
      };
      const onScrollDrive = () => {
        driveTarget = readDriveRatio() * maxScroll();
        if (driveFrame) {
          return;
        }
        const tick = () => {
          const delta = driveTarget - driveCurrent;
          driveCurrent += delta * 0.09;
          if (Math.abs(delta) < 0.35) {
            driveCurrent = driveTarget;
          }
          track.scrollLeft = clamp(driveCurrent, 0, maxScroll());
          queueSync();
          if (Math.abs(driveTarget - driveCurrent) >= 0.35) {
            driveFrame = requestAnimationFrame(tick);
          } else {
            driveFrame = 0;
          }
        };
        driveFrame = requestAnimationFrame(tick);
      };
      scroller.addEventListener('scroll', onScrollDrive, { passive: true });
      window.addEventListener('resize', onScrollDrive, { passive: true });
      onScrollDrive();
    }

    track.addEventListener('mouseenter', stopAutoplay);
    track.addEventListener('mouseleave', startAutoplay);
    track.addEventListener('focusin', pauseAutoplay);
    track.addEventListener('pointerdown', pauseAutoplay, { passive: true });
    track.addEventListener('wheel', pauseAutoplay, { passive: true });
    track.addEventListener('scroll', () => {
      if (!scrollDriven) {
        queueNormalize();
      }
      queueSync();
    }, { passive: true });
    window.addEventListener('resize', queueSync, { passive: true });

    queueSync();
    if (autoplay) {
      startAutoplay();
    }
  });

  const fadingHeaders = Array.from(document.querySelectorAll('.block-header.block-v2,.block-header.block-v3,.block-header.block-v4'));
  if (fadingHeaders.length) {
    const applyHeaderFade = () => {
      fadingHeaders.forEach((header) => {
        const rect = header.getBoundingClientRect();
        const vh = window.innerHeight || 1;
        const progress = clamp((0 - rect.top) / vh, 0, 1);
        const opacity = 1 - (progress * 0.55);
        header.style.opacity = String(clamp(opacity, 0.42, 1));
      });
    };
    scroller.addEventListener('scroll', applyHeaderFade, { passive: true });
    applyHeaderFade();
  }
})();
</script>
HTML;
}

function app_render_preview(array $payload): array
{
    $normalized = app_import_sitekit_payload($payload);
    $theme = $normalized['theme'];
    $page = $normalized['page'];
    app_set_render_site_base_url((string) ($normalized['site']['baseUrl'] ?? ''));
    app_set_render_site_assets(is_array($normalized['site']['assets'] ?? null) ? $normalized['site']['assets'] : []);

    return [
        'html' => app_render_page_html($page, $theme),
        'head' => [
            'fonts' => google_fonts_links($theme),
            'style' => theme_css_vars($theme) . app_canvas_css_vars($page) . preview_base_css(),
            'themeId' => (string) app_theme_value($theme, ['theme', 'id'], 'custom'),
        ],
    ];
}

function app_render_preview_block(array $payload, string $blockUid): array
{
    $normalized = app_import_sitekit_payload($payload);
    $theme = $normalized['theme'];
    $page = $normalized['page'];
    app_set_render_site_base_url((string) ($normalized['site']['baseUrl'] ?? ''));
    app_set_render_site_assets(is_array($normalized['site']['assets'] ?? null) ? $normalized['site']['assets'] : []);
    $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];

    $match = null;
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if ((string) ($block['uid'] ?? '') !== $blockUid) {
            continue;
        }
        $match = $block;
        break;
    }

    if ($match === null) {
        throw new RuntimeException('Block not found for preview render.');
    }

    app_reset_inline_style_registry();

    ob_start();
    echo app_render_block($match, $theme, true, $page);

    return [
        'html' => (string) ob_get_clean(),
    ];
}

function app_render_page_html(array $page, array $theme, bool $editorMode = true): string
{
    $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
    app_reset_inline_style_registry();

    ob_start();
    echo '<main class="site-main">';
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if ((bool) ($block['hidden'] ?? false)) {
            continue;
        }

        echo app_render_block($block, $theme, $editorMode, $page);
    }
    echo '</main>';

    return (string) ob_get_clean();
}

function app_block_card_override_selectors(string $type): array
{
    $selectors = ['.surface'];

    if ($type === 'hero') {
        $selectors = array_merge($selectors, ['.hero-copy', '.hero-copy::before', '.hero-copy::after', '.hero-visual']);
    } elseif ($type === 'banner') {
        $selectors = array_merge($selectors, ['.announcement', '.announcement-dismiss']);
    } elseif ($type === 'carousel') {
        $selectors = array_merge($selectors, ['.carousel-item']);
    } elseif ($type === 'datepicker') {
        $selectors = array_merge($selectors, ['.date-month-panel', '.date-weeks-panel', '.date-cell', '.date-week-card']);
    } elseif ($type === 'location') {
        $selectors = array_merge($selectors, ['.location-map-embed']);
    }

    return array_values(array_unique($selectors));
}

function app_render_block(array $block, array $theme, bool $editorMode = true, array $page = []): string
{
    $uid = (string) ($block['uid'] ?? app_uuid());
    $type = (string) ($block['type'] ?? '');
    $variant = (int) ($block['variant'] ?? 1);
    $canvasSettings = app_normalize_canvas_settings($page['canvas'] ?? null);
    $footerFollowContainerMaxWidth = (bool) ($canvasSettings['footerFollowContainerMaxWidth'] ?? false);
    $defaultWidthPercent = app_default_block_width_percent($type, $variant);
    $widthPercent = app_normalize_block_width_percent($block['widthPercent'] ?? (((bool) ($block['fullWidth'] ?? false)) ? 100 : $defaultWidthPercent), $defaultWidthPercent);
    $isFullWidth = $widthPercent >= 100;
    $backgroundColor = app_normalize_block_background_color((string) ($block['backgroundColor'] ?? ''));
    $backgroundOpacity = max(0, min(100, (float) ($block['backgroundOpacity'] ?? app_default_block_background_opacity($type, $theme)))) / 100;
    $cardBackgroundOpacityRaw = array_key_exists('cardBackgroundOpacity', $block)
        ? $block['cardBackgroundOpacity']
        : (!array_key_exists('backgroundColor', $block)
            ? ($block['backgroundOpacity'] ?? null)
            : null);
    $cardBackgroundOpacity = max(0, min(100, (float) ($cardBackgroundOpacityRaw ?? app_default_block_card_background_opacity($type, $theme)))) / 100;
    $foregroundOpacity = max(0, min(100, (float) ($block['foregroundOpacity'] ?? 100))) / 100;
    $fontScale = max(25, min(300, (float) ($block['fontScale'] ?? 100))) / 100;
    $verticalPaddingScale = max(0, min(300, (float) ($block['verticalPaddingScale'] ?? app_default_block_vertical_padding_scale($type)))) / 100;
    $cardColor = app_normalize_block_card_color((string) ($block['cardColor'] ?? ''));
    $cardBorderColor = array_key_exists('cardBorderColor', $block)
        ? app_normalize_block_card_border_color((string) ($block['cardBorderColor'] ?? ''))
        : '';
    $cardBorderStyle = array_key_exists('cardBorderStyle', $block)
        ? app_normalize_block_card_border_style($block['cardBorderStyle'] ?? '')
        : '';
    $cardBorderWidth = array_key_exists('cardBorderWidth', $block)
        ? app_normalize_block_card_border_width($block['cardBorderWidth'], $theme)
        : null;
    $resolvedCardBorderColor = $cardBorderColor !== '' ? resolve_color($cardBorderColor) : '';
    $animation = app_normalize_block_animation($block['animation'] ?? 'none');
    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
    $headingColors = app_normalize_block_heading_colors(
        $data['headingColors'] ?? null,
        $data['headingColor'] ?? null
    );

    $partialPath = __DIR__ . '/../partials/blocks/' . $type . '/v' . $variant . '.php';
    if (!is_file($partialPath)) {
        $inner = '<section class="block"><div class="container"><div class="surface">Missing block: ' . esc($type) . ' v' . esc((string) $variant) . '</div></div></section>';
    } else {
        app_push_block_render_context([
            'uid' => $uid,
            'type' => $type,
            'variant' => $variant,
            'editorMode' => $editorMode,
            'page' => $page,
        ]);
        ob_start();
        try {
            include $partialPath;
            $inner = (string) ob_get_clean();
        } finally {
            app_pop_block_render_context();
        }
    }

    $wrapperClass = 'preview-block' . ($isFullWidth ? ' is-full-width' : '');
    $cardBorderScope = 'block-' . substr(sha1($uid), 0, 12);
    $cardBorderOverrideRules = [];
    if ($cardBorderWidth !== null) {
        $cardBorderOverrideRules[] = 'border-width:' . $cardBorderWidth . 'px !important;';
    }
    if ($cardBorderStyle !== '') {
        $cardBorderOverrideRules[] = 'border-style:' . $cardBorderStyle . ' !important;';
    }
    if ($resolvedCardBorderColor !== '') {
        $cardBorderOverrideRules[] = 'border-color:' . $resolvedCardBorderColor . ' !important;';
    }
    $cardBorderOverrideStyleTag = '';
    if ($cardBorderOverrideRules !== []) {
        $selectorPrefix = '.preview-block[data-block-scope="' . $cardBorderScope . '"] ';
        $selectorList = implode(',', array_map(static fn(string $selector): string => $selectorPrefix . $selector, app_block_card_override_selectors($type)));
        $cardBorderOverrideStyleTag = '<style>' . $selectorList . '{' . implode('', $cardBorderOverrideRules) . '}</style>';
    }
    $isContainerMaxExempt = $type === 'header' || ($type === 'footer' && !$footerFollowContainerMaxWidth);
    if ($isContainerMaxExempt) {
        $wrapperClass .= ' is-container-max-exempt';
    }
    if ($type === 'header' && app_data_bool($data, 'sticky', true)) {
        $wrapperClass .= ' is-sticky-header';
    }
    $style = '--block-background-opacity:' . $backgroundOpacity
        . ';--block-card-bg-opacity:' . $cardBackgroundOpacity
        . ';--block-card-fill-solid:var(--block-card-color,var(--card-color))'
        . ';--block-card-fill:color-mix(in srgb,var(--block-card-color,var(--card-color)) calc(var(--block-card-bg-opacity) * 100%),transparent)'
        . ';--block-card-fill-soft-bg:color-mix(in srgb,var(--block-card-color,var(--card-color)) calc(var(--block-card-bg-opacity) * 100%),var(--background))'
        . ';--block-card-fill-soft-transparent:color-mix(in srgb,var(--block-card-color,var(--card-color)) calc(var(--block-card-bg-opacity) * 100%),transparent)'
        . ';--block-fg-opacity:' . $foregroundOpacity
        . ';--block-font-scale:' . $fontScale
        . ';--block-width-percent:' . $widthPercent
        . ';--block-pad-y-scale:' . $verticalPaddingScale . ';';
    if ($backgroundColor === 'theme-gradient') {
        $style .= '--block-background-fill:linear-gradient(var(--grad-angle),var(--grad-start),var(--grad-end));';
    } elseif ($backgroundColor !== '') {
        $style .= '--block-background-fill:' . resolve_color($backgroundColor) . ';';
    }
    if ($cardColor === 'theme-gradient') {
        $style .= '--block-card-fill-solid:linear-gradient(var(--grad-angle),var(--grad-start),var(--grad-end));'
            . '--block-card-fill:linear-gradient(var(--grad-angle),color-mix(in srgb,var(--grad-start) calc(var(--block-card-bg-opacity) * 100%),transparent),color-mix(in srgb,var(--grad-end) calc(var(--block-card-bg-opacity) * 100%),transparent));'
            . '--block-card-fill-soft-bg:linear-gradient(var(--grad-angle),color-mix(in srgb,var(--grad-start) calc(var(--block-card-bg-opacity) * 100%),var(--background)),color-mix(in srgb,var(--grad-end) calc(var(--block-card-bg-opacity) * 100%),var(--background)));'
            . '--block-card-fill-soft-transparent:linear-gradient(var(--grad-angle),color-mix(in srgb,var(--grad-start) calc(var(--block-card-bg-opacity) * 100%),transparent),color-mix(in srgb,var(--grad-end) calc(var(--block-card-bg-opacity) * 100%),transparent));';
    } elseif ($cardColor !== '') {
        $style .= '--block-card-color:' . resolve_color($cardColor) . ';';
    }
    if ($cardBorderColor !== '') {
        $style .= '--block-card-border-color:' . $resolvedCardBorderColor . ';--card-border-color:' . $resolvedCardBorderColor . ';';
    }
    if ($cardBorderWidth !== null) {
        $style .= '--block-card-border-width:' . $cardBorderWidth . 'px;';
    }
    if ($cardBorderStyle !== '') {
        $style .= '--block-card-border-style:' . $cardBorderStyle . ';';
    }
    foreach ($headingColors as $headingLevel => $headingColor) {
        $style .= '--block-heading-color-' . $headingLevel . ':' . resolve_color($headingColor) . ';';
    }

    $wrapperAttrs = 'class="' . attr($wrapperClass) . '" style="' . attr($style) . '" data-block-animation="' . attr($animation) . '" data-block-uid="' . attr($uid) . '" data-block-scope="' . attr($cardBorderScope) . '"';
    $actions = '';
    $blockLabel = '';
    if ($editorMode) {
        $definition = function_exists('app_block_definition') ? app_block_definition($type) : null;
        $labelBase = is_array($definition) ? (string) ($definition['name'] ?? $type) : ucfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $type))));
        $labelText = $labelBase . '-v' . $variant;
        $labelIcon = app_preview_block_icon_class($type);
        $wrapperAttrs .= ' draggable="true" data-block-type="' . attr($type) . '"';
        $blockLabel = '<button class="preview-block-label" type="button" data-action="edit" aria-label="' . attr(app_tr('Edit block')) . '"><i class="pi ' . attr($labelIcon) . '" aria-hidden="true"></i><span>' . esc($labelText) . '</span></button>';
        $actions = '<div class="preview-block-actions"><button class="preview-action" type="button" data-action="move-up" aria-label="' . attr(app_tr('Move block up')) . '"><i class="pi pi-upwardsarrow" aria-hidden="true"></i></button><button class="preview-action" type="button" data-action="move-down" aria-label="' . attr(app_tr('Move block down')) . '"><i class="pi pi-downwardsarrow" aria-hidden="true"></i></button><button class="preview-action" type="button" data-action="edit" aria-label="' . attr(app_tr('Edit block')) . '"><i class="pi pi-edit" aria-hidden="true"></i></button><button class="preview-action" type="button" data-action="copy" aria-label="' . attr(app_tr('Copy block')) . '"><i class="pi pi-copy" aria-hidden="true"></i></button><button class="preview-action" type="button" data-action="paste" aria-label="' . attr(app_tr('Paste copied block over this block')) . '"><i class="pi pi-clipboard" aria-hidden="true"></i></button><button class="preview-action danger" type="button" data-action="delete" aria-label="' . attr(app_tr('Delete block')) . '"><i class="pi pi-bin" aria-hidden="true"></i></button></div>';
    }

    return '<div ' . $wrapperAttrs . '>' . $blockLabel . $actions . $inner . $cardBorderOverrideStyleTag . '</div>';
}

function app_render_wetted_document(array $payload, bool $embeddedPreview = false): string
{
    $normalized = app_import_sitekit_payload($payload);
    $theme = $normalized['theme'];
    $page = $normalized['page'];
    app_set_render_site_base_url((string) ($normalized['site']['baseUrl'] ?? ''));
    app_set_render_site_assets(is_array($normalized['site']['assets'] ?? null) ? $normalized['site']['assets'] : []);
    $siteName = trim((string) (($normalized['site']['name'] ?? '') ?: ''));
    $pageLocale = app_normalize_locale((string) ($page['locale'] ?? app_current_locale()));
    $title = trim((string) ($page['title'] ?? 'SiteKit Page'));
    $slug = app_slugify((string) ($page['slug'] ?? $title));
    $themeId = (string) app_theme_value($theme, ['theme', 'id'], 'custom');
    $fonts = google_fonts_links($theme);
    $style = theme_css_vars($theme) . app_canvas_css_vars($page) . preview_base_css() . app_wetted_preview_overrides_css($embeddedPreview);
    $html = app_render_page_html($page, $theme, false);

    return '<!doctype html>
<html lang="' . attr($pageLocale) . '" data-theme-id="' . attr($themeId) . '" data-page-slug="' . attr($slug) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.classList.add("has-runtime");</script>
<title>' . esc(app_browser_page_title($siteName, $title !== '' ? $title : app_tr('SiteKit Page'))) . '</title>
<link rel="stylesheet" href="static/popicon.css">
' . $fonts . '
<style>' . $style . '</style>
</head>
<body data-link-animation="' . attr(app_canvas_link_animation($page['canvas'] ?? [])) . '">
' . $html . '
' . app_wetted_runtime_script() . '
</body>
</html>';
}

function app_render_wetted_site_document(array $payload, bool $embeddedPreview = false): string
{
    $normalized = app_import_sitekit_payload($payload);
    $theme = $normalized['theme'];
    $site = is_array($normalized['site'] ?? null) ? $normalized['site'] : [];
    app_set_render_site_base_url((string) ($site['baseUrl'] ?? ''));
    app_set_render_site_assets(is_array($site['assets'] ?? null) ? $site['assets'] : []);
    $sitePages = is_array($site['pages'] ?? null) ? array_values($site['pages']) : [];
    if (count($sitePages) <= 1) {
        return app_render_wetted_document($normalized, $embeddedPreview);
    }

    $activePageId = app_slugify((string) ($site['activePageId'] ?? ''));
    $activePage = $sitePages[0];
    foreach ($sitePages as $sitePage) {
        if (!is_array($sitePage)) {
            continue;
        }
        $candidateId = app_slugify((string) ($sitePage['id'] ?? ''));
        if ($candidateId !== '' && $candidateId === $activePageId) {
            $activePage = $sitePage;
            break;
        }
    }

    $siteName = trim((string) ($site['name'] ?? app_tr('SiteKit Site')));
    $activeTitle = trim((string) ($activePage['title'] ?? $siteName));
    $activeSlug = app_slugify((string) ($activePage['slug'] ?? $activeTitle));
    $activeLocale = app_normalize_locale((string) ($activePage['locale'] ?? app_current_locale()));
    $themeId = (string) app_theme_value($theme, ['theme', 'id'], 'custom');
    $fonts = google_fonts_links($theme);
    $style = theme_css_vars($theme)
        . app_canvas_css_vars($activePage)
        . preview_base_css()
        . app_wetted_preview_overrides_css($embeddedPreview)
        . app_wetted_site_pages_css($embeddedPreview);

    $pagesHtml = '';
    foreach ($sitePages as $index => $sitePage) {
        if (!is_array($sitePage)) {
            continue;
        }
        $pageId = app_slugify((string) ($sitePage['id'] ?? ''));
        if ($pageId === '') {
            $pageId = 'page-' . ($index + 1);
        }
        $pageTitle = trim((string) ($sitePage['title'] ?? app_tr('Page {index}', ['index' => $index + 1])));
        if ($pageTitle === '') {
            $pageTitle = app_tr('Page {index}', ['index' => $index + 1]);
        }
        $pageSlug = app_slugify((string) ($sitePage['slug'] ?? $pageTitle));
        $pageLocale = app_normalize_locale((string) ($sitePage['locale'] ?? $activeLocale));
        if ($pageSlug === '') {
            $pageSlug = $pageId;
        }
        $isActive = $pageId === app_slugify((string) ($activePage['id'] ?? ''));
        $canvasDecl = app_canvas_css_declarations(is_array($sitePage['canvas'] ?? null) ? $sitePage['canvas'] : []);
        $pageLinkAnimation = app_canvas_link_animation(is_array($sitePage['canvas'] ?? null) ? $sitePage['canvas'] : []);
        $pagesHtml .= '<section class="sitekit-site-page' . ($isActive ? ' is-active' : '') . '" data-site-page-id="' . attr($pageId) . '" data-site-page-slug="' . attr($pageSlug) . '" data-site-page-title="' . attr($pageTitle) . '" data-site-name="' . attr($siteName) . '" data-site-page-locale="' . attr($pageLocale) . '" data-site-canvas="' . attr($canvasDecl) . '" data-site-link-animation="' . attr($pageLinkAnimation) . '">';
        $pagesHtml .= app_render_page_html($sitePage, $theme, false);
        $pagesHtml .= '</section>';
    }

    return '<!doctype html>
<html lang="' . attr($activeLocale) . '" data-theme-id="' . attr($themeId) . '" data-page-slug="' . attr($activeSlug) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.classList.add("has-runtime");</script>
<title>' . esc(app_browser_page_title($siteName, $activeTitle)) . '</title>
<link rel="stylesheet" href="static/popicon.css">
' . $fonts . '
<style>' . $style . '</style>
</head>
<body data-site-page-id="' . attr(app_slugify((string) ($activePage['id'] ?? ''))) . '" data-link-animation="' . attr(app_canvas_link_animation($activePage['canvas'] ?? [])) . '">
<div class="sitekit-site-pages">' . $pagesHtml . '</div>
' . app_wetted_runtime_script() . '
' . app_wetted_site_pages_runtime_script() . '
</body>
</html>';
}

function app_data_text(array $data, string $key, string $fallback = ''): string
{
    $value = $data[$key] ?? $fallback;
    return is_scalar($value) ? (string) $value : $fallback;
}

function app_data_number(array $data, string $key, float $fallback = 0): float
{
    $value = $data[$key] ?? $fallback;
    return is_numeric($value) ? (float) $value : $fallback;
}

function app_data_columns(array $data, string $key, int $fallback = 3, int $min = 1, int $max = 6): int
{
    $raw = (int) app_data_number($data, $key, (float) $fallback);
    return max($min, min($max, $raw));
}

function app_data_bool(array $data, string $key, bool $fallback = false): bool
{
    return isset($data[$key]) ? (bool) $data[$key] : $fallback;
}

function app_data_list(array $data, string $key): array
{
    $value = $data[$key] ?? [];
    return is_array($value) ? array_values($value) : [];
}

function app_data_heading_level(array $data, string $fallback = 'h2'): string
{
    $fallbackTag = strtolower(trim($fallback));
    if (preg_match('/^h[2-6]$/', $fallbackTag) !== 1) {
        $fallbackTag = 'h2';
    }

    $value = strtolower(trim(app_data_text($data, 'headingLevel', $fallbackTag)));
    return preg_match('/^h[2-6]$/', $value) === 1 ? $value : $fallbackTag;
}

function app_push_block_render_context(array $context): void
{
    if (!isset($GLOBALS['__app_block_render_context_stack']) || !is_array($GLOBALS['__app_block_render_context_stack'])) {
        $GLOBALS['__app_block_render_context_stack'] = [];
    }

    $GLOBALS['__app_block_render_context_stack'][] = $context;
}

function app_pop_block_render_context(): void
{
    if (!isset($GLOBALS['__app_block_render_context_stack']) || !is_array($GLOBALS['__app_block_render_context_stack'])) {
        return;
    }

    array_pop($GLOBALS['__app_block_render_context_stack']);
}

function app_current_block_render_context(): array
{
    $stack = $GLOBALS['__app_block_render_context_stack'] ?? [];
    if (!is_array($stack) || $stack === []) {
        return [];
    }

    $context = end($stack);
    return is_array($context) ? $context : [];
}

function app_current_page_shared_contact(): array
{
    $context = app_current_block_render_context();
    $page = is_array($context['page'] ?? null) ? $context['page'] : [];
    $locale = app_normalize_locale((string) ($page['locale'] ?? app_current_locale()));

    return app_normalize_shared_contact_profile($page['sharedContact'] ?? null, $locale);
}

function app_current_block_editor_mode(): bool
{
    $context = app_current_block_render_context();
    return (bool) ($context['editorMode'] ?? false);
}

function app_safe_tag_name(string $tag): string
{
    return preg_match('/^[a-z][a-z0-9:-]*$/i', $tag) === 1 ? strtolower($tag) : 'span';
}

function app_html_attributes(array $attrs): string
{
    $parts = [];
    foreach ($attrs as $name => $value) {
        $attrName = trim((string) $name);
        if ($attrName === '' || $value === null || $value === false) {
            continue;
        }

        if ($value === true) {
            $parts[] = $attrName;
            continue;
        }

        $parts[] = $attrName . '="' . attr((string) $value) . '"';
    }

    return $parts === [] ? '' : ' ' . implode(' ', $parts);
}

function app_attributes_add_class(array $attrs, string $classNames): array
{
    $existing = preg_split('/\s+/', trim((string) ($attrs['class'] ?? ''))) ?: [];
    $incoming = preg_split('/\s+/', trim($classNames)) ?: [];

    $classes = [];
    foreach (array_merge($existing, $incoming) as $className) {
        $candidate = trim((string) $className);
        if ($candidate === '' || in_array($candidate, $classes, true)) {
            continue;
        }
        $classes[] = $candidate;
    }

    if ($classes !== []) {
        $attrs['class'] = implode(' ', $classes);
    }

    return $attrs;
}

function app_editable_attributes(array $path, bool $multiline = false, array $attrs = []): array
{
    if (!app_current_block_editor_mode()) {
        return $attrs;
    }

    $class = trim((string) ($attrs['class'] ?? ''));
    $attrs['class'] = trim($class . ' preview-editable-text');
    $attrs['data-edit-path'] = (string) json_encode(array_values($path), JSON_UNESCAPED_SLASHES);
    $attrs['data-edit-mode'] = $multiline ? 'multiline' : 'single';

    return $attrs;
}

function app_preview_image_drop_attributes(array $path, array $attrs = [], string $label = ''): array
{
    if (!app_current_block_editor_mode()) {
        return $attrs;
    }

    $attrs = app_attributes_add_class($attrs, 'preview-image-drop-target');
    $attrs['data-image-drop-path'] = (string) json_encode(array_values($path), JSON_UNESCAPED_SLASHES);
    $attrs['data-preview-inspector-group'] = 'images';
    if (trim($label) !== '') {
        $attrs['data-preview-inspector-label'] = trim($label);
    }

    return $attrs;
}

function app_editable_tag(string $tag, string $text, array $path, bool $multiline = false, array $attrs = []): string
{
    $safeTag = app_safe_tag_name($tag);
    $markup = $multiline ? nl2br(esc($text)) : esc($text);
    return '<' . $safeTag . app_html_attributes(app_editable_attributes($path, $multiline, $attrs)) . '>' . $markup . '</' . $safeTag . '>';
}

function app_editable_heading(array $data, string $fallbackTag, string $text, array $path, bool $multiline = false, array $attrs = []): string
{
    $tag = app_data_heading_level($data, $fallbackTag);
    $attrs = app_attributes_add_class($attrs, 'block-heading block-heading-' . $tag);
    return app_editable_tag($tag, $text, $path, $multiline, $attrs);
}

function app_editable_span(string $text, array $path, bool $multiline = false, array $attrs = []): string
{
    return app_editable_tag('span', $text, $path, $multiline, $attrs);
}

function app_set_render_site_assets(array $assets): void
{
    $lookup = [];
    foreach ($assets as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $id = trim((string) ($asset['id'] ?? ''));
        $dataUrl = app_safe_url((string) ($asset['dataUrl'] ?? ''));
        if ($id === '' || $dataUrl === '') {
            continue;
        }
        $lookup[$id] = [
            'dataUrl' => $dataUrl,
            'filename' => trim((string) ($asset['filename'] ?? '')),
            'mime' => trim((string) ($asset['mime'] ?? 'image/webp')),
        ];
    }
    $GLOBALS['app_render_site_assets'] = $lookup;
}

function app_render_site_asset_url(string $value): string
{
    $text = trim($value);
    if ($text === '' || !str_starts_with($text, 'sitekit-asset://')) {
        return '';
    }
    $raw = substr($text, strlen('sitekit-asset://'));
    if ($raw === false || $raw === '') {
        return '';
    }
    $segments = explode('/', $raw, 2);
    $assetId = trim(rawurldecode((string) ($segments[0] ?? '')));
    if ($assetId === '') {
        return '';
    }
    $assets = is_array($GLOBALS['app_render_site_assets'] ?? null) ? $GLOBALS['app_render_site_assets'] : [];
    $asset = $assets[$assetId] ?? null;
    if (!is_array($asset)) {
        return '';
    }
    return app_safe_url((string) ($asset['dataUrl'] ?? ''));
}

function app_image_from_entry(array $entry, array $theme): string
{
    $mode = (string) ($entry['imageMode'] ?? 'manual');
    $resolved = app_safe_url((string) ($entry['resolvedImageUrl'] ?? ''));
    if ($resolved !== '') {
        return $resolved;
    }

    $manualImageUrl = app_render_site_asset_url((string) ($entry['imageUrl'] ?? ''));
    if ($manualImageUrl === '') {
        $manualImageUrl = app_safe_url((string) ($entry['imageUrl'] ?? ''));
    }
    if ($mode === 'manual' && $manualImageUrl !== '') {
        return $manualImageUrl;
    }

    if ($mode === 'themeRandom') {
        return app_random_value(app_theme_image_urls($theme), '');
    }

    if ($mode === 'listRandom') {
        return app_random_value(app_image_source_list(), '');
    }

    $fallbackKeys = ['imageUrl', 'url'];
    foreach ($fallbackKeys as $key) {
        $url = app_render_site_asset_url((string) ($entry[$key] ?? ''));
        if ($url === '') {
            $url = app_safe_url((string) ($entry[$key] ?? ''));
        }
        if ($url === '' || $url === '#') {
            continue;
        }
        return $url;
    }

    return '';
}

function app_image_width_percent(array $entry, int $default = 100): int
{
    $raw = array_key_exists('imageWidthPercent', $entry) ? $entry['imageWidthPercent'] : $default;
    $value = (int) round((float) $raw);
    return max(0, min(100, $value));
}

function app_image_alignment(array $entry, string $default = 'left'): string
{
    $raw = strtolower(trim((string) ($entry['imageAlign'] ?? $default)));
    return in_array($raw, ['left', 'center', 'right'], true) ? $raw : $default;
}

function app_image_position(array $entry, string $default = 'center'): string
{
    $raw = strtolower(trim((string) ($entry['imagePosition'] ?? $default)));
    $map = [
        'center' => '50% 50%',
        'top' => '50% 0%',
        'bottom' => '50% 100%',
        'left' => '0% 50%',
        'right' => '100% 50%',
        'top-left' => '0% 0%',
        'top-right' => '100% 0%',
        'bottom-left' => '0% 100%',
        'bottom-right' => '100% 100%',
    ];

    return $map[$raw] ?? '50% 50%';
}

function app_image_wrapper_style(array $entry, int $defaultWidth = 100, string $defaultAlign = 'left'): string
{
    $width = app_image_width_percent($entry, $defaultWidth);
    $align = app_image_alignment($entry, $defaultAlign);
    $styles = ['width:' . $width . '%', 'max-width:100%'];

    if ($align === 'center') {
        $styles[] = 'margin-left:auto';
        $styles[] = 'margin-right:auto';
    } elseif ($align === 'right') {
        $styles[] = 'margin-left:auto';
    } else {
        $styles[] = 'margin-right:auto';
    }

    return implode(';', $styles) . ';';
}

function app_image_element_style(array $entry, bool $includeWidth = false, int $defaultWidth = 100, string $defaultAlign = 'left'): string
{
    $styles = ['object-position:' . app_image_position($entry)];
    if ($includeWidth) {
        $width = app_image_width_percent($entry, $defaultWidth);
        $align = app_image_alignment($entry, $defaultAlign);
        $styles[] = 'width:' . $width . '%';
        $styles[] = 'max-width:100%';
        $styles[] = 'display:block';
        if ($align === 'center') {
            $styles[] = 'margin-left:auto';
            $styles[] = 'margin-right:auto';
        } elseif ($align === 'right') {
            $styles[] = 'margin-left:auto';
        } else {
            $styles[] = 'margin-right:auto';
        }
    }

    return implode(';', $styles) . ';';
}

function app_icon_placeholder(): string
{
    return '<div class="placeholder-image" role="img" aria-label="' . attr(app_tr('placeholder image')) . '"><i class="pi pi-image" aria-hidden="true"></i></div>';
}

function app_block_open(string $type, int $variant, bool $compact = false, string $extraClass = ''): void
{
    $class = 'block block-' . $type . ' block-v' . $variant;
    if ($compact) {
        $class .= ' compact';
    }
    $extraClass = trim($extraClass);
    if ($extraClass !== '') {
        $class .= ' ' . $extraClass;
    }
    echo '<section class="' . attr($class) . '"><div class="container">';
}

function app_block_close(): void
{
    echo '</div></section>';
}
