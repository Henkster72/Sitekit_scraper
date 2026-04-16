from __future__ import annotations

import argparse
from pathlib import Path
from typing import Any

from PIL import Image
from playwright.sync_api import sync_playwright

from .capture import dismiss_basic_overlays
from .utils import ensure_scrape_paths, load_capture_metadata, slugify, write_json


DISCOVERY_SCRIPT = """
({ selectors, minHeight, minWidth }) => {
  const seen = new Set();
  const candidates = [];
  const hasShowitBlocks = document.querySelectorAll("#si-sp > div[data-bid]").length > 0;

  const cssPath = (element) => {
    const parts = [];
    let node = element;
    while (node && node.nodeType === 1 && parts.length < 8) {
      let part = node.tagName.toLowerCase();
      if (node.id) {
        part += `#${node.id}`;
        parts.unshift(part);
        break;
      }
      const classes = Array.from(node.classList || []).slice(0, 2);
      if (classes.length) {
        part += "." + classes.join(".");
      }
      const siblings = node.parentElement ? Array.from(node.parentElement.children).filter((child) => child.tagName === node.tagName) : [];
      if (siblings.length > 1) {
        part += `:nth-of-type(${siblings.indexOf(node) + 1})`;
      }
      parts.unshift(part);
      node = node.parentElement;
    }
    return parts.join(" > ");
  };

  const gather = (element, selector) => {
    if (!element || seen.has(element)) {
      return;
    }
    seen.add(element);
    const rect = element.getBoundingClientRect();
    const dataBid = element.getAttribute("data-bid") || "";
    if (hasShowitBlocks && !dataBid && !["header", "footer"].includes(element.tagName.toLowerCase())) {
      return;
    }
    const compactShowitChrome = /menu|header|footer/i.test(dataBid);
    if (rect.width < minWidth || (rect.height < minHeight && !compactShowitChrome)) {
      return;
    }
    const style = window.getComputedStyle(element);
    if (style.display === "none" || style.visibility === "hidden" || Number(style.opacity || "1") === 0) {
      return;
    }
    const top = rect.top + window.scrollY;
    const left = rect.left + window.scrollX;
    const outerHtml = (element.outerHTML || "").slice(0, 30000);
    const text = (element.innerText || "").replace(/\\s+/g, " ").trim();
    const item = {
      selector,
      domPath: cssPath(element),
      tag: element.tagName.toLowerCase(),
      blockSlug: dataBid,
      id: element.id || "",
      classes: Array.from(element.classList || []),
      bbox: {
        x: Math.max(0, Math.round(left)),
        y: Math.max(0, Math.round(top)),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
      },
      textSample: text.slice(0, 500),
      textLength: text.length,
      headingCount: element.querySelectorAll("h1,h2,h3,h4,h5,h6").length,
      paragraphCount: element.querySelectorAll("p").length,
      linkCount: element.querySelectorAll("a[href]").length,
      buttonCount: element.querySelectorAll("button,a[role='button'],input[type='submit']").length,
      imageCount: element.querySelectorAll("img").length,
      formFieldCount: element.querySelectorAll("input,select,textarea").length,
      backgroundColor: style.backgroundColor || "",
      backgroundImage: style.backgroundImage || "",
      outerHtml,
    };
    candidates.push(item);
  };

  for (const selector of selectors) {
    for (const element of document.querySelectorAll(selector)) {
      gather(element, selector);
    }
  }

  return {
    viewport: {
      width: window.innerWidth,
      height: window.innerHeight,
      pageHeight: Math.max(
        document.body.scrollHeight,
        document.documentElement.scrollHeight,
        document.body.offsetHeight,
        document.documentElement.offsetHeight
      ),
    },
    candidates,
  };
}
"""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Segment a captured Showit page into candidate sections.")
    parser.add_argument("--url", required=True, help="Public URL used for capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    parser.add_argument("--min-height", type=int, default=140)
    parser.add_argument("--min-width", type=int, default=320)
    parser.add_argument("--keep-overlays", action="store_true", help="Skip the basic overlay dismissal step.")
    return parser.parse_args()


def selector_priority(item: dict[str, Any]) -> tuple[int, int]:
    tag = item.get("tag", "")
    selector = item.get("selector", "")
    semantic_score = 0
    if item.get("blockSlug"):
        semantic_score += 5
    if tag in {"header", "footer", "section"}:
        semantic_score += 4
    if selector.startswith("main > section"):
        semantic_score += 3
    elif selector.startswith("body > section"):
        semantic_score += 2
    elif selector.startswith("main > div"):
        semantic_score += 1
    return (semantic_score, item["bbox"]["height"] * item["bbox"]["width"])


def overlaps(existing: dict[str, Any], candidate: dict[str, Any]) -> bool:
    a = existing["bbox"]
    b = candidate["bbox"]
    top_delta = abs(a["y"] - b["y"])
    height_delta = abs(a["height"] - b["height"])
    return top_delta <= 24 and height_delta <= 48


def dedupe_candidates(candidates: list[dict[str, Any]]) -> list[dict[str, Any]]:
    ordered = sorted(candidates, key=lambda item: (item["bbox"]["y"], -selector_priority(item)[0], -selector_priority(item)[1]))
    output: list[dict[str, Any]] = []
    for candidate in ordered:
        if candidate["tag"] == "main" and any(item["tag"] == "section" for item in ordered):
            continue
        match_index = next((index for index, item in enumerate(output) if overlaps(item, candidate)), None)
        if match_index is None:
            output.append(candidate)
            continue
        if selector_priority(candidate) > selector_priority(output[match_index]):
            output[match_index] = candidate
    return sorted(output, key=lambda item: item["bbox"]["y"])


def crop_sections(
    screenshot_path: Path,
    candidates: list[dict[str, Any]],
    output_dir: Path,
    relative_root: Path,
) -> None:
    for existing in output_dir.glob("*.png"):
        existing.unlink()
    image = Image.open(screenshot_path)
    width, height = image.size
    for index, candidate in enumerate(candidates, start=1):
        bbox = candidate["bbox"]
        left = max(0, int(bbox["x"]))
        top = max(0, int(bbox["y"]))
        right = min(width, left + max(1, int(bbox["width"])))
        bottom = min(height, top + max(1, int(bbox["height"])))
        crop = image.crop((left, top, right, bottom))
        slug_basis = candidate.get("blockSlug") or candidate.get("tag") or f"section-{index:02d}"
        filename = f"{index:02d}-{slugify(slug_basis, default='section')}.png"
        output_path = output_dir / filename
        crop.save(output_path)
        candidate["sectionIndex"] = index
        candidate["sectionId"] = slugify(candidate.get("blockSlug") or f"section-{index:02d}", default=f"section-{index:02d}")
        candidate["cropPath"] = str(output_path.relative_to(relative_root))


def main() -> int:
    args = parse_args()
    paths, capture_metadata = load_capture_metadata(args.url, site_slug=args.site_slug, page_slug=args.page_slug)
    ensure_scrape_paths(paths)
    desktop_viewport = capture_metadata["desktop"]["viewport"]

    selectors = [
        "#si-sp > div[data-bid]",
        "#si-sp > div.sb",
        "header",
        "body > section",
        "main > section",
        "main section",
        "footer",
        "body > div",
        "main > div",
    ]

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        page = browser.new_page(
            viewport={
                "width": desktop_viewport["width"],
                "height": desktop_viewport["height"],
            },
            device_scale_factor=1,
        )
        page.goto(capture_metadata["desktop"]["finalUrl"], wait_until="networkidle", timeout=60_000)
        page.wait_for_timeout(1200)
        if not args.keep_overlays:
            dismiss_basic_overlays(page)
            page.wait_for_timeout(300)
        discovery = page.evaluate(
            DISCOVERY_SCRIPT,
            {
                "selectors": selectors,
                "minHeight": args.min_height,
                "minWidth": args.min_width,
            },
        )
        browser.close()

    candidates = dedupe_candidates(discovery["candidates"])
    crop_sections(
        paths.screenshots_full_dir / "desktop.png",
        candidates,
        paths.screenshots_sections_dir,
        paths.page_dir.parent.parent.parent,
    )

    payload = {
        "version": "0.1",
        "sourceUrl": capture_metadata["sourceUrl"],
        "finalUrl": capture_metadata["desktop"]["finalUrl"],
        "siteSlug": paths.site_slug,
        "pageSlug": paths.page_slug,
        "viewport": discovery["viewport"],
        "sections": candidates,
    }
    output_path = paths.sections_dir / "sections.segmented.json"
    write_json(output_path, payload)
    print(output_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
