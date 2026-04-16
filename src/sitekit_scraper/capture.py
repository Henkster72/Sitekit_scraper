from __future__ import annotations

import argparse
from pathlib import Path
from typing import Any

from playwright.sync_api import Browser, Page, sync_playwright

from .utils import build_scrape_paths, ensure_scrape_paths, utc_timestamp, write_json, write_text


DEFAULT_TIMEOUT_MS = 60_000


def dismiss_basic_overlays(page: Page) -> None:
    selectors = [
        '[aria-label="Close"]',
        '[aria-label="close"]',
        'button:has-text("Accept")',
        'button:has-text("I Accept")',
        'button:has-text("Agree")',
        'button:has-text("Got it")',
        'button:has-text("Close")',
    ]
    for selector in selectors:
        locator = page.locator(selector)
        try:
            if locator.count() > 0 and locator.first.is_visible():
                locator.first.click(timeout=1200)
        except Exception:
            continue

    page.evaluate(
        """
        () => {
          const nodes = document.querySelectorAll('[id*="cookie"], [class*="cookie"], [class*="popup"], [class*="modal"]');
          for (const node of nodes) {
            const style = window.getComputedStyle(node);
            if (style.position === 'fixed' || style.position === 'sticky') {
              node.setAttribute('data-sitekit-hidden-overlay', 'true');
              node.style.display = 'none';
            }
          }
        }
        """
    )


def new_page(browser: Browser, width: int, height: int, mobile: bool = False) -> Page:
    return browser.new_page(
        viewport={"width": width, "height": height},
        is_mobile=mobile,
        device_scale_factor=1,
    )


def capture_view(
    browser: Browser,
    url: str,
    screenshot_path: Path,
    width: int,
    height: int,
    *,
    mobile: bool = False,
    dismiss_overlays: bool = True,
    timeout_ms: int = DEFAULT_TIMEOUT_MS,
) -> dict[str, Any]:
    page = new_page(browser, width, height, mobile=mobile)
    page.goto(url, wait_until="networkidle", timeout=timeout_ms)
    page.wait_for_timeout(1200)
    if dismiss_overlays:
        dismiss_basic_overlays(page)
        page.wait_for_timeout(300)
    page.screenshot(path=str(screenshot_path), full_page=True)
    html = page.content()
    metadata = {
        "title": page.title(),
        "finalUrl": page.url,
        "viewport": {"width": width, "height": height},
        "html": html,
    }
    page.close()
    return metadata


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Capture a Showit page into scraper artifacts.")
    parser.add_argument("--url", required=True, help="Public URL to capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    parser.add_argument("--desktop-width", type=int, default=1440)
    parser.add_argument("--desktop-height", type=int, default=2200)
    parser.add_argument("--mobile-width", type=int, default=430)
    parser.add_argument("--mobile-height", type=int, default=1600)
    parser.add_argument("--timeout-ms", type=int, default=DEFAULT_TIMEOUT_MS)
    parser.add_argument("--keep-overlays", action="store_true", help="Skip the basic overlay dismissal step.")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    paths = build_scrape_paths(args.url, site_slug=args.site_slug, page_slug=args.page_slug)
    ensure_scrape_paths(paths)

    desktop_png = paths.screenshots_full_dir / "desktop.png"
    mobile_png = paths.screenshots_full_dir / "mobile.png"
    html_path = paths.html_dir / "rendered.html"
    capture_metadata_path = paths.capture_dir / "capture.json"

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        desktop = capture_view(
            browser,
            args.url,
            desktop_png,
            args.desktop_width,
            args.desktop_height,
            dismiss_overlays=not args.keep_overlays,
            timeout_ms=args.timeout_ms,
        )
        mobile = capture_view(
            browser,
            args.url,
            mobile_png,
            args.mobile_width,
            args.mobile_height,
            mobile=True,
            dismiss_overlays=not args.keep_overlays,
            timeout_ms=args.timeout_ms,
        )
        browser.close()

    write_text(html_path, desktop.pop("html"))
    mobile.pop("html")

    payload = {
        "version": "0.1",
        "capturedAt": utc_timestamp(),
        "sourceUrl": args.url,
        "siteSlug": paths.site_slug,
        "pageSlug": paths.page_slug,
        "artifacts": {
            "html": str(html_path.relative_to(paths.page_dir.parent.parent.parent)),
            "desktopScreenshot": str(desktop_png.relative_to(paths.page_dir.parent.parent.parent)),
            "mobileScreenshot": str(mobile_png.relative_to(paths.page_dir.parent.parent.parent)),
        },
        "desktop": desktop,
        "mobile": mobile,
    }
    write_json(capture_metadata_path, payload)
    print(capture_metadata_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
