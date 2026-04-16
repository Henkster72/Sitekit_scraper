# SiteKit `.skit` Spec v1

## Scope

SiteKit spec `v1` defines the allowed structure for:

- a canonical SiteKit project payload
- a `.skit` transport envelope that contains that payload
- a theme-only JSON import payload

`Spec v1` is the validation baseline for import before normalize/render.

## Canonical Rules

### Canonical source payload

The canonical SiteKit project payload is a JSON object with this shape:

```json
{
  "version": "1.0 or 2.0",
  "theme": { "...normalized theme object..." },
  "palette": { "...optional top-level palette (recommended)..." },
  "typography": { "...optional top-level typography (recommended)..." },
  "gradient": { "...optional top-level gradient..." },
  "elements": { "...optional top-level element tokens..." },
  "images": [{ "...optional theme image..." }],
  "metadata": { "...optional theme metadata..." },
  "site": {
    "name": "Site name",
    "typeId": "optional-site-type",
    "activePageId": "home",
    "baseUrl": "optional base URL for relative assets/links",
    "canvas": { "...optional shared canvas..." },
    "assets": [{ "...optional embedded asset..." }],
    "sharedBlocks": {
      "header": { "...optional shared header block..." },
      "footer": { "...optional shared footer block..." }
    },
    "pages": [{ "...site page..." }]
  },
  "page": {
    "...active page mirror..."
  }
}
```

### Theme payload placement

SiteKit theme normalization expects `palette` and `typography` at the **top level**
of the payload (sibling to `theme`, `site`, `page`). If you only nest them inside
`theme`, the importer will treat the theme as incomplete and fall back to defaults.

Recommended:

- Always include `palette` and `typography` at the top level.
- Optionally include `gradient`, `elements`, `images`, and `metadata` at the top level.

### `.skit` transport envelope

A `.skit` file is a JSON envelope:

```json
{
  "format": "sitekit.skit",
  "version": "1.0",
  "encoding": "gzip+base64",
  "payload": "base64-gzip-json"
}
```

Important:

- `.skit` is not a zip archive in the current implementation.
- The decoded payload inside the envelope must satisfy spec `v1`.

### Theme-only import

Theme-only import remains allowed as a convenience shape.

It is valid for:

- importing a complete theme payload
- importing a theme-catalog style payload that SiteKit can normalize

Theme-only input is import-only convenience, not the canonical project source-of-truth format.

## Canonical Project Requirements

### Required project content

A project payload must contain at least one of:

- `page` object
- `site.pages` array with at least one page

### Page object

Each page may contain:

- `id`
- `title`
- `menuLabel`
- `slug`
- `locale`
- `showInHeaderNav`
- `showInFooterNav`
- `sharedContact`
- `presetId`
- `canvas`
- `blocks`

### Base URL

`site.baseUrl` is an optional base URL used to resolve relative URLs found in
block data or shared blocks.

Rules:

- If present, it must be an absolute URL (e.g. `https://example.com/`), a
  root-relative base (e.g. `/` or `/assets/`), or a relative base path
  (e.g. `assets/`) when the assets live on the same server as the SiteKit project.
- When `baseUrl` is set, block data may use relative URLs to avoid repetition.
- Importers should resolve relative URLs using `baseUrl` at render time.

### Block object

Each block may contain:

- `uid`
- `type`
- `variant`
- `hidden`
- `widthPercent`
- `backgroundColor`
- `backgroundOpacity`
- `foregroundOpacity`
- `fontScale`
- `verticalPaddingScale`
- `cardColor`
- `cardBorderColor`
- `cardBorderStyle`
- `cardBorderWidth`
- `cardBackgroundOpacity`
- `animation`
- `data`

Rules:

- `type` must map to a registered SiteKit block type.
- `variant` must be allowed for that block type.
- `data` must stay JSON-safe.

### Site assets

Embedded site assets live in `site.assets`.

Each asset contains:

- `id`
- `filename`
- `mime`
- `dataUrl`
- `size`
- `width`
- `height`
- `createdAt`

Rules:

- `dataUrl` must be an image `data:` URL with base64 payload.
- assets are optional
- assets are for portability, not mandatory runtime storage

## Validation Guardrails

Spec `v1` validation currently enforces:

- payload size limits
- `.skit` envelope size limits
- page count limits
- block count limits
- asset count limits
- nested JSON depth limits
- string size limits
- known block type + variant validation
- image `dataUrl` validation for embedded assets

## Source Of Truth

SiteKit spec `v1` defines the source of truth as:

- canonical project source of truth: exported SiteKit project payload / `.skit`
- workspace cache: browser storage
- UI convenience state: browser storage

Implication:

- `.skit` is the durable, portable project artifact
- browser storage must be rebuildable or disposable

## Non-goals For Spec v1

Spec `v1` does not define:

- team sync
- remoteStorage.io sync
- WebDAV sync
- custom template logic
- custom JavaScript execution model
- agency cloud or multi-tenant behavior

## Current Enforcement Points

Spec `v1` validation now runs before normalize/render on:

- builder POST boot payloads
- viewer uploads/pastes
- API render/export/save/download flows
- bridge normalization/export/render/save flows
- browser file import via `api.php?action=validate_import`

## Machine-readable Schema

See:

- [skit-spec-v1.schema.json](/home/henk/Documents/php_websites/SiteKit/docs/specs/skit-spec-v1.schema.json)
