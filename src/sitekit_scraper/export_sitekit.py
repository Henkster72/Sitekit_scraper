from __future__ import annotations

import argparse
from typing import Any
from urllib.parse import urlparse

from .utils import load_capture_metadata, read_json, write_json


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export mapper JSON and a draft SiteKit shell from matched Showit sections.")
    parser.add_argument("--url", required=True, help="Public URL used for capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    return parser.parse_args()


def block_data_for_section(section: dict[str, Any]) -> dict[str, Any]:
    role = section["classification"]["role"]
    headings = section.get("headings", [])
    paragraphs = section.get("paragraphs", [])
    buttons = section.get("buttons", [])
    images = section.get("imageUrls", [])

    if role == "hero":
        return {
            "heading": headings[0] if headings else "",
            "subheading": paragraphs[0] if paragraphs else "",
            "ctaLabel": buttons[0]["label"] if buttons else "",
            "ctaUrl": buttons[0]["url"] if buttons else "",
            "imageUrl": images[0] if images else "",
        }
    if role == "cta" or role == "contact":
        payload = {
            "heading": headings[0] if headings else "",
            "text": paragraphs[0] if paragraphs else "",
            "buttonLabel": buttons[0]["label"] if buttons else "",
            "buttonUrl": buttons[0]["url"] if buttons else "",
        }
        if images:
            payload["imageUrl"] = images[0]
        return payload
    if role == "gallery":
        return {
            "sectionTitle": headings[0] if headings else " ",
            "images": [{"url": url, "alt": ""} for url in images[:9]],
        }
    if role == "cards":
        cards = []
        for index, url in enumerate(images[:3]):
            cards.append(
                {
                    "badge": f"{index + 1:02d}",
                    "title": headings[index] if index < len(headings) else (headings[0] if headings else ""),
                    "text": paragraphs[index] if index < len(paragraphs) else "",
                    "linkUrl": buttons[index]["url"] if index < len(buttons) else "",
                    "linkLabel": buttons[index]["label"] if index < len(buttons) else "",
                    "imageUrl": url,
                }
            )
        return {
            "sectionTitle": headings[0] if headings else "",
            "columns": 2,
            "cards": cards,
        }
    if role == "testimonial":
        return {
            "sectionTitle": headings[0] if headings else "Kind words",
            "columns": 1,
            "items": [
                {
                    "quote": paragraphs[0] if paragraphs else section.get("textSample", ""),
                    "name": "",
                    "role": "",
                }
            ],
        }
    if role == "services":
        items = []
        for button in buttons[:4]:
            items.append(
                {
                    "title": button["label"],
                    "text": "",
                    "linkLabel": button["label"],
                    "linkUrl": button["url"],
                }
            )
        return {
            "sectionTitle": headings[0] if headings else "Services",
            "columns": 1,
            "items": items,
        }
    return {
        "sectionTitle": headings[0] if headings else "",
        "text": paragraphs[0] if paragraphs else section.get("textSample", ""),
        "imageUrl": images[0] if images else "",
    }


def mapper_section(section: dict[str, Any]) -> dict[str, Any]:
    match = section["variantMatches"][0] if section["variantMatches"] else {"type": section["classification"]["blockFamilyHint"]}
    buttons = section.get("buttons", [])
    images = section.get("imageUrls", [])
    style = {
        "backgroundColor": section["style"].get("backgroundColor", ""),
        "copyAlignment": "center" if section["bbox"]["width"] < 900 else "split",
        "backgroundImage": bool(section["style"].get("backgroundImage")),
    }
    media = [
        {
            "slot": "primary" if index == 0 else f"image-{index + 1}",
            "url": url,
            "alt": "",
            "source": "showit",
        }
        for index, url in enumerate(images[:8])
    ]
    content = {
        "heading": section["headings"][0] if section["headings"] else "",
        "subheading": section["paragraphs"][0] if section["paragraphs"] else "",
        "paragraphs": section["paragraphs"][:4],
        "buttons": buttons[:3],
    }
    return {
        "id": section["sectionId"],
        "kind": "section",
        "role": section["classification"]["role"],
        "sourcePattern": {
            "family": section["classification"]["patternFamily"],
            "label": section["classification"]["patternFamily"].replace("-", " ").title(),
            "confidence": section["classification"]["confidence"],
            "cues": section["classification"]["cues"],
        },
        "targetBlock": {
            key: value
            for key, value in match.items()
            if key in {"type", "variant", "layoutMode", "navLayout"}
        },
        "content": content,
        "media": media,
        "style": style,
        "notes": [
            "Generated from the Python foundation pipeline.",
            "Validate this mapper before final PHP bridge export.",
        ],
    }


def theme_shell() -> dict[str, Any]:
    return {
        "name": "Showit Import Draft",
        "colors": {
            "background": "#f7f4ef",
            "surface": "#fffdfa",
            "primary": "#1f1f1f",
            "secondary": "#6d665f",
            "text": "#1f1f1f",
            "textMuted": "#6d665f",
            "border": "#d8d1c8",
        },
    }


def palette_shell() -> dict[str, Any]:
    return {
        "base": "#1f1f1f",
        "lighter": "#f7f4ef",
        "darker": "#111111",
        "complementary": "#b9ad9a",
        "splitComplementary": "#d8d1c8",
        "triadic": "#ece5dc",
        "background": "#f7f4ef",
    }


def typography_shell() -> dict[str, Any]:
    return {
        "mode": "theme",
        "heading": {
            "family": '"Cormorant Garamond", Georgia, serif',
            "size": 72,
            "weight": 400,
            "lineHeight": 1.05,
            "letterSpacing": 0.02,
            "color": "#1f1f1f",
            "h2Color": "#1f1f1f",
            "h3PlusColor": "#1f1f1f",
        },
        "body": {
            "family": '"Inter", "Helvetica Neue", Arial, sans-serif',
            "size": 17,
            "weight": 300,
            "lineHeight": 1.7,
            "letterSpacing": 0.01,
            "color": "#6d665f",
        },
    }


def draft_sitekit_payload(site_name: str, base_url: str, page_slug: str, page_title: str, sections: list[dict[str, Any]]) -> dict[str, Any]:
    header = next((section for section in sections if section["classification"]["role"] == "header"), None)
    footer = next((section for section in sections if section["classification"]["role"] == "footer"), None)
    body_sections = [section for section in sections if section["classification"]["role"] not in {"header", "footer"}]

    blocks = []
    for section in body_sections:
        match = section["variantMatches"][0] if section["variantMatches"] else {"type": section["classification"]["blockFamilyHint"], "variant": 1}
        block = {
            "type": match["type"],
            "variant": match.get("variant", 1),
            "backgroundColor": section["style"].get("backgroundColor") or "#f7f4ef",
            "verticalPaddingScale": 8,
            "data": block_data_for_section(section),
        }
        if "layoutMode" in match:
            block["data"]["layoutMode"] = match["layoutMode"]
        if "navLayout" in match:
            block["data"]["navLayout"] = match["navLayout"]
        blocks.append(block)

    shared_blocks = {}
    if header:
        match = header["variantMatches"][0]
        shared_blocks["header"] = {
            "type": match["type"],
            "variant": match.get("variant", 1),
            "data": {
                "brand": site_name,
                "links": header.get("links", [])[:8],
            },
        }
        if "navLayout" in match:
            shared_blocks["header"]["data"]["navLayout"] = match["navLayout"]
    if footer:
        match = footer["variantMatches"][0]
        shared_blocks["footer"] = {
            "type": match["type"],
            "variant": match.get("variant", 1),
            "data": {
                "heading": site_name,
                "tagline": footer.get("textSample", ""),
                "links": footer.get("links", [])[:8],
            },
        }

    page = {
        "id": page_slug,
        "title": page_title,
        "slug": page_slug,
        "blocks": blocks,
    }
    return {
        "version": "1.0",
        "theme": theme_shell(),
        "palette": palette_shell(),
        "typography": typography_shell(),
        "site": {
            "name": site_name,
            "activePageId": page_slug,
            "baseUrl": base_url,
            "pages": [page],
            "sharedBlocks": shared_blocks,
        },
        "page": page,
    }


def main() -> int:
    args = parse_args()
    paths, capture_metadata = load_capture_metadata(args.url, site_slug=args.site_slug, page_slug=args.page_slug)
    matched = read_json(paths.sections_dir / "sections.matched.json")

    parsed = urlparse(capture_metadata["desktop"]["finalUrl"])
    base_url = f"{parsed.scheme}://{parsed.netloc}/"
    site_name = capture_metadata["desktop"]["title"] or paths.site_slug.replace("-", " ").title()
    page_title = capture_metadata["desktop"]["title"] or paths.page_slug.replace("-", " ").title()

    sections = matched["sections"]
    mapper_sections = [mapper_section(section) for section in sections if section["classification"]["role"] not in {"header", "footer"}]
    globals_payload = {}
    header = next((section for section in sections if section["classification"]["role"] == "header"), None)
    footer = next((section for section in sections if section["classification"]["role"] == "footer"), None)
    if header:
        globals_payload["header"] = mapper_section(header)
    if footer:
        globals_payload["footer"] = mapper_section(footer)

    mapper_payload = {
        "version": "1.0",
        "source": {
            "platform": "showit",
            "sourceUrls": [capture_metadata["sourceUrl"]],
            "baseUrl": base_url,
            "extractionMode": "live-dom",
            "artifacts": [
                {"type": "html", "path": capture_metadata["artifacts"]["html"]},
                {"type": "screenshot", "path": capture_metadata["artifacts"]["desktopScreenshot"]},
                {"type": "screenshot", "path": capture_metadata["artifacts"]["mobileScreenshot"]},
            ],
        },
        "globals": globals_payload,
        "pages": [
            {
                "id": paths.page_slug,
                "title": page_title,
                "slug": paths.page_slug,
                "sourceUrl": capture_metadata["sourceUrl"],
                "sections": mapper_sections,
            }
        ],
    }

    sitekit_draft = draft_sitekit_payload(site_name, base_url, paths.page_slug, page_title, sections)
    mapper_path = paths.mapper_dir / "showit.mapper.json"
    sitekit_path = paths.sitekit_dir / "sitekit.draft.json"
    write_json(mapper_path, mapper_payload)
    write_json(sitekit_path, sitekit_draft)
    print(mapper_path)
    print(sitekit_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
