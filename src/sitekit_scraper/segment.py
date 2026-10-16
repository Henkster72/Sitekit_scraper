from __future__ import annotations

import argparse
import math
from pathlib import Path
from typing import Any
from urllib.request import Request, urlopen

from PIL import Image
from playwright.sync_api import sync_playwright

from .capture import dismiss_basic_overlays, reveal_page_for_analysis, wait_for_render_ready
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

  const unique = (values) => Array.from(new Set(values.filter(Boolean))).slice(0, 32);
  const styleEvidence = (element) => {
    const root = window.getComputedStyle(element);
    const descendants = [element, ...element.querySelectorAll("h1,h2,h3,h4,h5,h6,p,a,button,img")].slice(0, 80);
    const styles = descendants.map((node) => window.getComputedStyle(node));
    const headings = Array.from(element.querySelectorAll("h1,h2,h3,h4,h5,h6")).slice(0, 24).map((node) => window.getComputedStyle(node));
    const body = Array.from(element.querySelectorAll("p,a,button")).slice(0, 48).map((node) => window.getComputedStyle(node));
    return {
      display: root.display,
      position: root.position,
      visibility: root.visibility,
      opacity: Number(root.opacity || "1"),
      color: root.color || "",
      backgroundColor: root.backgroundColor || "",
      backgroundImage: root.backgroundImage || "",
      fontFamilies: unique(styles.map((style) => style.fontFamily)),
      fontSizes: unique(styles.map((style) => style.fontSize)),
      headingFontFamilies: unique(headings.map((style) => style.fontFamily)),
      headingFontSizes: unique(headings.map((style) => style.fontSize)),
      bodyFontFamilies: unique(body.map((style) => style.fontFamily)),
      bodyFontSizes: unique(body.map((style) => style.fontSize)),
      lineHeights: unique(styles.map((style) => style.lineHeight)),
      fontWeights: unique(styles.map((style) => style.fontWeight)),
      letterSpacings: unique(styles.map((style) => style.letterSpacing)),
      textAlignments: unique(styles.map((style) => style.textAlign)),
      borderRadii: unique(styles.flatMap((style) => [style.borderTopLeftRadius, style.borderTopRightRadius, style.borderBottomRightRadius, style.borderBottomLeftRadius])),
      borderWidths: unique(styles.flatMap((style) => [style.borderTopWidth, style.borderRightWidth, style.borderBottomWidth, style.borderLeftWidth])),
      borderStyles: unique(styles.flatMap((style) => [style.borderTopStyle, style.borderRightStyle, style.borderBottomStyle, style.borderLeftStyle])),
      padding: [root.paddingTop, root.paddingRight, root.paddingBottom, root.paddingLeft],
      gap: root.gap || "",
      flexDirection: root.flexDirection || "",
      gridTemplateColumns: root.gridTemplateColumns || "",
      alignItems: root.alignItems || "",
      justifyContent: root.justifyContent || "",
    };
  };

  const isStageBoundary = (node) => {
    if (!node || node === document.body) return false;
    const tag = node.tagName.toLowerCase();
    return ["section", "header", "footer"].includes(tag) || Boolean(node.getAttribute("data-bid"));
  };

  const ownedElements = (element, selector) => Array.from(element.querySelectorAll(selector)).filter((node) => {
    let parent = node.parentElement;
    while (parent && parent !== element) {
      if (isStageBoundary(parent)) return false;
      parent = parent.parentElement;
    }
    return true;
  });

  const ownedTextLength = (element) => {
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    let length = 0;
    let node;
    while ((node = walker.nextNode())) {
      let parent = node.parentElement;
      let owned = true;
      while (parent && parent !== element) {
        const style = window.getComputedStyle(parent);
        if (isStageBoundary(parent) || parent.tagName.toLowerCase() === "script" ||
            parent.tagName.toLowerCase() === "style" || style.display === "none" ||
            style.visibility === "hidden" || Number(style.opacity || "1") === 0) {
          owned = false;
          break;
        }
        parent = parent.parentElement;
      }
      if (owned) length += (node.textContent || "").replace(/\\s+/g, " ").trim().length;
    }
    return length;
  };

  const overlayEvidence = (element, style, rect) => {
    const identity = `${element.id || ""} ${element.className || ""}`.toLowerCase();
    const semantic = element.getAttribute("role") === "dialog" ||
      element.getAttribute("aria-modal") === "true" ||
      /cookie|consent|modal|popup|overlay|dialog/.test(identity);
    const fixed = style.position === "fixed";
    const zIndex = Number.parseInt(style.zIndex || "", 10);
    const coversViewport = rect.width >= window.innerWidth * 0.7 && rect.height >= window.innerHeight * 0.35;
    if (fixed && (semantic || (coversViewport && (Number.isNaN(zIndex) || zIndex >= 0)))) {
      return {
        sectionKind: "overlay",
        overlayReason: semantic ? "fixed-overlay-semantics" : "fixed-viewport-cover",
      };
    }
    return { sectionKind: "page", overlayReason: "" };
  };

  const documentStyles = () => {
    const root = window.getComputedStyle(document.documentElement);
    const cssVariables = {};
    for (const name of Array.from(document.styleSheets).flatMap((sheet) => {
      try {
        return Array.from(sheet.cssRules || []).flatMap((rule) =>
          rule.style ? Array.from(rule.style).filter((property) => property.startsWith("--")) : []
        );
      } catch (error) {
        return [];
      }
    })) {
      const value = root.getPropertyValue(name).trim();
      if (value) cssVariables[name] = value;
    }
    return {
      cssVariables,
      root: {
        color: root.color || "",
        backgroundColor: root.backgroundColor || "",
        fontFamily: root.fontFamily || "",
        fontSize: root.fontSize || "",
        lineHeight: root.lineHeight || "",
      },
    };
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
    const semanticChrome = ["header", "footer"].includes(element.tagName.toLowerCase());
    if (rect.width < minWidth || (rect.height < minHeight && !compactShowitChrome && !semanticChrome)) {
      return;
    }
    const style = window.getComputedStyle(element);
    if (style.display === "none" || style.visibility === "hidden") {
      return;
    }
    const top = rect.top + window.scrollY;
    const left = rect.left + window.scrollX;
    const outerHtml = (element.outerHTML || "").slice(0, 30000);
    const text = (element.innerText || "").replace(/\\s+/g, " ").trim();
    const ownedHeadings = ownedElements(element, "h1,h2,h3,h4,h5,h6");
    const ownedParagraphs = ownedElements(element, "p");
    const ownedLinks = ownedElements(element, "a[href]");
    const ownedButtons = ownedElements(element, "button,a[role='button'],input[type='submit']");
    const ownedMedia = ownedElements(element, "img,video");
    const overlay = overlayEvidence(element, style, rect);
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
      localTextLength: ownedTextLength(element),
      localHeadingCount: ownedHeadings.length,
      localParagraphCount: ownedParagraphs.length,
      localLinkCount: ownedLinks.length,
      localButtonCount: ownedButtons.length,
      localMediaCount: ownedMedia.length,
      localFormFieldCount: ownedElements(element, "input,select,textarea").length,
      descendantBoundaryCount: element.querySelectorAll("section,header,footer,[data-bid]").length,
      sectionKind: overlay.sectionKind,
      overlayReason: overlay.overlayReason,
      mediaEvidence: Array.from(element.querySelectorAll("img,video")).slice(0, 40).map((node) => {
        const mediaRect = node.getBoundingClientRect();
        const mediaStyle = window.getComputedStyle(node);
        return {
          kind: node.tagName.toLowerCase() === "video" ? "video" : "image",
          url: node.currentSrc || node.getAttribute("src") || "",
          alt: node.getAttribute("alt") || "",
          bbox: {
            x: Math.max(0, Math.round(mediaRect.left + window.scrollX)),
            y: Math.max(0, Math.round(mediaRect.top + window.scrollY)),
            width: Math.round(mediaRect.width),
            height: Math.round(mediaRect.height),
          },
          naturalWidth: node.naturalWidth || 0,
          naturalHeight: node.naturalHeight || 0,
          objectFit: mediaStyle.objectFit || "",
          objectPosition: mediaStyle.objectPosition || "",
        };
      }),
      backgroundColor: style.backgroundColor || "",
      backgroundImage: style.backgroundImage || "",
      computedStyle: styleEvidence(element),
      visibility: {
        display: style.display,
        visibility: style.visibility,
        opacity: Number(style.opacity || "1"),
        hiddenByAnimation: Number(style.opacity || "1") === 0,
        availableForAnalysis: true,
      },
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
      pageWidth: Math.max(
        document.body?.scrollWidth || 0,
        document.documentElement.scrollWidth,
        document.body?.offsetWidth || 0,
        document.documentElement.offsetWidth
      ),
      pageHeight: Math.max(
        document.body?.scrollHeight || 0,
        document.documentElement.scrollHeight,
        document.body?.offsetHeight || 0,
        document.documentElement.offsetHeight
      ),
    },
    candidates,
    documentStyles: documentStyles(),
  };
}
"""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Segment a captured Showit page into candidate sections.")
    parser.add_argument("--url", required=True, help="Public URL used for capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    parser.add_argument("--output", help="Override the page artifact directory.")
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
    if tag in {"header", "footer"}:
        semantic_score += 8
    elif tag == "section":
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
    return sorted(output, key=lambda item: (item["bbox"]["y"], -selector_priority(item)[0], -selector_priority(item)[1]))


def _contains_bbox(parent: dict[str, Any], child: dict[str, Any], tolerance: int = 8) -> bool:
    parent_bbox = parent["bbox"]
    child_bbox = child["bbox"]
    return (
        child_bbox["x"] >= parent_bbox["x"] - tolerance
        and child_bbox["y"] >= parent_bbox["y"] - tolerance
        and child_bbox["x"] + child_bbox["width"] <= parent_bbox["x"] + parent_bbox["width"] + tolerance
        and child_bbox["y"] + child_bbox["height"] <= parent_bbox["y"] + parent_bbox["height"] + tolerance
    )


def _vertical_coverage(parent: dict[str, Any], children: list[dict[str, Any]]) -> float:
    parent_top = parent["bbox"]["y"]
    parent_bottom = parent_top + parent["bbox"]["height"]
    intervals = sorted(
        (
            max(parent_top, child["bbox"]["y"]),
            min(parent_bottom, child["bbox"]["y"] + child["bbox"]["height"]),
        )
        for child in children
    )
    merged: list[list[int]] = []
    for start, end in intervals:
        if end <= start:
            continue
        if merged and start <= merged[-1][1]:
            merged[-1][1] = max(merged[-1][1], end)
        else:
            merged.append([start, end])
    covered = sum(end - start for start, end in merged)
    return covered / max(1, parent["bbox"]["height"])


def _stage_score(candidate: dict[str, Any], viewport: dict[str, Any]) -> float:
    bbox = candidate["bbox"]
    width = max(1, int(viewport.get("pageWidth") or viewport.get("width") or 1))
    height = max(1, int(viewport.get("pageHeight") or viewport.get("height") or 1))
    score = 0.0
    if candidate.get("tag") in {"section", "header", "footer"}:
        score += 3.0
    if bbox["width"] >= width * 0.65:
        score += 1.0
    if bbox["height"] >= max(120, viewport.get("height", 0) * 0.25):
        score += 1.0
    if candidate.get("localTextLength", 0) >= 32:
        score += 2.0
    if candidate.get("localHeadingCount", 0):
        score += 1.5
    if candidate.get("localParagraphCount", 0):
        score += 0.5
    if candidate.get("localMediaCount", 0):
        score += 1.0
    if candidate.get("localLinkCount", 0) or candidate.get("localButtonCount", 0):
        score += 0.5
    background = str(candidate.get("backgroundColor", ""))
    if background and "rgba(0, 0, 0, 0)" not in background:
        score += 1.0
    if candidate.get("backgroundImage") and candidate["backgroundImage"] != "none":
        score += 1.0
    return score


def select_visual_candidates(
    candidates: list[dict[str, Any]],
    viewport: dict[str, Any],
) -> tuple[list[dict[str, Any]], list[dict[str, Any]], dict[str, Any]]:
    """Select page stages without allowing structural parents to win by size."""
    overlays = [candidate for candidate in candidates if candidate.get("sectionKind") == "overlay"]
    page_candidates = [candidate for candidate in candidates if candidate.get("sectionKind") != "overlay"]
    for candidate in page_candidates:
        candidate["wrapperScore"] = 0.0
        candidate["sectionSelectionReason"] = "independent-candidate"

    suppressed: list[dict[str, Any]] = []
    for parent in page_candidates:
        children = [
            child
            for child in page_candidates
            if child is not parent
            and _contains_bbox(parent, child)
            and child["bbox"]["height"] >= 120
            and _stage_score(child, viewport) >= 4.0
        ]
        if len(children) < 2:
            continue
        coverage = _vertical_coverage(parent, children)
        aggregate_text = max(1, int(parent.get("textLength", 0)))
        local_text = int(parent.get("localTextLength", 0))
        wrapper_score = min(1.0, coverage) * 0.55
        wrapper_score += min(1.0, len(children) / 6) * 0.25
        wrapper_score += 0.20 if local_text <= max(80, aggregate_text * 0.25) else 0.0
        parent["wrapperScore"] = round(wrapper_score, 3)
        if coverage < 0.55 or local_text > max(80, aggregate_text * 0.25):
            continue
        if parent["bbox"]["height"] < max(viewport.get("height", 0) * 1.5, viewport.get("pageHeight", 0) * 0.4):
            continue
        parent["sectionSelectionReason"] = "suppressed-structural-wrapper"
        parent["selectionChildren"] = [child.get("domPath", "") for child in sorted(children, key=lambda item: item["bbox"]["y"])]
        suppressed.append(parent)

    suppressed_ids = {id(candidate) for candidate in suppressed}
    selected = [candidate for candidate in page_candidates if id(candidate) not in suppressed_ids]

    # A footer/header element and a contained section at the same terminal/top
    # boundary are usually one visual stage, not two semantic stages.
    duplicate_ids: set[int] = set()
    for parent in selected:
        if parent.get("tag") not in {"header", "footer"}:
            continue
        for child in selected:
            if child is parent or id(child) in duplicate_ids or not _contains_bbox(parent, child):
                continue
            same_boundary = abs(parent["bbox"]["y"] - child["bbox"]["y"]) <= 32
            broad_child = child["bbox"]["width"] >= parent["bbox"]["width"] * 0.8
            if same_boundary and broad_child:
                child["sectionSelectionReason"] = "suppressed-contained-duplicate"
                child["parentCandidate"] = parent.get("domPath", "")
                duplicate_ids.add(id(child))
    selected = [candidate for candidate in selected if id(candidate) not in duplicate_ids]

    diagnostics = {
        "candidateCount": len(candidates),
        "pageCandidateCount": len(page_candidates),
        "selectedCount": len(selected),
        "overlayCount": len(overlays),
        "suppressedWrappers": [
            {
                "domPath": candidate.get("domPath", ""),
                "bbox": candidate.get("bbox", {}),
                "wrapperScore": candidate.get("wrapperScore", 0.0),
                "children": candidate.get("selectionChildren", []),
            }
            for candidate in suppressed
        ],
        "suppressedContainedDuplicates": [
            {
                "domPath": candidate.get("domPath", ""),
                "parentCandidate": candidate.get("parentCandidate", ""),
                "bbox": candidate.get("bbox", {}),
            }
            for candidate in candidates
            if candidate.get("sectionSelectionReason") == "suppressed-contained-duplicate"
        ],
    }
    return sorted(selected, key=lambda item: (item["bbox"]["y"], -selector_priority(item)[0])), overlays, diagnostics


MIN_EFFECTIVE_CROP_PIXELS = 2


def _coordinate(value: Any) -> float | None:
    try:
        number = float(value)
    except (TypeError, ValueError):
        return None
    return number if math.isfinite(number) else None


def _bbox_edges(bbox: dict[str, Any], start: str, size: str, end: str) -> tuple[float, float] | None:
    explicit_start = "left" if start == "x" else "top"
    start_value = _coordinate(bbox.get(explicit_start if explicit_start in bbox else start))
    if start_value is None:
        return None
    end_value = _coordinate(bbox.get(end)) if end in bbox else None
    if end_value is None:
        size_value = _coordinate(bbox.get(size))
        if size_value is None:
            return None
        end_value = start_value + size_value
    if not math.isfinite(end_value):
        return None
    return (min(start_value, end_value), max(start_value, end_value))


def normalize_crop_bbox(
    bbox: dict[str, Any],
    image_size: tuple[int, int],
) -> tuple[tuple[int, int, int, int] | None, str]:
    """Return a safe pixel crop rectangle and a structured skip reason."""
    image_width, image_height = image_size
    horizontal = _bbox_edges(bbox, "x", "width", "right")
    vertical = _bbox_edges(bbox, "y", "height", "bottom")
    if horizontal is None or vertical is None:
        return None, "missing-or-non-finite-coordinate"

    left_edge, right_edge = horizontal
    top_edge, bottom_edge = vertical
    if right_edge <= left_edge:
        return None, "zero-width"
    if bottom_edge <= top_edge:
        return None, "zero-height"

    left = max(0, min(image_width, math.floor(left_edge)))
    top = max(0, min(image_height, math.floor(top_edge)))
    right = max(0, min(image_width, math.ceil(right_edge)))
    bottom = max(0, min(image_height, math.ceil(bottom_edge)))

    if right <= left or bottom <= top:
        return None, "outside-screenshot"
    if right - left < MIN_EFFECTIVE_CROP_PIXELS or bottom - top < MIN_EFFECTIVE_CROP_PIXELS:
        return None, "too-small-after-clamping"
    return (left, top, right, bottom), ""


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
        candidate["sectionIndex"] = index
        candidate["sectionId"] = slugify(candidate.get("blockSlug") or f"section-{index:02d}", default=f"section-{index:02d}")
        candidate["cropPath"] = ""
        candidate["cropStatus"] = "skipped"
        candidate["cropReason"] = ""
        candidate["cropRect"] = None
        bbox = candidate["bbox"]
        crop_rect, reason = normalize_crop_bbox(bbox, (width, height))
        if crop_rect is None:
            candidate["cropReason"] = reason
            continue
        try:
            crop = image.crop(crop_rect)
        except (OSError, ValueError) as error:
            candidate["cropReason"] = f"crop-failed:{error.__class__.__name__}"
            continue
        slug_basis = candidate.get("blockSlug") or candidate.get("tag") or f"section-{index:02d}"
        filename = f"{index:02d}-{slugify(slug_basis, default='section')}.png"
        output_path = output_dir / filename
        try:
            crop.save(output_path)
        except OSError as error:
            candidate["cropReason"] = f"save-failed:{error.__class__.__name__}"
            continue
        candidate["cropStatus"] = "written"
        candidate["cropRect"] = list(crop_rect)
        candidate["cropPath"] = str(output_path.relative_to(relative_root))


def discover_viewport(
    url: str,
    viewport: dict[str, int],
    selectors: list[str],
    min_height: int,
    min_width: int,
    *,
    mobile: bool = False,
    keep_overlays: bool = False,
) -> dict[str, Any]:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        page = browser.new_page(
            viewport={"width": viewport["width"], "height": viewport["height"]},
            is_mobile=mobile,
            device_scale_factor=1,
        )
        page.goto(url, wait_until="commit", timeout=60_000)
        page.wait_for_selector("html", state="attached", timeout=60_000)
        wait_for_render_ready(page)
        if not keep_overlays:
            dismiss_basic_overlays(page)
            page.wait_for_timeout(300)
        reveal_page_for_analysis(page)
        wait_for_render_ready(page)
        if not page.evaluate("() => Boolean(document.body)"):
            page.close()
            request = Request(url, headers={"User-Agent": "Mozilla/5.0 SiteKitScraper/1.0"})
            with urlopen(request, timeout=60) as response:
                html = response.read().decode(response.headers.get_content_charset() or "utf-8", errors="replace")
            page = browser.new_page(
                viewport={"width": viewport["width"], "height": viewport["height"]},
                is_mobile=mobile,
                device_scale_factor=1,
                java_script_enabled=False,
            )
            page.set_content(html, wait_until="commit", timeout=60_000)
            wait_for_render_ready(page)
            if not keep_overlays:
                dismiss_basic_overlays(page)
                page.wait_for_timeout(300)
            reveal_page_for_analysis(page)
            wait_for_render_ready(page)
        discovery = page.evaluate(
            DISCOVERY_SCRIPT,
            {"selectors": selectors, "minHeight": min_height, "minWidth": min_width},
        )
        browser.close()
    candidates = dedupe_candidates(discovery.pop("candidates"))
    sections, overlays, selection_diagnostics = select_visual_candidates(candidates, discovery["viewport"])
    discovery["sections"] = sections
    discovery["overlays"] = overlays
    discovery["selectionDiagnostics"] = selection_diagnostics
    discovery["viewport"]["mobile"] = mobile
    return discovery


def main() -> int:
    args = parse_args()
    paths, capture_metadata = load_capture_metadata(args.url, site_slug=args.site_slug, page_slug=args.page_slug, output_dir=args.output)
    ensure_scrape_paths(paths)
    desktop_viewport = capture_metadata["desktop"]["viewport"]

    selectors = [
        "#si-sp > div[data-bid]",
        "#si-sp > div.sb",
        "header",
        "body > section",
        "main > section",
        "main section",
        "section",
        "footer",
        "body > div",
        "main > div",
    ]

    discovery = discover_viewport(
        capture_metadata["desktop"]["finalUrl"],
        desktop_viewport,
        selectors,
        args.min_height,
        args.min_width,
        keep_overlays=args.keep_overlays,
    )
    mobile_viewport = capture_metadata["mobile"]["viewport"]
    mobile_discovery = discover_viewport(
        capture_metadata["mobile"]["finalUrl"],
        mobile_viewport,
        selectors,
        args.min_height,
        args.min_width,
        mobile=True,
        keep_overlays=args.keep_overlays,
    )
    for index, section in enumerate(mobile_discovery["sections"], start=1):
        section["sectionIndex"] = index
        section["sectionId"] = slugify(section.get("blockSlug") or f"section-{index:02d}", default=f"section-{index:02d}")
    candidates = discovery["sections"]
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
        "documentStyles": discovery.get("documentStyles", {}),
        "overlays": discovery.get("overlays", []),
        "selectionDiagnostics": discovery.get("selectionDiagnostics", {}),
    }
    output_path = paths.sections_dir / "sections.segmented.json"
    write_json(output_path, payload)
    mobile_payload = {
        "version": "0.1",
        "sourceUrl": capture_metadata["sourceUrl"],
        "finalUrl": capture_metadata["mobile"]["finalUrl"],
        "siteSlug": paths.site_slug,
        "pageSlug": paths.page_slug,
        "viewport": mobile_discovery["viewport"],
        "sections": mobile_discovery["sections"],
        "documentStyles": mobile_discovery.get("documentStyles", {}),
        "overlays": mobile_discovery.get("overlays", []),
        "selectionDiagnostics": mobile_discovery.get("selectionDiagnostics", {}),
    }
    mobile_output_path = paths.sections_dir / "sections.segmented.mobile.json"
    write_json(mobile_output_path, mobile_payload)
    print(output_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
