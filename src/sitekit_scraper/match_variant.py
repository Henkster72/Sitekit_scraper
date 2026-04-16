from __future__ import annotations

import argparse
import math
import re
from typing import Any

from .utils import REFERENCE_LIBRARY_DIR, load_block_guide, load_capture_metadata, read_json, write_json


TOKEN_PATTERN = re.compile(r"[a-z0-9]+")
SOFTMAX_TEMPERATURE = 0.18
FORMISH_FIELDS = ("first name", "last name", "email address", "email", "phone", "message")

ROLE_TYPE_AFFINITY: dict[str, dict[str, float]] = {
    "header": {"header": 0.58},
    "footer": {"footer": 0.58},
    "hero": {"hero": 0.5, "content": 0.16, "cta": 0.12},
    "services": {"services": 0.46, "cta": 0.22, "cards": 0.18, "content": 0.12},
    "gallery": {"gallery": 0.38, "carousel": 0.34, "cta": 0.18, "cards": 0.18},
    "cards": {"cards": 0.46, "gallery": 0.18, "content": 0.12},
    "testimonial": {"testimonials": 0.52, "cards": 0.12, "content": 0.08},
    "cta": {"cta": 0.5, "content": 0.16, "contact": 0.12},
    "contact": {"contact": 0.5, "cta": 0.18, "content": 0.14},
    "content": {"content": 0.46, "cta": 0.22, "cards": 0.1},
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Match classified Showit sections to candidate SiteKit variants.")
    parser.add_argument("--url", required=True, help="Public URL used for capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    return parser.parse_args()


def library_variant_count() -> int:
    variants_path = REFERENCE_LIBRARY_DIR / "variants.json"
    if not variants_path.is_file():
        return 0
    payload = read_json(variants_path)
    return len(payload) if isinstance(payload, list) else 0


def slider_hints(section: dict[str, Any]) -> dict[str, int]:
    height = section["bbox"]["height"]
    text_length = section["stats"]["textLength"]
    return {
        "padding_top": max(36, min(120, int(height * 0.12))),
        "padding_bottom": max(36, min(120, int(height * 0.12))),
        "text_width": max(38, min(72, 70 - min(28, text_length // 60))),
    }


def normalized_phrase(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace("-", " ").strip().lower())


def text_tokens(value: str) -> set[str]:
    return {token for token in TOKEN_PATTERN.findall(normalized_phrase(value)) if len(token) > 2}


def unique_in_order(values: list[str]) -> list[str]:
    seen: set[str] = set()
    output: list[str] = []
    for value in values:
        normalized = normalized_phrase(value)
        if not normalized or normalized in seen:
            continue
        seen.add(normalized)
        output.append(normalized)
    return output


def section_text_blob(section: dict[str, Any]) -> str:
    parts = section.get("headings", []) + section.get("paragraphs", [])
    parts.extend([section.get("textSample", ""), section.get("rawText", "")])
    return normalized_phrase(" ".join(parts))


def formish_field_label_count(section: dict[str, Any]) -> int:
    text = section_text_blob(section)
    return sum(1 for label in FORMISH_FIELDS if label in text)


def looks_like_newsletter_form(section: dict[str, Any]) -> bool:
    stats = section["stats"]
    return stats["formFieldCount"] > 0 or (formish_field_label_count(section) >= 2 and stats["imageCount"] == 0)


def looks_like_promo_card(section: dict[str, Any]) -> bool:
    stats = section["stats"]
    visual = section.get("visual", {})
    bbox = section["bbox"]
    return stats["buttonCount"] >= 1 and stats["imageCount"] >= 1 and (visual.get("isDark") or bbox["height"] >= 780)


def looks_like_entry_link_hub(section: dict[str, Any]) -> bool:
    stats = section["stats"]
    return (
        stats["headingCount"] >= 2
        and stats["buttonCount"] >= 2
        and stats["textLength"] <= 140
        and 3 <= stats["imageCount"] <= 8
    )


def section_feature_phrases(section: dict[str, Any]) -> list[str]:
    classification = section["classification"]
    stats = section["stats"]
    style = section["style"]
    visual = section.get("visual", {})
    bbox = section["bbox"]
    block_slug = (section.get("blockSlug", "") or "").replace("-", " ")
    role = classification["role"]
    family = classification["patternFamily"].replace("-", " ")
    newsletter_form = looks_like_newsletter_form(section)
    promo_card = looks_like_promo_card(section)
    entry_link_hub = looks_like_entry_link_hub(section)

    phrases: list[str] = [role, family]
    if block_slug:
        phrases.append(block_slug)
    phrases.extend(classification.get("cues", []))

    if bbox["y"] < 220:
        phrases.append("top of page")
    if bbox["height"] >= 720:
        phrases.append("tall section")
    if stats["imageCount"] >= 1:
        phrases.append("image first")
    if stats["imageCount"] >= 6:
        phrases.extend(["image heavy", "social grid", "portrait rhythm"])
    elif stats["imageCount"] >= 3:
        phrases.append("lead image first")
    if stats["repeatedItemCount"] >= 3:
        phrases.extend(["repeated items", "list"])
    if stats["buttonCount"] >= 2:
        phrases.append("stacked actions")
    if style.get("backgroundImage"):
        phrases.extend(["background image", "full bleed background image"])
    if visual.get("isDark"):
        phrases.append("dark stage")
    if visual.get("orientation") == "portrait":
        phrases.append("portrait rhythm")
    if promo_card:
        phrases.extend(["overlay card", "centered launch card"])
    if entry_link_hub:
        phrases.extend(["editorial callout", "editorial two panel callout"])

    if role == "hero":
        phrases.extend(["cover style hero", "editorial hero"])
        if stats["buttonCount"] <= 2:
            phrases.append("centered overlay hero")
        if visual.get("isDark") and bbox["height"] >= 1200:
            phrases.append("full bleed editorial hero with centered overlay copy")
    elif role == "header":
        phrases.extend(["site navigation", "editorial masthead"])
        if stats["linkCount"] >= 6:
            phrases.append("split nav around brand")
    elif role == "footer":
        phrases.extend(["dense editorial footer", "editorial footer"])
    elif role == "content":
        if stats["imageCount"] >= 1 and stats["paragraphCount"] >= 2:
            phrases.append("alternating image plus text rows")
        if stats["imageCount"] >= 2:
            phrases.append("editorial split")
        if stats["imageCount"] >= 2 and stats["buttonCount"] >= 2:
            phrases.extend(["triptych links", "image link image editorial split"])
        if stats["imageCount"] == 0 and stats["buttonCount"] == 0 and stats["headingCount"] >= 1 and stats["paragraphCount"] <= 2:
            phrases.append("centered minimal callout")
    elif role == "services":
        if stats["buttonCount"] >= 3 or stats["repeatedItemCount"] >= 3:
            phrases.extend(["ticket strip list", "service list"])
    elif role == "cards":
        phrases.extend(["lead story", "supporting cards"])
        if stats["imageCount"] <= 2:
            phrases.append("balanced default layout")
    elif role == "gallery":
        if stats["imageCount"] >= 8 and stats["buttonCount"] >= 1 and visual.get("isDark"):
            phrases.extend(["two band filmstrip", "centered overlay"])
        if stats["imageCount"] >= 6:
            phrases.extend(["editorial collage", "social grid"])
        if entry_link_hub:
            phrases.append("editorial two panel callout")
    elif role == "testimonial":
        phrases.extend(["review stage", "review strip"])
    elif role == "cta":
        if newsletter_form:
            phrases.append("newsletter split")
        if promo_card:
            phrases.extend(["overlay card", "centered launch card"])
        if stats["buttonCount"] >= 2 or entry_link_hub:
            phrases.append("editorial CTA")
        if entry_link_hub:
            phrases.append("editorial two panel callout")
    elif role == "contact":
        phrases.extend(["contact conversion band", "form first"])

    return unique_in_order(phrases)


def section_feature_tokens(section: dict[str, Any], phrases: list[str]) -> set[str]:
    tokens = set()
    for phrase in phrases:
        tokens |= text_tokens(phrase)
    return tokens


def candidate_types_for_role(role: str) -> list[str]:
    affinity = ROLE_TYPE_AFFINITY.get(role, {"content": 0.4})
    return list(affinity.keys())


def candidate_variants(guide: dict[str, Any], role: str) -> list[dict[str, Any]]:
    blocks = guide.get("blocks", {})
    output: list[dict[str, Any]] = []
    for block_type in candidate_types_for_role(role):
        block = blocks.get(block_type)
        if not isinstance(block, dict):
            continue
        for variant in block.get("variants", []):
            base = {
                "type": block_type,
                "variant": variant["id"],
                "summary": variant.get("summary", ""),
                "useWhen": variant.get("useWhen", []),
                "builderEditable": bool(variant.get("builderEditable", False)),
            }
            if block_type == "header" and isinstance(variant.get("navLayout"), list):
                navs = variant["navLayout"]
                if "default" in navs:
                    output.append(dict(base))
                for nav_layout in navs:
                    if nav_layout == "default":
                        continue
                    item = dict(base)
                    item["navLayout"] = nav_layout
                    output.append(item)
                continue
            if isinstance(variant.get("layoutMode"), list):
                modes = variant["layoutMode"]
                if "default" in modes:
                    output.append(dict(base))
                for layout_mode in modes:
                    if layout_mode == "default":
                        continue
                    item = dict(base)
                    item["layoutMode"] = layout_mode
                    output.append(item)
                continue
            output.append(dict(base))
    return output


def guide_phrase_matches(candidate: dict[str, Any], section_phrases: list[str], section_tokens: set[str]) -> tuple[float, list[str], list[str]]:
    guide_phrases = unique_in_order(
        [candidate.get("summary", "")] + list(candidate.get("useWhen", []))
    )
    exact_matches = [phrase for phrase in guide_phrases if phrase in section_phrases]
    guide_tokens: set[str] = set()
    for phrase in guide_phrases:
        guide_tokens |= text_tokens(phrase)
    token_matches = sorted(guide_tokens & section_tokens)

    bonus = min(0.32, 0.11 * len(exact_matches)) + min(0.16, 0.025 * len(token_matches))
    return bonus, exact_matches, token_matches


def structural_bonus(section: dict[str, Any], candidate: dict[str, Any], section_phrases: list[str]) -> tuple[float, list[str]]:
    stats = section["stats"]
    visual = section.get("visual", {})
    bbox = section["bbox"]
    role = section["classification"]["role"]
    block_type = candidate["type"]
    variant = candidate["variant"]
    layout_mode = candidate.get("layoutMode", "")
    nav_layout = candidate.get("navLayout", "")
    block_slug = section.get("blockSlug", "")
    newsletter_form = looks_like_newsletter_form(section)
    promo_card = looks_like_promo_card(section)
    entry_link_hub = looks_like_entry_link_hub(section)

    bonus = 0.0
    reasons: list[str] = []

    if block_type == "header":
        if variant == 6 and nav_layout == "split-brand-center" and stats["linkCount"] >= 6:
            bonus += 0.2
            reasons.append("split navigation structure")
    elif block_type == "footer":
        if variant == 8 and stats["linkCount"] >= 8:
            bonus += 0.2
            reasons.append("dense footer navigation")
    elif block_type == "hero":
        if bbox["y"] < 220 and bbox["height"] >= 650:
            bonus += 0.08
            reasons.append("hero occupies the top fold")
        if variant == 5 and "cover style hero" in section_phrases:
            bonus += 0.2
            reasons.append("cover-style hero evidence")
        if variant == 5 and visual.get("isDark") and bbox["height"] >= 1200:
            bonus += 0.1
            reasons.append("dark full-bleed hero evidence")
        if variant == 3 and layout_mode == "title-overlay" and ("full bleed background image" in section_phrases or section["style"].get("backgroundImage") or visual.get("isDark")):
            bonus += 0.14
            reasons.append("overlay hero evidence")
        if variant == 1 and stats["paragraphCount"] >= 1 and stats["buttonCount"] >= 1:
            bonus += 0.08
            reasons.append("split hero fallback evidence")
    elif block_type == "content":
        if variant == 2 and ("triptych links" in section_phrases or "image link image editorial split" in section_phrases):
            bonus += 0.22
            reasons.append("editorial triptych evidence")
        if variant == 1 and stats["paragraphCount"] >= 2 and stats["imageCount"] >= 1:
            bonus += 0.12
            reasons.append("long-form content evidence")
    elif block_type == "services":
        if variant == 8 and ("ticket strip list" in section_phrases or stats["buttonCount"] >= 3):
            bonus += 0.2
            reasons.append("service list/ticket-strip evidence")
        if variant == 1 and stats["imageCount"] >= 1:
            bonus += 0.08
            reasons.append("card-like services fallback")
    elif block_type == "cards":
        if variant == 8 and "lead story" in section_phrases and (stats["imageCount"] >= 4 or any(token in block_slug for token in {"featured", "project"})):
            bonus += 0.22
            reasons.append("lead-story card evidence")
        if variant == 1 and (stats["repeatedItemCount"] >= 2 or stats["imageCount"] <= 2):
            bonus += 0.18
            reasons.append("balanced card grid evidence")
    elif block_type == "gallery":
        if layout_mode == "social-grid" and stats["imageCount"] >= 6:
            bonus += 0.2
            reasons.append("social grid image density")
        if layout_mode == "editorial-collage" and stats["imageCount"] >= 4:
            bonus += 0.12
            reasons.append("editorial collage evidence")
        if variant == 4 and 2 <= stats["imageCount"] <= 5:
            bonus += 0.12
            reasons.append("lead-image gallery evidence")
    elif block_type == "carousel":
        if stats["imageCount"] >= 8:
            bonus += 0.16
            reasons.append("many-slide section")
        if stats["buttonCount"] >= 1:
            bonus += 0.06
            reasons.append("carousel CTA evidence")
        if visual.get("isDark"):
            bonus += 0.08
            reasons.append("dark stage styling")
        if variant == 2 and "two band filmstrip" in section_phrases:
            bonus += 0.22
            reasons.append("two-band filmstrip evidence")
    elif block_type == "testimonials":
        if variant == 8 and "review stage" in section_phrases:
            bonus += 0.2
            reasons.append("review-stage testimonial evidence")
        if variant == 2 and stats["paragraphCount"] <= 2:
            bonus += 0.08
            reasons.append("pull-quote testimonial fallback")
    elif block_type == "cta":
        if variant == 6 and layout_mode == "newsletter-split" and newsletter_form:
            bonus += 0.18
            reasons.append("newsletter split evidence")
        if variant == 6 and layout_mode == "overlay-card" and promo_card:
            bonus += 0.24
            reasons.append("overlay-card CTA evidence")
        if variant == 8 and ("editorial two panel callout" in section_phrases or entry_link_hub or (1 <= stats["buttonCount"] <= 4 and stats["textLength"] <= 220)):
            bonus += 0.22
            reasons.append("editorial callout CTA evidence")
        if variant == 8 and stats["buttonCount"] >= 2:
            bonus += 0.14
            reasons.append("multi-action CTA evidence")
        if variant == 3 and stats["buttonCount"] == 0 and stats["paragraphCount"] <= 2:
            bonus += 0.18
            reasons.append("minimal CTA evidence")
    elif block_type == "contact":
        if role == "contact" and stats["formFieldCount"] > 0:
            bonus += 0.18
            reasons.append("contact form evidence")

    return bonus, reasons


def penalty_adjustment(section: dict[str, Any], candidate: dict[str, Any], section_phrases: list[str]) -> tuple[float, list[str]]:
    stats = section["stats"]
    block_slug = section.get("blockSlug", "")
    visual = section.get("visual", {})
    block_type = candidate["type"]
    variant = candidate["variant"]
    layout_mode = candidate.get("layoutMode", "")
    newsletter_form = looks_like_newsletter_form(section)
    promo_card = looks_like_promo_card(section)

    penalty = 0.0
    reasons: list[str] = []

    if block_type == "gallery" and layout_mode == "social-grid" and stats["imageCount"] < 6:
        penalty -= 0.12
        reasons.append("not enough images for social-grid")
    if block_type == "gallery" and layout_mode == "social-grid" and visual.get("isDark") and stats["buttonCount"] >= 1 and stats["imageCount"] >= 8:
        penalty -= 0.18
        reasons.append("looks more like a dark filmstrip than a social grid")
    if block_type == "carousel" and stats["imageCount"] < 5:
        penalty -= 0.14
        reasons.append("not enough images for carousel")
    if block_type == "cta" and layout_mode == "newsletter-split" and "newsletter" not in block_slug and stats["formFieldCount"] == 0 and stats["textLength"] > 220:
        penalty -= 0.12
        reasons.append("looks more like a promo CTA than a signup split")
    if block_type == "cta" and layout_mode == "newsletter-split" and not newsletter_form and promo_card:
        penalty -= 0.18
        reasons.append("promo-card section without form evidence")
    if block_type == "cta" and layout_mode == "overlay-card" and newsletter_form and stats["imageCount"] == 0:
        penalty -= 0.12
        reasons.append("form-first section without image backdrop")
    if block_type == "content" and variant == 2 and stats["imageCount"] < 2:
        penalty -= 0.12
        reasons.append("insufficient media for editorial triptych")
    if block_type == "content" and variant == 1 and stats["imageCount"] == 0:
        penalty -= 0.12
        reasons.append("needs media for alternating image/text rows")
    if block_type == "content" and variant == 7 and stats["imageCount"] == 0:
        penalty -= 0.12
        reasons.append("needs media for stacked story cards")
    if block_type == "content" and variant == 1 and stats["buttonCount"] >= 2 and stats["imageCount"] >= 1 and stats["textLength"] <= 320:
        penalty -= 0.12
        reasons.append("behaves more like a conversion band than long-form content")
    if block_type == "hero" and variant == 3 and layout_mode == "title-overlay" and (stats["paragraphCount"] >= 4 or stats["textLength"] > 320):
        penalty -= 0.12
        reasons.append("too content-heavy for a simple title-overlay hero")
    if block_type == "cards" and variant == 8 and stats["imageCount"] <= 2:
        penalty -= 0.18
        reasons.append("not enough media for a lead-story card spread")

    return penalty, reasons


def score_candidate(section: dict[str, Any], candidate: dict[str, Any], section_phrases: list[str], section_tokens: set[str]) -> dict[str, Any]:
    classification = section["classification"]
    role = classification["role"]
    affinity = ROLE_TYPE_AFFINITY.get(role, {})
    type_affinity = affinity.get(candidate["type"], 0.0)

    score = type_affinity
    evidence: list[str] = []
    matched_terms: list[str] = []

    if type_affinity > 0:
        evidence.append(f"role/type affinity {type_affinity:.2f}")

    if candidate["type"] == classification.get("blockFamilyHint"):
        score += 0.1
        evidence.append("matches classified block family")

    phrase_bonus, phrase_matches, token_matches = guide_phrase_matches(candidate, section_phrases, section_tokens)
    if phrase_bonus > 0:
        score += phrase_bonus
        matched_terms.extend(phrase_matches)
        evidence.append(f"guide phrase overlap {phrase_bonus:.2f}")
    if token_matches:
        evidence.append("guide token matches: " + ", ".join(token_matches[:5]))

    structure_bonus_value, structure_reasons = structural_bonus(section, candidate, section_phrases)
    if structure_bonus_value > 0:
        score += structure_bonus_value
        evidence.extend(structure_reasons)

    penalty_value, penalty_reasons = penalty_adjustment(section, candidate, section_phrases)
    if penalty_value != 0:
        score += penalty_value
        evidence.extend(penalty_reasons)

    result = {
        "type": candidate["type"],
        "variant": candidate["variant"],
        "score": round(max(score, 0.0), 4),
        "matchedGuideTerms": matched_terms,
        "evidence": evidence,
        "reason": evidence[0] if evidence else "fallback candidate",
    }
    if "layoutMode" in candidate:
        result["layoutMode"] = candidate["layoutMode"]
    if "navLayout" in candidate:
        result["navLayout"] = candidate["navLayout"]
    return result


def apply_confidences(candidates: list[dict[str, Any]]) -> list[dict[str, Any]]:
    if not candidates:
        return []
    scaled_weights = [math.exp(candidate["score"] / SOFTMAX_TEMPERATURE) for candidate in candidates]
    total = sum(scaled_weights) or 1.0
    for candidate, weight in zip(candidates, scaled_weights):
        candidate["confidence"] = round(weight / total, 4)
    return candidates


def rank_matches(section: dict[str, Any], guide: dict[str, Any]) -> list[dict[str, Any]]:
    section_phrases = section_feature_phrases(section)
    section_tokens = section_feature_tokens(section, section_phrases)
    scored = [
        score_candidate(section, candidate, section_phrases, section_tokens)
        for candidate in candidate_variants(guide, section["classification"]["role"])
    ]
    scored = apply_confidences(sorted(scored, key=lambda item: (-item["score"], item["type"], item["variant"])))
    return scored[:3]


def main() -> int:
    args = parse_args()
    paths, capture_metadata = load_capture_metadata(args.url, site_slug=args.site_slug, page_slug=args.page_slug)
    classified = read_json(paths.sections_dir / "sections.classified.json")
    guide = load_block_guide()
    sections = []
    for section in classified["sections"]:
        enriched = dict(section)
        enriched["variantMatches"] = rank_matches(section, guide)
        enriched["sliders"] = slider_hints(section)
        enriched["referenceLibraryVariantCount"] = library_variant_count()
        sections.append(enriched)

    payload = {
        "version": "0.1",
        "sourceUrl": capture_metadata["sourceUrl"],
        "finalUrl": capture_metadata["desktop"]["finalUrl"],
        "siteSlug": paths.site_slug,
        "pageSlug": paths.page_slug,
        "sections": sections,
    }
    output_path = paths.sections_dir / "sections.matched.json"
    write_json(output_path, payload)
    print(output_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
