import tempfile
import unittest
from pathlib import Path

from PIL import Image

from src.sitekit_scraper.segment import (
    crop_sections,
    normalize_crop_bbox,
    select_dominant_surfaces,
    select_visual_candidates,
)
from src.sitekit_scraper.visual_evidence import section_evidence


class CropNormalizationTests(unittest.TestCase):
    image_size = (100, 100)

    def test_normal_bbox(self):
        self.assertEqual(normalize_crop_bbox({"x": 10, "y": 20, "width": 30, "height": 40}, self.image_size), ((10, 20, 40, 60), ""))

    def test_negative_position_is_clamped(self):
        self.assertEqual(normalize_crop_bbox({"x": -10, "y": -5, "width": 30, "height": 20}, self.image_size), ((0, 0, 20, 15), ""))

    def test_larger_bbox_is_clamped(self):
        self.assertEqual(normalize_crop_bbox({"x": -10, "y": -10, "width": 200, "height": 200}, self.image_size), ((0, 0, 100, 100), ""))

    def test_negative_dimensions_are_ordered(self):
        self.assertEqual(normalize_crop_bbox({"x": 100, "y": 100, "width": -30, "height": -40}, self.image_size), ((70, 60, 100, 100), ""))

    def test_explicit_inverted_edges_are_ordered(self):
        self.assertEqual(normalize_crop_bbox({"x": 0, "y": 0, "left": 80, "right": 20, "top": 90, "bottom": 10}, self.image_size), ((20, 10, 80, 90), ""))

    def test_fractional_coordinates_use_floor_and_ceil(self):
        self.assertEqual(normalize_crop_bbox({"x": 1.2, "y": 2.1, "width": 3.1, "height": 4.1}, self.image_size), ((1, 2, 5, 7), ""))

    def test_huge_transformed_bbox_is_clamped(self):
        self.assertEqual(normalize_crop_bbox({"x": -1e9, "y": -1e9, "width": 2e9, "height": 2e9}, self.image_size), ((0, 0, 100, 100), ""))

    def test_completely_outside_bbox_is_skipped(self):
        rect, reason = normalize_crop_bbox({"x": 200, "y": 200, "width": 10, "height": 10}, self.image_size)
        self.assertIsNone(rect)
        self.assertEqual(reason, "outside-screenshot")

    def test_partially_visible_bbox_survives(self):
        self.assertEqual(normalize_crop_bbox({"x": -10, "y": 90, "width": 30, "height": 30}, self.image_size), ((0, 90, 20, 100), ""))

    def test_zero_and_sliver_bboxes_are_skipped(self):
        for bbox, reason in [
            ({"x": 0, "y": 0, "width": 0, "height": 20}, "zero-width"),
            ({"x": 0, "y": 0, "width": 20, "height": 0}, "zero-height"),
            ({"x": 0.1, "y": 0.1, "width": 0.2, "height": 0.2}, "too-small-after-clamping"),
        ]:
            rect, actual_reason = normalize_crop_bbox(bbox, self.image_size)
            self.assertIsNone(rect)
            self.assertEqual(actual_reason, reason)


