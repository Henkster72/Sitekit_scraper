from __future__ import annotations

import argparse
import re
from collections import Counter
from typing import Any

from .extract import extract_section
from .utils import load_capture_metadata, read_json, slugify, write_json


CONTRACT_FORMAT = "sitekit-visual-evidence"
CONTRACT_VERSION = "1.0"
NUMBER_PATTERN = re.compile(r"-?\d+(?:\.\d+)?")
COLOR_PATTERN = re.compile(r"#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)|hsla?\([^)]*\)")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Export visual-evidence.v1 for the main SiteKit mapper.")
    parser.add_argument("--url", required=True, help="URL used for capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    parser.add_argument("--output", help="Override the page artifact directory.")
    parser.add_argument("--source-type", default="unknown", help="Observed source type, such as showit or wordpress.")
    return parser.parse_args()


def identity_key(section: dict[str, Any], index: int) -> str:
    classes = section.get("classes", [])
    source_pattern = next((value for value in classes if str(value).startswith("pattern-")), "")
    return slugify(
        str(section.get("blockSlug") or source_pattern or section.get("sectionId") or f"section-{index:02d}"),
        default=f"section-{index:02d}",
    )


def stable_evidence_ids(sections: list[dict[str, Any]]) -> dict[str, str]:
    counts: Counter[str] = Counter(identity_key(section, index) for index, section in enumerate(sections, start=1))
    seen: Counter[str] = Counter()
    output: dict[str, str] = {}
    for index, section in enumerate(sections, start=1):
        key = identity_key(section, index)
        seen[key] += 1
        suffix = f"-{seen[key]:02d}" if counts[key] > 1 else ""
        output[f"{key}:{index}"] = f"section-{key}{suffix}"
    return output


def section_key(section: dict[str, Any], index: int) -> str:
    return identity_key(section, index)


def dedupe_dicts(items: list[dict[str, Any]], key: str) -> list[dict[str, Any]]:
    output: list[dict[str, Any]] = []
    seen: set[str] = set()
    for item in items:
        value = str(item.get(key, ""))
        if not value or value in seen:
            continue
        seen.add(value)
        output.append(item)
    return output


def colors_from_values(values: list[str]) -> list[str]:
    colors: list[str] = []
    for value in values:
        colors.extend(COLOR_PATTERN.findall(value or ""))
    return list(dict.fromkeys(colors))


def numeric_values(values: list[str]) -> list[float]:
    output: list[float] = []
    for value in values:
        for match in NUMBER_PATTERN.findall(value or ""):
            number = float(match)
            if number not in output:
                output.append(number)
    return sorted(output)


def section_media(section: dict[str, Any]) -> list[dict[str, str]]:
    observed = section.get("mediaEvidence", [])
    if observed:
        return observed
    return [{"url": url, "alt": "", "kind": "image"} for url in section.get("imageUrls", []) if url]


def section_actions(section: dict[str, Any]) -> list[dict[str, str]]:
    actions: list[dict[str, str]] = []
    for item in section.get("buttons", []) + section.get("links", []):
        if not isinstance(item, dict):
            continue
        label = str(item.get("label", "")).strip()
        url = str(item.get("url", "")).strip()
        if label or url:
            actions.append({"label": label, "url": url, "kind": "action"})
    return dedupe_dicts(actions, "url")


def section_evidence(section: dict[str, Any], viewport: str, evidence_id: str, order: int) -> dict[str, Any]:
    classification = section.get("classification", {})
    pattern_candidates = list(section.get("patternCandidates", []))
    if not pattern_candidates and classification.get("patternFamily"):
        pattern_candidates = [
            {
                "label": classification["patternFamily"],
                "confidence": float(classification.get("confidence", 0.0)),
                "evidence": list(classification.get("cues", [])),
            }
        ]
    confidence = float(classification.get("confidence", 0.0))
    visibility = section.get("visibility", {})
    repeated_items = section.get("repeatedItems") if isinstance(section.get("repeatedItems"), dict) else {}
    repeated_count = int(repeated_items.get("count") or section.get("stats", {}).get("repeatedItemCount", 0) or 0)
    repeated_observation = {
        "count": repeated_count,
        "evidence": list(repeated_items.get("evidence") or (["repeated DOM child signature"] if repeated_count else [])),
        "items": list(repeated_items.get("items") or []),
    }
    for key in ("interactive", "collection"):
        if key in repeated_items:
            repeated_observation[key] = repeated_items[key]
    return {
        "evidenceId": evidence_id,
        "order": order,
        "locator": {
            "selector": section.get("selector", ""),
            "domPath": section.get("domPath", ""),
            "tag": section.get("tag", ""),
            "blockSlug": section.get("blockSlug", ""),
            "classes": section.get("classes", []),
        },
        "bbox": section.get("bbox", {}),
        "visibility": visibility,
        "text": {
            "sample": section.get("textSample", ""),
            "raw": section.get("rawText", ""),
            "headings": section.get("headings", []),
            "paragraphs": section.get("paragraphs", []),
        },
        "actions": section_actions(section),
        "media": section_media(section),
        "forms": {
            "fieldCount": int(section.get("forms", {}).get("fieldCount", section.get("stats", {}).get("formFieldCount", 0))),
            "fields": section.get("forms", {}).get("fields", []),
        },
        "repeatedItems": repeated_observation,
        "navItems": list(section.get("navItems") or []),
        "background": {
            "color": section.get("style", {}).get("backgroundColor", ""),
            "image": section.get("style", {}).get("backgroundImage", ""),
        },
        "dominantSurfaces": section.get("dominantSurfaces", []),
        "underlyingSurface": section.get("underlyingSurface"),
        "accentColors": section.get("accentColors", []),
        "computedVisual": section.get("style", {}).get("computed", {}),
        "visual": section.get("visual", {}),
        "cropStatus": section.get("cropStatus", "not-captured"),
        "cropReason": section.get("cropReason", ""),
        "cropRect": section.get("cropRect"),
        "roleCandidates": (
            [{"label": classification["role"], "confidence": confidence, "evidence": list(classification.get("cues", []))}]
            if classification.get("role")
            else []
        ),
        "patternCandidates": pattern_candidates,
        "confidence": confidence,
        "evidence": list(classification.get("cues", [])) + ([f"viewport:{viewport}"] if viewport else []),
    }


def extract_mobile_sections(segmented: dict[str, Any], base_url: str) -> list[dict[str, Any]]:
    sections: list[dict[str, Any]] = []
    for index, section in enumerate(segmented.get("sections", []), start=1):
        enriched = extract_section(section, base_url)
        enriched["sectionIndex"] = index
        enriched["sectionId"] = section.get("sectionId", enriched.get("sectionId", f"section-{index:02d}"))
        sections.append(enriched)
    return sections


def aggregate_theme(
    desktop: list[dict[str, Any]],
    mobile: list[dict[str, Any]],
    document_styles: list[dict[str, Any]],
    document_surfaces: dict[str, Any] | None = None,
) -> dict[str, Any]:
    all_sections = desktop + mobile
    computed = [section.get("style", {}).get("computed", {}) for section in all_sections]
    background_values = [section.get("style", {}).get("backgroundColor", "") for section in all_sections]
    background_values.extend(style.get("root", {}).get("backgroundColor", "") for style in document_styles)
    surface_records: list[dict[str, Any]] = []
    for viewport_name, sections in (("desktop", desktop), ("mobile", mobile)):
        for section in sections:
            surface_records.append(
                {
                    "viewport": viewport_name,
                    "evidenceId": section.get("sectionId", ""),
                    "rootSurface": section.get("style", {}).get("backgroundColor", ""),
                    "underlyingSurface": section.get("underlyingSurface"),
                    "dominantSurfaces": section.get("dominantSurfaces", []),
                    "accentColors": section.get("accentColors", []),
                }
            )
            background_values.extend(item.get("color", "") for item in section.get("dominantSurfaces", []))
            if section.get("underlyingSurface"):
                background_values.append(section["underlyingSurface"].get("color", ""))
            background_values.extend(item.get("color", "") for item in section.get("accentColors", []))
    document_surfaces = document_surfaces or {}
    for viewport_surface in document_surfaces.values():
        if isinstance(viewport_surface, dict):
            for surface in viewport_surface.values():
                if isinstance(surface, dict):
                    background_values.append(surface.get("color", ""))
    colors = colors_from_values(background_values + [item.get("color", "") for item in computed])
    families = list(dict.fromkeys(value for item in computed for value in item.get("fontFamilies", []) if value))
    heading_families = list(dict.fromkeys(value for item in computed for value in item.get("headingFontFamilies", []) if value))
    body_families = list(dict.fromkeys(value for item in computed for value in item.get("bodyFontFamilies", []) if value))
    heading_sizes = numeric_values([value for item in computed for value in item.get("headingFontSizes", [])])
    body_sizes = numeric_values([value for item in computed for value in item.get("bodyFontSizes", [])])
    radii = numeric_values([value for item in computed for value in item.get("borderRadii", [])])
    borders = numeric_values([value for item in computed for value in item.get("borderWidths", [])])
    heights = [int(section.get("bbox", {}).get("height", 0)) for section in desktop if section.get("bbox", {}).get("height")]
    aspect_ratios = [section.get("visual", {}).get("aspectRatio") for section in desktop if section.get("visual", {}).get("aspectRatio")]
    orientations = [section.get("visual", {}).get("orientation") for section in desktop if section.get("visual", {}).get("orientation")]
    luminance = [section.get("visual", {}).get("meanLuminance", 0) for section in desktop]
    contrast = [section.get("visual", {}).get("stddevLuminance", 0) for section in desktop]
    return {
        "palette": {
            "measuredColors": colors[:32],
            "cssVariables": {key: value for style in document_styles for key, value in style.get("cssVariables", {}).items()},
        },
        "surfaceEvidence": {
            "document": document_surfaces,
            "sections": surface_records,
        },
        "typography": {
            "observedFontFamilies": families[:32],
            "headingFontFamilies": heading_families[:16],
            "bodyFontFamilies": body_families[:16],
            "headingSizeRangePx": [min(heading_sizes), max(heading_sizes)] if heading_sizes else [],
            "bodySizeRangePx": [min(body_sizes), max(body_sizes)] if body_sizes else [],
            "classes": ["computed-style-observation"],
        },
        "geometry": {
            "radiusPxDistribution": radii,
            "borderWidthPxDistribution": borders,
        },
        "density": {
            "sectionHeightsPx": heights,
            "spacingObservations": [
                max(0, desktop[index + 1]["bbox"]["y"] - (section["bbox"]["y"] + section["bbox"]["height"]))
                for index, section in enumerate(desktop[:-1])
                if desktop[index + 1].get("bbox") and section.get("bbox")
            ],
        },
        "surfaceSequence": [section.get("style", {}).get("backgroundColor", "") for section in desktop],
        "imagery": {
            "count": sum(len(section.get("imageUrls", [])) for section in all_sections),
            "aspectRatios": aspect_ratios,
            "orientations": list(dict.fromkeys(orientations)),
            "character": [
                "dark" if sum(1 for value in luminance if value < 105) > len(luminance) / 2 else "light",
                "high-contrast" if any(value >= 32 for value in contrast) else "low-contrast",
            ],
        },
        "contrast": {
            "meanLuminance": round(sum(luminance) / len(luminance), 2) if luminance else 0,
            "meanStddevLuminance": round(sum(contrast) / len(contrast), 2) if contrast else 0,
        },
    }


def build_evidence(paths: Any, capture: dict[str, Any], source_type: str) -> dict[str, Any]:
    segmented = read_json(paths.sections_dir / "sections.segmented.json")
    mobile_segmented_path = paths.sections_dir / "sections.segmented.mobile.json"
    mobile_segmented = read_json(mobile_segmented_path) if mobile_segmented_path.is_file() else {"sections": []}
    extracted_path = paths.sections_dir / "sections.extracted.json"
    classified_path = paths.sections_dir / "sections.classified.json"
    matched_path = paths.sections_dir / "sections.matched.json"
    desktop_sections = read_json(extracted_path).get("sections", []) if extracted_path.is_file() else []
    classified = read_json(classified_path).get("sections", []) if classified_path.is_file() else []
    matched = read_json(matched_path).get("sections", []) if matched_path.is_file() else []
    classification_by_key = {section_key(section, index): section.get("classification", {}) for index, section in enumerate(classified, start=1)}
    matches_by_key = {section_key(section, index): section.get("patternCandidates", []) for index, section in enumerate(matched, start=1)}
    for section in desktop_sections:
        key = section_key(section, int(section.get("sectionIndex", 0) or 0))
        section["classification"] = classification_by_key.get(key, {})
        section["patternCandidates"] = matches_by_key.get(key, [])
    mobile_sections = extract_mobile_sections(mobile_segmented, capture["mobile"]["finalUrl"])

    combined = desktop_sections + mobile_sections
    ids = stable_evidence_ids(combined)
    key_to_id: dict[str, str] = {}
    for index, section in enumerate(combined, start=1):
        key = section_key(section, int(section.get("sectionIndex", index) or index))
        key_to_id.setdefault(key, ids.get(f"{key}:{index}", f"section-{key}"))

    viewport_records: dict[str, Any] = {}
    for viewport_name, sections, segmented_view in (
        ("desktop", desktop_sections, segmented),
        ("mobile", mobile_sections, mobile_segmented),
    ):
        records = []
        for index, section in enumerate(sections, start=1):
            key = section_key(section, int(section.get("sectionIndex", index) or index))
            records.append(
                section_evidence(section, viewport_name, key_to_id.get(key, f"section-{key}"), index)
            )
        viewport = dict(capture.get(viewport_name, {}).get("viewport", {}))
        viewport.update(segmented_view.get("viewport", {}))
        viewport_records[viewport_name] = {
            "width": viewport.get("width", 0),
            "height": viewport.get("height", 0),
            "pageWidth": viewport.get("pageWidth", 0),
            "pageHeight": viewport.get("pageHeight", 0),
            "screenshot": capture.get("artifacts", {}).get(f"{viewport_name}Screenshot", ""),
            "html": capture.get("artifacts", {}).get("html" if viewport_name == "desktop" else "mobileHtml", ""),
            "sections": records,
        }

    merged_sections: list[dict[str, Any]] = []
    seen: set[str] = set()
    for viewport_name in ("desktop", "mobile"):
        for record in viewport_records[viewport_name]["sections"]:
            if record["evidenceId"] in seen:
                continue
            seen.add(record["evidenceId"])
            merged = dict(record)
            merged["bboxes"] = {viewport_name: record.get("bbox", {})}
            merged["visibility"] = {viewport_name: record.get("visibility", {})}
            for other_viewport in ("desktop", "mobile"):
                if other_viewport == viewport_name:
                    continue
                other = next((item for item in viewport_records[other_viewport]["sections"] if item["evidenceId"] == record["evidenceId"]), None)
                if other:
                    merged["bboxes"][other_viewport] = other.get("bbox", {})
                    merged["visibility"][other_viewport] = other.get("visibility", {})
            merged.pop("bbox", None)
            merged["dominantSurfacesByViewport"] = {
                name: next(
                    (item.get("dominantSurfaces", []) for item in viewport_records[name]["sections"] if item["evidenceId"] == record["evidenceId"]),
                    [],
                )
                for name in ("desktop", "mobile")
            }
            merged["underlyingSurfaceByViewport"] = {
                name: next(
                    (item.get("underlyingSurface") for item in viewport_records[name]["sections"] if item["evidenceId"] == record["evidenceId"]),
                    None,
                )
                for name in ("desktop", "mobile")
            }
            merged["accentColorsByViewport"] = {
                name: next(
                    (item.get("accentColors", []) for item in viewport_records[name]["sections"] if item["evidenceId"] == record["evidenceId"]),
                    [],
                )
                for name in ("desktop", "mobile")
            }
            merged_sections.append(merged)

    return {
        "format": CONTRACT_FORMAT,
        "version": CONTRACT_VERSION,
        "source": {
            "url": capture.get("sourceUrl", ""),
            "finalUrl": capture.get("desktop", {}).get("finalUrl", ""),
            "capturedAt": capture.get("capturedAt", ""),
            "sourceType": source_type,
            "artifacts": capture.get("artifacts", {}),
        },
        "viewports": viewport_records,
        "sections": merged_sections,
        "documentSurfaces": {
            "desktop": segmented.get("documentSurfaces", {}),
            "mobile": mobile_segmented.get("documentSurfaces", {}),
        },
        "themeEvidence": aggregate_theme(
            desktop_sections,
            mobile_sections,
            [segmented.get("documentStyles", {}), mobile_segmented.get("documentStyles", {})],
            {
                "desktop": segmented.get("documentSurfaces", {}),
                "mobile": mobile_segmented.get("documentSurfaces", {}),
            },
        ),
        "semanticAuthority": "main-sitekit",
        "notes": [
            "This document reports observed source-site evidence only.",
            "Stable pattern decisions and runtime renderer mappings belong to Main SiteKit.",
        ],
    }


def main() -> int:
    args = parse_args()
    paths, capture = load_capture_metadata(args.url, site_slug=args.site_slug, page_slug=args.page_slug, output_dir=args.output)
    payload = build_evidence(paths, capture, args.source_type)
    output_path = paths.evidence_dir / "visual-evidence.v1.json"
    write_json(output_path, payload)
    print(output_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
