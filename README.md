# Sitekit Scraper

Showit-first scraping and mapping pipeline for turning visually rich websites into structured Sitekit draft output.

## Why This Exists

This project exists to bridge the gap between a live website and the internal Sitekit block format.

The immediate goal is:

- capture a rendered site as it actually appears in the browser
- identify its sections and visual patterns
- extract useful text, imagery, and structure
- match those sections to Sitekit block variants
- export draft Sitekit JSON that can be refined inside the target builder

The first target platform is **Showit** because its templates are visually rich, relatively pattern-based, and less overloaded with client-side JavaScript than many modern page builders.

This is not meant to be a vague "AI website copier". It is a pipeline with explicit stages, inspectable outputs, and intermediate artifacts you can review.

## Relationship To Sitekit

The **Sitekit builder** is the proprietary product of **AllroundWebsite**.

This repository is an importer and transformation layer that produces **Sitekit scrape outputs** for use with that builder. It is not the builder itself, and it does not replace the editing, layout control, or publishing workflow that happens inside Sitekit.

In practice, this repo is meant to do the front-end ingestion work:

- scrape
- segment
- classify
- map
- export

Then the output can be consumed online by the Sitekit builder.

## Why These Languages Are Used

### Python

Python is used for the scraping and analysis pipeline because it is the most practical tool here for browser automation, HTML parsing, image processing, and data transformation.

It fits this project well because:

- **Playwright** works cleanly from Python for rendered-page capture
- **Beautiful Soup** and **lxml** are strong for parsing messy real-world HTML
- **Pillow** is practical for section crop analysis and image statistics
- Python is good for small pipeline stages that read JSON in and write JSON out

That makes Python the right language for:

- page capture
- section segmentation
- content extraction
- heuristic classification
- visual-stat generation
- variant scoring
- draft export

### PHP

PHP exists here because the target ecosystem around Sitekit is PHP-based.

The PHP scripts in this repo are for:

- bridging to Sitekit-style payload generation
- working with existing Sitekit-oriented structures
- keeping compatibility with the surrounding builder/runtime environment

Python is doing the scraping work. PHP is here to align the output with the system that will consume it.

### JSON

JSON is the contract format across the pipeline.

It is used for:

- scraped section artifacts
- block variant guides
- mapping specs
- Sitekit draft output
- reference libraries

That is intentional. Every stage should be inspectable and debuggable without hidden state.

### Markdown

Markdown is used for human-readable specs, planning, and operating instructions:

- project notes
- mapping rules
- block variant guidance
- agent instructions
- TODO tracking

## Core Pipeline

Current pipeline shape:

1. **Capture**
   - Render the page with Playwright
   - Save desktop/mobile screenshots
   - Save final rendered HTML

2. **Segment**
   - Find Showit sections/blocks
   - Get bounding boxes
   - Crop section screenshots

3. **Extract**
   - Pull headings, paragraphs, links, buttons, images
   - Compute useful stats
   - Compute visual metrics from the cropped screenshots

4. **Classify**
   - Infer section role such as hero, services, gallery, testimonial, cards, CTA

5. **Match Variant**
   - Compare section evidence against the Sitekit block variant guide
   - Produce ranked matches with generated confidence scores

6. **Export**
   - Build draft Sitekit JSON

## Project Structure

```text
Sitekit_scraper/
├── bin/                    # CLI entry points
├── data/                   # reference data and scrape artifacts
├── docs/                   # specs and project notes
├── inc/                    # PHP integration/runtime helpers
├── reference_library/      # Sitekit-oriented reference data
├── src/sitekit_scraper/    # Python pipeline modules
├── AGENTS.md               # instructions for Codex/agent workflows
├── TODO.md                 # active work list
└── fitzgerald.json         # manual/reference Fitzgerald mapping
```

## Main Modules

- `src/sitekit_scraper/capture.py`
- `src/sitekit_scraper/segment.py`
- `src/sitekit_scraper/extract.py`
- `src/sitekit_scraper/classify.py`
- `src/sitekit_scraper/match_variant.py`
- `src/sitekit_scraper/export_sitekit.py`

CLI wrappers live in `bin/`.

## Current Focus

The current implementation is focused on:

- Showit template ingestion
- block/section detection
- evidence-based variant matching
- alignment with Sitekit block specs
- Fitzgerald as a working reference case

## What This Project Is Not

- not a one-click production copier
- not a replacement for the Sitekit builder
- not a finished ML training stack
- not a guarantee of pixel-perfect parity

The near-term goal is reliable structured import, not fake certainty.

## Install

Base environment:

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
playwright install chromium
```

Optional heavier packages:

```bash
pip install -r requirements-optional.txt
```

## Example Run

```bash
source .venv/bin/activate
python bin/showit_capture.py --url https://fitzgerald.tonicsiteshop.com/
python bin/showit_segment.py --url https://fitzgerald.tonicsiteshop.com/
python bin/showit_extract.py --url https://fitzgerald.tonicsiteshop.com/
python bin/showit_classify.py --url https://fitzgerald.tonicsiteshop.com/
python bin/showit_match_variant.py --url https://fitzgerald.tonicsiteshop.com/
python bin/showit_export_sitekit.py --url https://fitzgerald.tonicsiteshop.com/
```

## Key Documents

- `docs/specs/skit-spec-v1.md`
- `docs/specs/showit-to-sitekit-mapper-v1.md`
- `docs/specs/sitekit-block-variant-guide-v1.md`
- `AGENTS.md`
- `TODO.md`

## Status

The repo currently has working foundations for:

- rendered Showit capture
- section screenshot cropping
- DOM and image extraction
- heuristic classification
- evidence-based Sitekit variant confidence scoring
- draft Sitekit JSON export

The next major improvements are around:

- section merging and suppression
- richer reference matching
- stronger Sitekit payload fidelity
- more than one source platform
