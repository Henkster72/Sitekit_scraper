from __future__ import annotations

import argparse
import re
from collections import Counter
from pathlib import Path
from typing import Any

from bs4 import BeautifulSoup
from lxml import etree, html as lxml_html
from PIL import Image, ImageFilter, ImageStat

from .utils import DATA_DIR, PROJECT_ROOT, dedupe_strings, load_capture_metadata, normalise_whitespace, read_json, resolve_url, write_json


STYLE_URL_PATTERN = re.compile(r"url\((['\"]?)([^'\"\)]+)\1\)")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Extract normalized section content from segmented Showit artifacts.")
    parser.add_argument("--url", required=True, help="Public URL used for capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    return parser.parse_args()


def parse_style_urls(style_value: str, base_url: str) -> list[str]:
    urls = [resolve_url(match.group(2), base_url) for match in STYLE_URL_PATTERN.finditer(style_value or "")]
    return dedupe_strings([url for url in urls if url])


def first_src_from_srcset(value: str) -> str:
    parts = [part.strip() for part in value.split(",") if part.strip()]
    if not parts:
        return ""
    return parts[-1].split()[0]


def node_signature(node: Any) -> str:
    children = [child for child in node.find_all(recursive=False) if getattr(child, "name", None)]
    child_names = ",".join(child.name for child in children[:6])
    classes = " ".join(sorted(node.get("class", []))[:2])
    return f"{node.name}|{classes}|{child_names}"


def estimate_repeated_items(soup: BeautifulSoup) -> int:
    scores: list[int] = []
    for container in soup.find_all(["section", "div", "ul", "ol"]):
        children = [child for child in container.find_all(recursive=False) if getattr(child, "name", None)]
        if len(children) < 2 or len(children) > 12:
            continue
        signatures = [node_signature(child) for child in children]
        common = Counter(signatures).most_common(1)
        if common and common[0][1] >= 2:
            scores.append(common[0][1])
    return max(scores or [0])


def resolve_crop_path(crop_path: str) -> Path | None:
    path = Path(crop_path)
    candidates = [path] if path.is_absolute() else [PROJECT_ROOT / path, DATA_DIR / path]
    for candidate in candidates:
        if candidate.is_file():
            return candidate
    return None


def visual_stats_for_crop(crop_path: str) -> dict[str, Any]:
    absolute_path = resolve_crop_path(crop_path)
    if absolute_path is None:
        return {
            "width": 0,
            "height": 0,
            "aspectRatio": 0,
            "orientation": "unknown",
            "meanLuminance": 0,
            "stddevLuminance": 0,
            "edgeDensity": 0,
            "entropy": 0,
            "isDark": False,
            "isBright": False,
            "isLowContrast": False,
        }

    with Image.open(absolute_path) as image:
        rgb = image.convert("RGB")
        gray = rgb.convert("L")
        width, height = rgb.size
        luminance = ImageStat.Stat(gray)
        mean_luminance = float(luminance.mean[0]) if luminance.mean else 0.0
        stddev_luminance = float(luminance.stddev[0]) if luminance.stddev else 0.0
        edges = gray.filter(ImageFilter.FIND_EDGES)
        edge_stats = ImageStat.Stat(edges)
        edge_mean = float(edge_stats.mean[0]) if edge_stats.mean else 0.0
        aspect_ratio = round((width / height), 4) if width and height else 0.0
        if width > height * 1.1:
            orientation = "landscape"
        elif height > width * 1.1:
            orientation = "portrait"
        else:
            orientation = "square"

        return {
            "width": width,
            "height": height,
            "aspectRatio": aspect_ratio,
            "orientation": orientation,
            "meanLuminance": round(mean_luminance, 2),
            "stddevLuminance": round(stddev_luminance, 2),
            "edgeDensity": round(edge_mean / 255, 4),
            "entropy": round(float(gray.entropy()), 4),
            "isDark": mean_luminance < 105,
            "isBright": mean_luminance > 185,
            "isLowContrast": stddev_luminance < 32,
        }


def extract_section(section: dict[str, Any], base_url: str) -> dict[str, Any]:
    outer_html = section.get("outerHtml", "")
    soup = BeautifulSoup(outer_html, "lxml")
    try:
        tree = lxml_html.fromstring(outer_html) if outer_html else lxml_html.fromstring("<section></section>")
    except (etree.ParserError, ValueError):
        tree = lxml_html.fromstring("<section></section>")

    headings = dedupe_strings([tag.get_text(" ", strip=True) for tag in soup.select("h1, h2, h3, h4, h5, h6")])
    paragraphs = dedupe_strings([tag.get_text(" ", strip=True) for tag in soup.select("p")])
    buttons = []
    for node in soup.select("button, a[href], input[type='submit']"):
        label = normalise_whitespace(node.get_text(" ", strip=True) or node.get("value", ""))
        href = node.get("href", "")
        if label:
            buttons.append({"label": label, "url": resolve_url(href, base_url) if href else ""})

    links = []
    for node in soup.select("a[href]"):
        href = resolve_url(node.get("href", ""), base_url)
        label = normalise_whitespace(node.get_text(" ", strip=True))
        if href:
            links.append({"label": label, "url": href})

    images: list[str] = []
    for node in soup.select("img"):
        src = node.get("src") or first_src_from_srcset(node.get("srcset", ""))
        if src:
            images.append(resolve_url(src, base_url))
    for node in soup.select("[style]"):
        images.extend(parse_style_urls(node.get("style", ""), base_url))
    if section.get("backgroundImage"):
        images.extend(parse_style_urls(section["backgroundImage"], base_url))

    plain_text = normalise_whitespace(" ".join(tree.xpath("//text()")))
    form_fields = len(soup.select("input, select, textarea"))
    repeated_item_count = estimate_repeated_items(soup)

    return {
        "sectionId": section["sectionId"],
        "sectionIndex": section["sectionIndex"],
        "tag": section["tag"],
        "blockSlug": section.get("blockSlug", ""),
        "selector": section["selector"],
        "domPath": section["domPath"],
        "bbox": section["bbox"],
        "cropPath": section["cropPath"],
        "headings": headings,
        "paragraphs": paragraphs,
        "buttons": buttons,
        "links": links,
        "imageUrls": dedupe_strings(images),
        "stats": {
            "textLength": len(plain_text),
            "headingCount": len(headings),
            "paragraphCount": len(paragraphs),
            "buttonCount": len(buttons),
            "linkCount": len(links),
            "imageCount": len(dedupe_strings(images)),
            "formFieldCount": form_fields,
            "repeatedItemCount": repeated_item_count,
        },
        "visual": visual_stats_for_crop(section["cropPath"]),
        "style": {
            "backgroundColor": section.get("backgroundColor", ""),
            "backgroundImage": section.get("backgroundImage", ""),
        },
        "textSample": section.get("textSample", ""),
        "rawText": plain_text[:4000],
    }


def main() -> int:
    args = parse_args()
    paths, capture_metadata = load_capture_metadata(args.url, site_slug=args.site_slug, page_slug=args.page_slug)
    segmented = read_json(paths.sections_dir / "sections.segmented.json")
    sections = [extract_section(section, capture_metadata["desktop"]["finalUrl"]) for section in segmented["sections"]]
    payload = {
        "version": "0.1",
        "sourceUrl": capture_metadata["sourceUrl"],
        "finalUrl": capture_metadata["desktop"]["finalUrl"],
        "siteSlug": paths.site_slug,
        "pageSlug": paths.page_slug,
        "sections": sections,
    }
    output_path = paths.sections_dir / "sections.extracted.json"
    write_json(output_path, payload)
    print(output_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
