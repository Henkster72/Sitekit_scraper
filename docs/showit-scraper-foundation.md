# Showit Scraper Foundation

## Install

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
playwright install chromium
```

Optional later:

```bash
pip install -r requirements-optional.txt
```

The optional file contains the heavier similarity and model runtime stack. Keep it out of the base environment until the matcher actually starts using embeddings or model-backed classification.

## Artifact Layout

Each scraped page writes to:

`data/scrapes/<site_slug>/<page_slug>/`

Within that page folder the scaffold uses:

- `capture/` for capture metadata
- `html/` for rendered HTML
- `screenshots/full/` for desktop and mobile full-page screenshots
- `screenshots/sections/` for cropped section screenshots
- `sections/` for segmentation and extraction JSON
- `mapper/` for mapper outputs
- `sitekit/` for draft SiteKit outputs
- `logs/` for run logs

## Command Flow

The current foundation keeps the steps separate on purpose.

### 1. Capture a page

```bash
python bin/showit_capture.py --url https://fitzgerald.tonicsiteshop.com/
```

### 2. Segment the page into sections

```bash
python bin/showit_segment.py --url https://fitzgerald.tonicsiteshop.com/
```

### 3. Extract section content

```bash
python bin/showit_extract.py --url https://fitzgerald.tonicsiteshop.com/
```

### 4. Classify each section

```bash
python bin/showit_classify.py --url https://fitzgerald.tonicsiteshop.com/
```

### 5. Match candidate SiteKit variants

```bash
python bin/showit_match_variant.py --url https://fitzgerald.tonicsiteshop.com/
```

### 6. Export mapper output and a draft SiteKit shell

```bash
python bin/showit_export_sitekit.py --url https://fitzgerald.tonicsiteshop.com/
```

## Notes

- The current Python export stage writes a valid mapper-shaped JSON document and a conservative SiteKit draft shell for inspection.
- The existing PHP bridge remains the target for real normalization and final export.
- `reference_library/variants.json` and `reference_library/section_index.json` are placeholder catalogs for later screenshot and embedding-based matching.
- The current scaffold does not import `sentence-transformers` yet, so the base install stays smaller while capture, segmentation, extraction, and rule-based matching settle down.
- A live baseline run now exists at `data/scrapes/fitzgerald-tonicsiteshop-com/home/`.
- `bin/sitekit_bridge.php` is currently not runnable from this repo snapshot because `inc/utils.php` is missing. Treat bridge validation as blocked until that file or its equivalent helpers are restored.
