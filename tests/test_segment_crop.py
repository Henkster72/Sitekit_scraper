import tempfile
import unittest
from pathlib import Path

from PIL import Image

from src.sitekit_scraper.segment import crop_sections, normalize_crop_bbox


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


if __name__ == "__main__":
    unittest.main()
