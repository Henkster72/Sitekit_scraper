from __future__ import annotations

import json
import re
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from urllib.parse import urljoin, urlparse


PROJECT_ROOT = Path(__file__).resolve().parents[2]
DATA_DIR = PROJECT_ROOT / "data"
SCRAPES_DIR = DATA_DIR / "scrapes"
REFERENCE_LIBRARY_DIR = PROJECT_ROOT / "reference_library"
BLOCK_GUIDE_PATH = PROJECT_ROOT / "docs" / "specs" / "sitekit-block-variant-guide-v1.json"


def slugify(value: str, default: str = "item") -> str:
    text = value.strip().lower()
    text = re.sub(r"https?://", "", text)
    text = re.sub(r"[^a-z0-9]+", "-", text)
    text = re.sub(r"-{2,}", "-", text)
    text = text.strip("-")
    return text or default


def site_slug_from_url(url: str) -> str:
    parsed = urlparse(url)
    host = parsed.netloc or "site"
    return slugify(host.replace(".", "-"), default="site")


def page_slug_from_url(url: str) -> str:
    parsed = urlparse(url)
    path = (parsed.path or "/").strip("/")
    if not path:
        return "home"
    return slugify(path.replace("/", "-"), default="page")


@dataclass(frozen=True)
class ScrapePaths:
    site_slug: str
    page_slug: str
    page_dir: Path
    capture_dir: Path
    html_dir: Path
    screenshots_full_dir: Path
    screenshots_sections_dir: Path
    sections_dir: Path
    mapper_dir: Path
    sitekit_dir: Path
    logs_dir: Path


def build_scrape_paths(url: str, site_slug: str | None = None, page_slug: str | None = None) -> ScrapePaths:
    resolved_site_slug = slugify(site_slug or site_slug_from_url(url), default="site")
    resolved_page_slug = slugify(page_slug or page_slug_from_url(url), default="page")
    page_dir = SCRAPES_DIR / resolved_site_slug / resolved_page_slug
    return ScrapePaths(
        site_slug=resolved_site_slug,
        page_slug=resolved_page_slug,
        page_dir=page_dir,
        capture_dir=page_dir / "capture",
        html_dir=page_dir / "html",
        screenshots_full_dir=page_dir / "screenshots" / "full",
        screenshots_sections_dir=page_dir / "screenshots" / "sections",
        sections_dir=page_dir / "sections",
        mapper_dir=page_dir / "mapper",
        sitekit_dir=page_dir / "sitekit",
        logs_dir=page_dir / "logs",
    )


def ensure_scrape_paths(paths: ScrapePaths) -> None:
    for directory in (
        paths.page_dir,
        paths.capture_dir,
        paths.html_dir,
        paths.screenshots_full_dir,
        paths.screenshots_sections_dir,
        paths.sections_dir,
        paths.mapper_dir,
        paths.sitekit_dir,
        paths.logs_dir,
    ):
        directory.mkdir(parents=True, exist_ok=True)


def read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8"))


def write_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    text = json.dumps(payload, indent=2, ensure_ascii=False)
    path.write_text(text + "\n", encoding="utf-8")


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def utc_timestamp() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def normalise_whitespace(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace("\xa0", " ")).strip()


def dedupe_strings(values: list[str]) -> list[str]:
    seen: set[str] = set()
    output: list[str] = []
    for value in values:
        text = normalise_whitespace(value)
        if not text or text in seen:
            continue
        seen.add(text)
        output.append(text)
    return output


def resolve_url(url: str, base_url: str) -> str:
    value = url.strip()
    if not value:
        return ""
    return urljoin(base_url, value)


def same_origin_relative(url: str, base_url: str) -> str:
    parsed_url = urlparse(url)
    parsed_base = urlparse(base_url)
    if not parsed_url.netloc or parsed_url.netloc != parsed_base.netloc:
        return url
    path = parsed_url.path or "/"
    if parsed_url.query:
        path += f"?{parsed_url.query}"
    if parsed_url.fragment:
        path += f"#{parsed_url.fragment}"
    return path


def load_block_guide() -> dict[str, Any]:
    return read_json(BLOCK_GUIDE_PATH)


def load_capture_metadata(url: str, site_slug: str | None = None, page_slug: str | None = None) -> tuple[ScrapePaths, dict[str, Any]]:
    paths = build_scrape_paths(url, site_slug=site_slug, page_slug=page_slug)
    metadata_path = paths.capture_dir / "capture.json"
    if not metadata_path.is_file():
        raise FileNotFoundError(f"Capture metadata not found: {metadata_path}")
    return paths, read_json(metadata_path)
