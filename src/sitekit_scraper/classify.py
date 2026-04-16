from __future__ import annotations

import argparse
from typing import Any

from .utils import load_capture_metadata, read_json, write_json


SERVICE_KEYWORDS = {"services", "offerings", "packages", "collections", "experience", "investment"}
TESTIMONIAL_KEYWORDS = {"testimonial", "testimonials", "review", "reviews", "kind words", "love notes"}
GALLERY_KEYWORDS = {"portfolio", "gallery", "journal", "featured work", "instagram"}
NEWSLETTER_KEYWORDS = {"newsletter", "subscribe", "inbox", "download", "freebie"}
CONTACT_KEYWORDS = {"contact", "inquire", "enquire", "inquiry", "get in touch", "book now"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Classify extracted Showit sections into roles and pattern families.")
    parser.add_argument("--url", required=True, help="Public URL used for capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    return parser.parse_args()


def contains_keyword(section: dict[str, Any], keywords: set[str]) -> bool:
    text = " ".join(section.get("headings", []) + section.get("paragraphs", []) + [section.get("textSample", "")]).lower()
    return any(keyword in text for keyword in keywords)


def classify_section(section: dict[str, Any]) -> dict[str, Any]:
    tag = section.get("tag", "")
    block_slug = (section.get("blockSlug", "") or "").lower()
    stats = section["stats"]
    bbox = section["bbox"]
    top = bbox["y"]
    cues: list[str] = []

    if tag == "header" or any(token in block_slug for token in {"menu", "header"}):
        return {
            "role": "header",
            "patternFamily": "editorial-header-nav",
            "confidence": 0.99,
            "cues": ["header/menu block slug" if block_slug else "semantic header tag"],
            "blockFamilyHint": "header",
        }
    if tag == "footer" or "footer" in block_slug:
        return {
            "role": "footer",
            "patternFamily": "editorial-footer-nav",
            "confidence": 0.99,
            "cues": ["footer block slug" if block_slug else "semantic footer tag"],
            "blockFamilyHint": "footer",
        }

    if block_slug.startswith("hero"):
        cues.append("showit hero block slug")
        role = "hero"
        family = "editorial-hero-overlay"
        block_family = "hero"
    elif "newsletter" in block_slug:
        cues.append("showit newsletter block slug")
        role = "cta"
        family = "newsletter-split"
        block_family = "cta"
    elif any(token in block_slug for token in {"bio", "about", "mission"}):
        cues.append("showit bio/about block slug")
        role = "content"
        family = "bio-band-with-portrait" if "bio" in block_slug else "split-intro-story"
        block_family = "content"
    elif any(token in block_slug for token in {"review", "testimonial"}):
        cues.append("showit review/testimonial block slug")
        role = "testimonial"
        family = "client-praise-band"
        block_family = "testimonials"
    elif any(token in block_slug for token in {"project", "explore", "resource", "journal"}):
        cues.append("showit project/resource block slug")
        role = "cards"
        family = "portfolio-feature-list"
        block_family = "cards"
    elif any(token in block_slug for token in {"portfolio", "gallery"}):
        cues.append("showit portfolio/gallery block slug")
        role = "gallery"
        family = "portfolio-feature-list"
        block_family = "gallery"
    elif any(token in block_slug for token in {"offering", "service"}):
        cues.append("showit offerings/services block slug")
        role = "services"
        family = "oversized-services-list"
        block_family = "services"
    elif stats["formFieldCount"] > 0 or contains_keyword(section, CONTACT_KEYWORDS):
        cues.append("form or contact language")
        role = "contact"
        family = "contact-conversion-band"
        block_family = "cta"
    elif top < 220 and stats["headingCount"] >= 1 and stats["imageCount"] >= 1 and bbox["height"] >= 500:
        cues.append("top-of-page section")
        cues.append("large media and heading")
        role = "hero"
        family = "editorial-hero-overlay" if section["style"]["backgroundImage"] or stats["imageCount"] >= 1 else "hero-band"
        block_family = "hero"
    elif contains_keyword(section, TESTIMONIAL_KEYWORDS):
        cues.append("testimonial language")
        role = "testimonial"
        family = "client-praise-band"
        block_family = "testimonials"
    elif contains_keyword(section, NEWSLETTER_KEYWORDS):
        cues.append("newsletter language")
        role = "cta"
        family = "newsletter-split"
        block_family = "cta"
    elif stats["imageCount"] >= 6 or contains_keyword(section, GALLERY_KEYWORDS):
        cues.append("image-heavy section")
        role = "gallery"
        family = "instagram-grid" if stats["imageCount"] >= 6 else "portfolio-feature-list"
        block_family = "gallery"
    elif stats["repeatedItemCount"] >= 3 and contains_keyword(section, SERVICE_KEYWORDS):
        cues.append("repeated service-like items")
        role = "services"
        family = "oversized-services-list"
        block_family = "services"
    elif stats["buttonCount"] >= 1 and stats["textLength"] <= 700:
        cues.append("compact conversion section")
        role = "cta"
        family = "dark-cta-band" if section["style"]["backgroundColor"] else "cta-band"
        block_family = "cta"
    else:
        cues.append("default long-form content")
        role = "content"
        family = "split-intro-story" if stats["imageCount"] >= 1 else "content-band"
        block_family = "content"

    confidence = 0.72
    if role in {"hero", "header", "footer"}:
        confidence = 0.88
    if role == "contact":
        confidence = 0.84
    if role == "services" and stats["repeatedItemCount"] >= 4:
        confidence = 0.86

    return {
        "role": role,
        "patternFamily": family,
        "confidence": confidence,
        "cues": cues,
        "blockFamilyHint": block_family,
    }


def main() -> int:
    args = parse_args()
    paths, capture_metadata = load_capture_metadata(args.url, site_slug=args.site_slug, page_slug=args.page_slug)
    extracted = read_json(paths.sections_dir / "sections.extracted.json")
    sections = []
    for section in extracted["sections"]:
        enriched = dict(section)
        enriched["classification"] = classify_section(section)
        sections.append(enriched)

    payload = {
        "version": "0.1",
        "sourceUrl": capture_metadata["sourceUrl"],
        "finalUrl": capture_metadata["desktop"]["finalUrl"],
        "siteSlug": paths.site_slug,
        "pageSlug": paths.page_slug,
        "sections": sections,
    }
    output_path = paths.sections_dir / "sections.classified.json"
    write_json(output_path, payload)
    print(output_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
