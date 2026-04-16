# SiteKit Scraper Agent Guide

## Mission

Build a Showit-first scraper pipeline that turns a live site into:

1. a mapper JSON document that follows `docs/specs/showit-to-sitekit-mapper-v1.*`
2. a canonical SiteKit payload that follows `docs/specs/skit-spec-v1.*`
3. optionally a `.skit` export through the existing PHP bridge

Do not build one oversized "AI app". Build a staged pipeline with inspectable artifacts.

## Source Of Truth

Read these before changing code:

- `docs/specs/showit-to-sitekit-mapper-v1.md`
- `docs/specs/showit-to-sitekit-mapper-v1.schema.json`
- `docs/specs/skit-spec-v1.md`
- `docs/specs/skit-spec-v1.schema.json`
- `prompt-website-to-sitekit-json.md`
- `bin/showit_to_sitekit_payload.php`
- `bin/sitekit_bridge.php`

If implementation and docs disagree, move implementation toward the docs unless the docs are clearly stale and you update them in the same change.

## Core Rules

- Showit is the first target, not the last target.
- Prefer the live URL for copy, links, and public asset URLs.
- Use DivMagic HTML only as a fallback or structural cross-check.
- Preserve section order and page rhythm.
- Map structure into SiteKit blocks; do not try to reproduce Showit absolute positioning literally.
- Do not invent SiteKit block types or variants.
- Keep `palette` and `typography` at the top level of SiteKit payloads.
- Prefer original `static.showit.co` image URLs when they are public and stable.
- Treat model output as a hint. DOM facts, geometry, and schema validation win.
- Do not add training or fine-tuning work yet. `transformers`, `torch`, and `peft` are later-stage options, not the starting point.

## Pipeline

Build the system in this order:

1. Capture
   - Use Playwright to load the page.
   - Save rendered HTML.
   - Save desktop and mobile full-page screenshots.
   - Record source URL, final URL, viewport, and timestamp.

2. Segment
   - Detect likely sections from real rendered DOM plus bounding boxes.
   - Start with `header`, `main`, `section`, `footer`, and large `div` containers.
   - Save per-section bounding boxes and cropped screenshots.

3. Extract
   - Pull headings, paragraphs, CTA labels and URLs, image URLs, repeated-card counts, form signals, and layout cues.
   - Normalize the extracted data into a stable section record.

4. Classify
   - First pass is rules plus similarity, not training.
   - Assign Showit pattern family.
   - Suggest the closest SiteKit block type and variant.

5. Export mapper
   - Emit mapper JSON that validates against `showit-to-sitekit-mapper-v1.schema.json`.

6. Export SiteKit
   - Convert mapper output into canonical SiteKit payloads.
   - Use the existing PHP SiteKit helpers instead of recreating SiteKit rules in Python.

7. Validate
   - Run payloads through `bin/sitekit_bridge.php`.
   - Only consider the pipeline done when mapper and SiteKit output are both valid.

## Language Split

Use the language that matches the job:

- Python for browser capture, HTML parsing, section segmentation, image crops, embeddings, and heuristic classification.
- PHP for SiteKit-specific block creation, normalization, export, and validation, because this repo already has those helpers.

Do not rewrite the SiteKit bridge in Python just to keep everything in one language.

## Suggested Artifact Layout

Keep generated scraper artifacts grouped by source site, for example:

`data/scrapes/<site_slug>/capture/`
`data/scrapes/<site_slug>/html/`
`data/scrapes/<site_slug>/screenshots/full/`
`data/scrapes/<site_slug>/screenshots/sections/`
`data/scrapes/<site_slug>/mapper/`
`data/scrapes/<site_slug>/sitekit/`
`data/scrapes/<site_slug>/logs/`

Keep hand-authored specs, prompts, and code out of these folders.

## Two-Agent Split

### Primary agent: orchestrator and SiteKit owner

This is the instance that owns:

- schema and spec compliance
- pipeline shape and module boundaries
- PHP SiteKit export path
- validation commands and regression fixtures
- final mapper and payload review
- docs and handoff notes

Primary agent should touch:

- `docs/specs/*` when contracts need updates
- `bin/showit_to_sitekit_payload.php`
- `bin/sitekit_bridge.php`
- new glue code that converts extracted section records into SiteKit payloads
- fixtures and docs

Primary agent should not block on pixel-perfect extraction details before defining the contract between extraction and export.

### Secondary agent: capture and extraction owner

The second instance should own:

- Playwright capture
- DOM and bbox-based section segmentation
- per-section screenshot crops
- section-level extraction of text, links, and media
- rule-based pattern scoring
- similarity lookup against known SiteKit references when available

Secondary agent should produce stable intermediate JSON artifacts that the primary agent can consume without reading raw HTML again.

Secondary agent should not:

- invent final SiteKit payload shapes
- bypass the mapper schema
- modify PHP render logic unless the primary agent explicitly asks for it

## Handoff Contract Between Agents

The secondary agent hands off section records with enough information for mapping, for example:

- page id, title, slug, and source URL
- section id and order
- bbox and screenshot path
- detected role and pattern family
- extracted copy fields
- button labels and URLs
- image URLs
- simple style cues like tone, alignment, and density
- top SiteKit block guesses with confidence

The primary agent turns that into:

- mapper JSON
- canonical SiteKit payload
- optional `.skit`

## Definition Of Done

The Showit pipeline is only done when all of these are true:

- one command can capture a Showit page into saved artifacts
- one command can emit mapper JSON for that page
- one command can emit canonical SiteKit JSON for that page
- the mapper matches the mapper schema
- the SiteKit payload passes the existing bridge normalization/export path
- at least one real Showit example is checked in as a regression fixture

## First Regression Target

Use the existing Fitzgerald work as the first comparison target:

- `bin/build_fitzgerald_payload.php`

The pipeline output does not need to match that file byte-for-byte, but it should land in the same neighborhood structurally and visually.

## Working Style

- Make small pipeline pieces that can be run independently.
- Save intermediate outputs; do not hide all logic inside one final export command.
- Prefer deterministic rules first, then add embeddings where they clearly help.
- Add sample commands in comments or docs whenever you introduce a new script.
- Keep future multi-platform expansion in mind, but do not dilute the current Showit-first milestone.
