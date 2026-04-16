<?php
declare(strict_types=1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/blocks.php';

interface SiteKitStorageAdapter
{
    public function id(): string;
    public function label(): string;
    public function capabilities(): array;
    public function listProjects(): array;
    public function loadProject(string $projectId): ?array;
    public function saveProject(array $payload, ?string $projectId = null): array;
    public function deleteProject(string $projectId): bool;
    public function syncStatus(?string $projectId = null): array;
    public function putAsset(string $assetId, string $dataUrl, array $meta = []): array;
    public function getAsset(string $assetId): ?array;
}

function app_storage_root_dir(): string
{
    app_load_repo_env();

    $configured = trim((string) (getenv('SITEKIT_STORAGE_ROOT') ?: ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $defaultMountedRoot = '/home/henk/vbook_web/sitekit';
    if (is_dir($defaultMountedRoot)) {
        return $defaultMountedRoot;
    }

    return dirname(__DIR__);
}

function app_storage_pages_dir(string $rootDir): string
{
    return rtrim($rootDir, '/') . '/data/pages';
}

function app_nextcloud_storage_root_dir(?string $preferredRoot = null): string
{
    $preferred = trim((string) ($preferredRoot ?? ''));
    if ($preferred !== '') {
        return rtrim($preferred, '/');
    }

    $configured = trim(app_sitekit_nextcloud_root());
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    return rtrim(app_sitekit_nextcloud_prompt_default(), '/');
}

function app_nextcloud_pages_dir(?string $preferredRoot = null): string
{
    return app_nextcloud_storage_root_dir($preferredRoot) . '/SiteKit/data/pages';
}

function app_pages_dir(?string $rootDir = null): string
{
    return app_storage_pages_dir($rootDir !== null && trim($rootDir) !== '' ? $rootDir : app_storage_root_dir());
}

function app_page_path(string $slug, ?string $pagesDir = null): string
{
    $safeSlug = app_slugify($slug);
    $dir = $pagesDir !== null && trim($pagesDir) !== '' ? rtrim($pagesDir, '/') : app_pages_dir();
    return $dir . '/' . $safeSlug . '.json';
}

function app_storage_project_slug(array $payload, ?string $preferredProjectId = null): string
{
    $preferred = app_slugify((string) ($preferredProjectId ?? ''));
    if ($preferred !== '') {
        return $preferred;
    }

    $siteName = trim((string) ($payload['site']['name'] ?? ''));
    $pageSlug = trim((string) ($payload['page']['slug'] ?? ''));
    $pageTitle = trim((string) ($payload['page']['title'] ?? ''));

    return app_slugify($siteName !== '' ? $siteName : ($pageSlug !== '' ? $pageSlug : ($pageTitle !== '' ? $pageTitle : 'project')));
}

final class SiteKitDirectoryStorageAdapter implements SiteKitStorageAdapter
{
    private string $id;
    private string $label;
    private string $mode;
    private string $storageRoot;
    private string $pagesDir;
    private array $extraStatus;

    public function __construct(
        string $id,
        string $label,
        string $mode,
        string $storageRoot,
        string $pagesDir,
        array $extraStatus = []
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->mode = $mode;
        $this->storageRoot = rtrim($storageRoot, '/');
        $this->pagesDir = rtrim($pagesDir, '/');
        $this->extraStatus = $extraStatus;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function capabilities(): array
    {
        return [
            'listProjects' => true,
            'loadProject' => true,
            'saveProject' => true,
            'deleteProject' => true,
            'putAsset' => false,
            'getAsset' => false,
            'syncStatus' => true,
        ];
    }

    public function listProjects(): array
    {
        $dir = $this->pagesDir;
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        $paths = glob($dir . '/*.json') ?: [];
        sort($paths);

        foreach ($paths as $path) {
            if (!is_string($path) || !is_file($path)) {
                continue;
            }
            $slug = app_slugify(pathinfo($path, PATHINFO_FILENAME));
            if ($slug === '') {
                continue;
            }

            $raw = app_read_json_file($path, []);
            $title = '';
            $siteName = '';
            $locale = '';
            try {
                $payload = $raw !== [] ? app_import_sitekit_payload($raw) : [];
                $title = trim((string) ($payload['page']['title'] ?? ''));
                $siteName = trim((string) ($payload['site']['name'] ?? ''));
                $locale = trim((string) ($payload['page']['locale'] ?? ''));
            } catch (RuntimeException $_e) {
                $title = '';
                $siteName = '';
                $locale = '';
            }

            $mtime = @filemtime($path);
            $items[] = [
                'id' => $slug,
                'slug' => $slug,
                'path' => $path,
                'title' => $title !== '' ? $title : $slug,
                'siteName' => $siteName,
                'locale' => $locale,
                'updatedAt' => $mtime ? gmdate('c', $mtime) : '',
            ];
        }

        return $items;
    }

    public function loadProject(string $projectId): ?array
    {
        $slug = app_slugify($projectId);
        if ($slug === '') {
            return null;
        }
        $path = app_page_path($slug, $this->pagesDir);
        if (!is_file($path)) {
            return null;
        }

        $payload = app_read_json_file($path, []);
        if ($payload === []) {
            return null;
        }

        return app_import_sitekit_payload($payload);
    }

    public function saveProject(array $payload, ?string $projectId = null): array
    {
        $normalized = app_import_sitekit_payload($payload);
        $slug = app_storage_project_slug($normalized, $projectId);
        if ($slug === '') {
            throw new RuntimeException('Missing project identifier.');
        }

        $dir = $this->pagesDir;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Failed to create project storage directory.');
        }

        $path = app_page_path($slug, $this->pagesDir);
        $ok = app_write_json_file($path, $normalized);

        return [
            'ok' => $ok,
            'slug' => $slug,
            'path' => $path,
            'payload' => $normalized,
        ];
    }

    public function deleteProject(string $projectId): bool
    {
        $slug = app_slugify($projectId);
        if ($slug === '') {
            return false;
        }
        $path = app_page_path($slug, $this->pagesDir);
        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    public function syncStatus(?string $projectId = null): array
    {
        $nextcloudRoot = app_sitekit_nextcloud_root();
        $nextcloudUrl = app_sitekit_nextcloud_url();
        return array_merge([
            'adapterId' => $this->id(),
            'adapterLabel' => $this->label(),
            'mode' => $this->mode,
            'connected' => true,
            'projectId' => $projectId !== null ? app_slugify($projectId) : '',
            'storageRoot' => $this->storageRoot,
            'pagesDir' => $this->pagesDir,
            'pagesDirExists' => is_dir($this->pagesDir),
            'pagesDirWritable' => is_dir($this->pagesDir) ? is_writable($this->pagesDir) : is_writable(dirname($this->pagesDir)),
            'nextcloudRoot' => $nextcloudRoot,
            'nextcloudUrl' => $nextcloudUrl,
            'nextcloudSuggestedSavePath' => app_sitekit_nextcloud_prompt_default(),
            'nextcloudConfigured' => $nextcloudRoot !== '' || $nextcloudUrl !== '',
        ], $this->extraStatus);
    }

    public function putAsset(string $assetId, string $dataUrl, array $meta = []): array
    {
        throw new RuntimeException('The local filesystem project adapter does not manage assets yet.');
    }

    public function getAsset(string $assetId): ?array
    {
        throw new RuntimeException('The local filesystem project adapter does not manage assets yet.');
    }
}

function app_storage_mode(?string $mode): string
{
    $candidate = trim(strtolower((string) ($mode ?? '')));
    if ($candidate === 'nextcloud') {
        return 'nextcloud';
    }
    return 'local-filesystem';
}

function app_storage_adapter(?string $mode = null, ?string $storagePath = null): SiteKitStorageAdapter
{
    static $adapters = [];

    $resolvedMode = app_storage_mode($mode);
    $resolvedPath = trim((string) ($storagePath ?? ''));
    $cacheKey = $resolvedMode . '|' . $resolvedPath;
    if (isset($adapters[$cacheKey]) && $adapters[$cacheKey] instanceof SiteKitStorageAdapter) {
        return $adapters[$cacheKey];
    }

    if ($resolvedMode === 'nextcloud') {
        $rootDir = app_nextcloud_storage_root_dir($resolvedPath !== '' ? $resolvedPath : null);
        $pagesDir = app_nextcloud_pages_dir($rootDir);
        $adapters[$cacheKey] = new SiteKitDirectoryStorageAdapter(
            'nextcloud',
            app_tr('Nextcloud'),
            'remote',
            $rootDir,
            $pagesDir,
            [
                'requestedStoragePath' => $resolvedPath,
                'nextcloudRoot' => $rootDir,
                'nextcloudSuggestedSavePath' => $rootDir,
                'nextcloudConfigured' => $rootDir !== '' || app_sitekit_nextcloud_url() !== '',
            ]
        );
        return $adapters[$cacheKey];
    }

    $rootDir = $resolvedPath !== '' ? rtrim($resolvedPath, '/') : app_storage_root_dir();
    $pagesDir = app_pages_dir($rootDir);
    $adapters[$cacheKey] = new SiteKitDirectoryStorageAdapter(
        'local-filesystem',
        app_tr('Local Filesystem'),
        'local',
        $rootDir,
        $pagesDir,
        [
            'requestedStoragePath' => $resolvedPath,
        ]
    );
    return $adapters[$cacheKey];
}

function app_storage_adapter_summary(?string $mode = null, ?string $storagePath = null): array
{
    $adapter = app_storage_adapter($mode, $storagePath);
    return [
        'id' => $adapter->id(),
        'label' => $adapter->label(),
        'capabilities' => $adapter->capabilities(),
    ];
}

function app_save_page(array $pageJson, ?string $projectId = null, ?string $mode = null, ?string $storagePath = null): array
{
    return app_storage_adapter($mode, $storagePath)->saveProject($pageJson, $projectId);
}

function app_load_page(string $slug, ?string $mode = null, ?string $storagePath = null): ?array
{
    try {
        return app_storage_adapter($mode, $storagePath)->loadProject($slug);
    } catch (RuntimeException $_e) {
        return null;
    }
}

function app_list_pages(?string $mode = null, ?string $storagePath = null): array
{
    return app_storage_adapter($mode, $storagePath)->listProjects();
}

function app_delete_page(string $slug, ?string $mode = null, ?string $storagePath = null): bool
{
    return app_storage_adapter($mode, $storagePath)->deleteProject($slug);
}

function app_storage_sync_status(?string $projectId = null, ?string $mode = null, ?string $storagePath = null): array
{
    return app_storage_adapter($mode, $storagePath)->syncStatus($projectId);
}
