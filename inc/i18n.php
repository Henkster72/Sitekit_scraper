<?php
declare(strict_types=1);

function app_i18n_locale_names(): array
{
    return [
        'en' => 'English',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'nl' => 'Nederlands',
        'es' => 'Español',
        'pt' => 'Português',
    ];
}

function app_i18n_locale_catalog_paths(string $locale): array
{
    $resolvedLocale = app_normalize_locale($locale);
    $paths = [];

    $basePath = __DIR__ . '/i18n/' . $resolvedLocale . '.php';
    if (is_file($basePath)) {
        $paths[] = $basePath;
    }

    $domainPaths = glob(__DIR__ . '/i18n/*/' . $resolvedLocale . '.php') ?: [];
    sort($domainPaths);

    foreach ($domainPaths as $path) {
        if (is_file($path)) {
            $paths[] = $path;
        }
    }

    return $paths;
}

function app_i18n_locale_catalog(string $locale): array
{
    static $catalogs = [];

    $resolvedLocale = app_normalize_locale($locale);
    if (isset($catalogs[$resolvedLocale])) {
        return $catalogs[$resolvedLocale];
    }

    $catalog = [];
    foreach (app_i18n_locale_catalog_paths($resolvedLocale) as $path) {
        $segment = require $path;
        if (!is_array($segment)) {
            continue;
        }
        $catalog = array_replace($catalog, $segment);
    }

    $catalogs[$resolvedLocale] = $catalog;
    return $catalogs[$resolvedLocale];
}

function app_i18n_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $sourceStrings = [];
    foreach (array_keys(app_i18n_locale_names()) as $locale) {
        $sourceStrings[$locale] = app_i18n_locale_catalog($locale);
    }

    $catalog = [
        'locales' => app_i18n_locale_names(),
        'source_strings' => $sourceStrings,
    ];

    return $catalog;
}

function app_supported_locales(): array
{
    return array_keys(app_i18n_locale_names());
}

function app_normalize_locale(?string $locale, string $fallback = 'en'): string
{
    $candidate = strtolower(trim((string) $locale));
    if ($candidate === '') {
        return $fallback;
    }

    if (str_contains($candidate, '-')) {
        $candidate = explode('-', $candidate, 2)[0];
    } elseif (str_contains($candidate, '_')) {
        $candidate = explode('_', $candidate, 2)[0];
    }

    return in_array($candidate, app_supported_locales(), true) ? $candidate : $fallback;
}

function app_detect_locale(): string
{
    $sources = [
        $_SERVER['HTTP_X_SITEKIT_LOCALE'] ?? null,
        $_GET['lang'] ?? null,
        $_POST['lang'] ?? null,
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
    ];

    foreach ($sources as $source) {
        if (!is_string($source) || trim($source) === '') {
            continue;
        }
        foreach (preg_split('/[,;]/', $source) ?: [] as $candidate) {
            $locale = app_normalize_locale($candidate, '');
            if ($locale !== '') {
                return $locale;
            }
        }
    }

    return 'en';
}

function app_current_locale(): string
{
    if (isset($GLOBALS['__app_locale']) && is_string($GLOBALS['__app_locale'])) {
        return app_normalize_locale($GLOBALS['__app_locale']);
    }

    $GLOBALS['__app_locale'] = app_detect_locale();
    return $GLOBALS['__app_locale'];
}

function app_set_locale(string $locale): void
{
    $GLOBALS['__app_locale'] = app_normalize_locale($locale);
}

function app_source_translation_map(?string $locale = null): array
{
    $resolvedLocale = app_normalize_locale($locale ?? app_current_locale());
    return app_i18n_locale_catalog($resolvedLocale);
}

function app_tr(string $source, array $vars = [], ?string $locale = null): string
{
    $resolvedLocale = app_normalize_locale($locale ?? app_current_locale());
    $translated = app_source_translation_map($resolvedLocale)[$source] ?? $source;

    foreach ($vars as $key => $value) {
        $translated = str_replace('{' . $key . '}', (string) $value, $translated);
    }

    return $translated;
}

function app_localize_data($value, ?string $locale = null)
{
    if (is_string($value)) {
        return app_tr($value, [], $locale);
    }

    if (!is_array($value)) {
        return $value;
    }

    $localized = [];
    foreach ($value as $key => $item) {
        $localized[$key] = app_localize_data($item, $locale);
    }

    return $localized;
}

function app_i18n_client_payload(): array
{
    $sourceStrings = [];
    foreach (array_keys(app_i18n_locale_names()) as $locale) {
        $sourceStrings[$locale] = app_i18n_locale_catalog($locale);
    }

    return [
        'locale' => app_current_locale(),
        'locales' => app_i18n_locale_names(),
        'sourceStrings' => $sourceStrings,
    ];
}