class CropSectionTests(unittest.TestCase):
    def test_invalid_section_does_not_crash_or_disappear(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            screenshot = root / "full.png"
            output = root / "sections"
            output.mkdir()
            Image.new("RGB", (100, 100), "white").save(screenshot)
            candidates = [
                {"tag": "section", "bbox": {"x": 200, "y": 200, "width": 10, "height": 10}},
                {"tag": "section", "bbox": {"x": 10, "y": 10, "width": 20, "height": 20}},
            ]

            crop_sections(screenshot, candidates, output, root)

            self.assertEqual(candidates[0]["cropStatus"], "skipped")
            self.assertEqual(candidates[0]["cropReason"], "outside-screenshot")
            self.assertEqual(candidates[0]["cropPath"], "")
            self.assertEqual(candidates[0]["sectionIndex"], 1)
            self.assertEqual(candidates[1]["cropStatus"], "written")
            self.assertTrue(candidates[1]["cropPath"])
            self.assertEqual(len(list(output.glob("*.png"))), 1)


def surface(
    color: str,
    x: int,
    y: int,
    width: int,
    height: int,
    *,
    source_kind: str = "descendant-background",
    visible: bool = True,
) -> dict:
    return {
        "color": color,
        "sourceKind": source_kind,
        "bbox": {"x": x, "y": y, "width": width, "height": height},
        "backgroundImage": "",
        "gradientStops": [],
        "visible": visible,
    }


class DominantSurfaceTests(unittest.TestCase):
    section = {"x": 0, "y": 0, "width": 1200, "height": 900}

    def test_large_colored_child_is_kept(self):
        result = select_dominant_surfaces([surface("#2a0831", 0, 0, 400, 900)], self.section)
        self.assertEqual([item["color"] for item in result], ["#2a0831"])
        self.assertEqual(result[0]["coverage"], 0.3333)

    def test_sequential_peer_surfaces_are_kept_separately(self):
        candidates = [
            surface("#2a0831", 0, 0, 400, 900),
            surface("#003500", 400, 0, 400, 900),
            surface("#1a1423", 800, 0, 400, 900),
        ]
        self.assertEqual(
            [item["color"] for item in select_dominant_surfaces(candidates, self.section)],
            ["#2a0831", "#003500", "#1a1423"],
        )

    def test_tiny_colored_control_is_excluded(self):
        self.assertEqual(select_dominant_surfaces([surface("#ffbd89", 20, 20, 120, 40)], self.section), [])

    def test_thin_separator_is_excluded(self):
        self.assertEqual(select_dominant_surfaces([surface("#ffffff", 0, 0, 1200, 1)], self.section), [])

    def test_hidden_colored_surface_is_excluded(self):
        self.assertEqual(
            select_dominant_surfaces([surface("#ffbd89", 0, 0, 1200, 900, visible=False)], self.section),
            [],
        )

    def test_nested_same_color_wrappers_are_deduplicated(self):
        candidates = [
            surface("#003500", 0, 0, 600, 900),
            surface("#003500", 20, 20, 560, 860),
        ]
        result = select_dominant_surfaces(candidates, self.section)
        self.assertEqual(len(result), 1)
        self.assertEqual(result[0]["bbox"]["width"], 600)

    def test_absolute_colored_layer_is_kept(self):
        result = select_dominant_surfaces(
            [surface("#8ce3e2", 0, 0, 1200, 900, source_kind="decorative-layer")], self.section
        )
        self.assertEqual(result[0]["sourceKind"], "decorative-layer")

    def test_overlapping_different_color_layers_are_both_kept(self):
        candidates = [
            surface("#8ce3e2", 0, 0, 1200, 900, source_kind="decorative-layer"),
            surface("#ffd3f2", 0, 0, 1200, 900, source_kind="decorative-layer"),
        ]
        self.assertEqual(
            [item["color"] for item in select_dominant_surfaces(candidates, self.section)],
            ["#8ce3e2", "#ffd3f2"],
        )

    def test_media_surfaces_are_excluded(self):
        self.assertEqual(
            select_dominant_surfaces([surface("#203886", 0, 0, 1200, 900, source_kind="media")], self.section),
            [],
        )
        self.assertEqual(
            select_dominant_surfaces(
                [surface("", 0, 0, 1200, 900, source_kind="media-background")], self.section
            ),
            [],
        )

    def test_transparent_root_keeps_underlying_surface_separate(self):
        evidence = section_evidence(
            {
                "sectionId": "work",
                "style": {"backgroundColor": "rgba(0, 0, 0, 0)", "backgroundImage": "none"},
                "underlyingSurface": surface("#203886", 0, 0, 1200, 900, source_kind="document-background"),
            },
            "desktop",
            "section-work",
            1,
        )
        self.assertEqual(evidence["background"]["color"], "rgba(0, 0, 0, 0)")
        self.assertEqual(evidence["underlyingSurface"]["color"], "#203886")


def stage_candidate(
    name: str,
    tag: str,
    x: int,
    y: int,
    width: int,
    height: int,
    *,
    local_text: int = 120,
    headings: int = 1,
    media: int = 0,
    background: str = "rgb(240, 240, 240)",
    section_kind: str = "page",
) -> dict:
    return {
        "domPath": name,
        "tag": tag,
        "bbox": {"x": x, "y": y, "width": width, "height": height},
        "textLength": local_text,
        "localTextLength": local_text,
        "localHeadingCount": headings,
        "localParagraphCount": 1 if local_text else 0,
        "localLinkCount": 1 if local_text else 0,
        "localButtonCount": 0,
        "localMediaCount": media,
        "backgroundColor": background,
        "backgroundImage": "none",
        "sectionKind": section_kind,
        "overlayReason": "" if section_kind == "page" else "fixed-overlay-semantics",
    }


class CandidateSelectionTests(unittest.TestCase):
    viewport = {"width": 1440, "height": 900, "pageWidth": 1440, "pageHeight": 3000}

    def test_full_page_wrapper_is_replaced_by_meaningful_child_stages(self):
        wrapper = stage_candidate("div.page-wrapper", "div", 0, 0, 1440, 3000, local_text=0, headings=0)
        children = [
            stage_candidate(f"section.stage-{index}", "section", 0, index * 600, 1440, 600, media=1)
            for index in range(5)
        ]

        selected, overlays, diagnostics = select_visual_candidates([wrapper, *children], self.viewport)

        self.assertEqual([candidate["domPath"] for candidate in selected], [candidate["domPath"] for candidate in children])
        self.assertFalse(overlays)
        self.assertEqual(len(diagnostics["suppressedWrappers"]), 1)
        self.assertEqual(diagnostics["suppressedWrappers"][0]["domPath"], "div.page-wrapper")

    def test_single_hero_with_layout_children_is_not_decomposed(self):
        hero = stage_candidate("section.hero", "section", 0, 0, 1440, 900, local_text=180, headings=1, media=1)
        selected, overlays, diagnostics = select_visual_candidates([hero], self.viewport)

        self.assertEqual([candidate["domPath"] for candidate in selected], ["section.hero"])
        self.assertFalse(overlays)
        self.assertFalse(diagnostics["suppressedWrappers"])

    def test_card_grid_candidate_remains_one_stage(self):
        grid = stage_candidate("section.cards", "section", 0, 0, 1440, 600, local_text=120, headings=1, media=6)
        cards = [
            stage_candidate(f"div.card-{index}", "div", index * 240, 120, 220, 280, local_text=50, headings=1, media=1)
            for index in range(3)
        ]

        selected, overlays, diagnostics = select_visual_candidates([grid, *cards], self.viewport)

        self.assertIn("section.cards", [candidate["domPath"] for candidate in selected])
        self.assertFalse(diagnostics["suppressedWrappers"])

    def test_fixed_overlay_is_not_a_primary_page_section(self):
        page = stage_candidate("section.page", "section", 0, 0, 1440, 1200, media=1)
        overlay = stage_candidate(
            "div.consent-dialog",
            "div",
            0,
            0,
            1440,
            900,
            local_text=180,
            headings=1,
            section_kind="overlay",
        )

        selected, overlays, diagnostics = select_visual_candidates([page, overlay], self.viewport)

        self.assertEqual([candidate["domPath"] for candidate in selected], ["section.page"])
        self.assertEqual([candidate["domPath"] for candidate in overlays], ["div.consent-dialog"])
        self.assertEqual(diagnostics["overlayCount"], 1)

    def test_nested_footer_duplicate_is_suppressed(self):
        footer = stage_candidate("footer#site", "footer", 0, 2700, 1440, 300, local_text=120)
        nested = stage_candidate("footer#site > section", "section", 0, 2700, 1400, 180, local_text=80)

        selected, overlays, diagnostics = select_visual_candidates([footer, nested], self.viewport)

        self.assertEqual([candidate["domPath"] for candidate in selected], ["footer#site"])
        self.assertEqual(len(diagnostics["suppressedContainedDuplicates"]), 1)

    def test_header_and_footer_survive_wrapper_suppression(self):
        wrapper = stage_candidate("div.builder-wrapper", "div", 0, 0, 1440, 3000, local_text=0, headings=0)
        header = stage_candidate("header#site", "header", 0, 0, 1440, 120, local_text=80)
        body = stage_candidate("section.body", "section", 0, 120, 1440, 2700, local_text=600, headings=2, media=2)
        footer = stage_candidate("footer#site", "footer", 0, 2820, 1440, 180, local_text=80)

        selected, overlays, diagnostics = select_visual_candidates([wrapper, header, body, footer], self.viewport)

        self.assertEqual(
            [candidate["domPath"] for candidate in selected],
            ["header#site", "section.body", "footer#site"],
        )
        self.assertEqual(len(diagnostics["suppressedWrappers"]), 1)


if __name__ == "__main__":
    unittest.main()
