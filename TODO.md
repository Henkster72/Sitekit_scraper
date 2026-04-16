# TODO

## P0 Foundations

- [x] Primary: add Python scraper dependencies for capture and extraction without bringing in training-time tooling yet.
- [x] Primary: define the on-disk artifact layout under `data/scrapes/<site_slug>/...`.
- [x] Primary: document one end-to-end sample command flow from capture to mapper to SiteKit export.
- [x] Secondary: capture one real Showit target and save baseline HTML plus desktop and mobile screenshots.

## P1 Capture And Segmentation

- [x] Secondary: add a Playwright capture script that saves rendered HTML, final URL, and full-page screenshots.
- [x] Secondary: add basic popup or cookie-dismiss hooks where they materially improve captures.
- [x] Secondary: segment pages into candidate sections using rendered DOM and element bounding boxes.
- [x] Secondary: save per-section crops plus section metadata JSON.

## P2 Extraction And Mapping

- [x] Secondary: extract headings, paragraphs, buttons, links, image URLs, and repeated-item counts per section.
- [x] Secondary: add first-pass rule-based role classification for hero, content, gallery, CTA, contact, testimonial, and footer patterns.
- [ ] Secondary: add similarity-based variant suggestion only after the deterministic extraction output is stable.
- [x] Primary: define the intermediate JSON contract the exporter expects from the extraction stage.
- [ ] Primary: emit mapper JSON that validates against `docs/specs/showit-to-sitekit-mapper-v1.schema.json`.

## P3 SiteKit Export

- [ ] Primary: convert mapper sections into canonical SiteKit blocks using existing PHP helpers.
- [ ] Primary: preserve shared header and footer in `site.sharedBlocks` where the source site uses global chrome.
- [ ] Primary: keep `palette` and `typography` at the top level of exported payloads.
- [ ] Primary: support both decoded JSON export and optional `.skit` export.
- [ ] Primary: restore `inc/utils.php` or equivalent missing bridge helpers so `bin/sitekit_bridge.php` can run in this repo snapshot.

## P4 Validation And Fixtures

- [ ] Primary: validate generated payloads through `php bin/sitekit_bridge.php normalize_payload`.
- [x] Primary: add at least one saved mapper fixture and one saved SiteKit fixture for a Showit page.
- [ ] Primary: use `bin/build_fitzgerald_payload.php` as the first regression comparison target.
- [ ] Primary: add smoke checks for touched PHP and Python files.

## P5 After The First Showit Success

- [ ] Joint: review where rules fail and decide whether embeddings actually improve block matching.
- [ ] Joint: add a small reference library of known SiteKit block screenshots and sample data.
- [ ] Joint: expand from single-page homepages to multi-page Showit sites.
- [ ] Joint: only consider `transformers`, `torch`, or `peft` after the rule-based pipeline is producing useful exports consistently.
