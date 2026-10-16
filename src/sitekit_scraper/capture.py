from __future__ import annotations

import argparse
from pathlib import Path
from typing import Any
from urllib.request import Request, urlopen

from playwright.sync_api import Browser, Page, TimeoutError as PlaywrightTimeoutError, sync_playwright

from .utils import build_scrape_paths, ensure_scrape_paths, utc_timestamp, write_json, write_text


DEFAULT_TIMEOUT_MS = 60_000


def wait_for_render_ready(page: Page, timeout_ms: int = 5_000) -> None:
    """Wait briefly for the rendered layout to become usable for evidence."""
    bounded_timeout = max(500, min(timeout_ms, 5_000))
    try:
        page.wait_for_function("() => document.readyState === 'complete'", timeout=bounded_timeout)
    except Exception:
        pass
    try:
        page.wait_for_function(
            """() => Array.from(document.querySelectorAll('link[rel="stylesheet"]')).every((link) =>
                link.disabled || Boolean(link.sheet) || !link.href
            )""",
            timeout=bounded_timeout,
        )
    except Exception:
        pass
    try:
        page.evaluate(
            """() => Promise.race([
                document.fonts?.ready || Promise.resolve(),
                new Promise((resolve) => setTimeout(resolve, 1500)),
            ])"""
        )
    except Exception:
        pass
    page.wait_for_timeout(250)
    try:
        page.wait_for_function(
            """() => {
                const body = document.body;
                const root = document.documentElement;
                const current = [
                    body?.scrollWidth || 0,
                    body?.scrollHeight || 0,
                    root.scrollWidth || 0,
                    root.scrollHeight || 0,
                ].join(':');
                window.__sitekitLayoutSamples = window.__sitekitLayoutSamples || [];
                window.__sitekitLayoutSamples.push(current);
                if (window.__sitekitLayoutSamples.length > 3) window.__sitekitLayoutSamples.shift();
                return window.__sitekitLayoutSamples.length >= 3 &&
                    new Set(window.__sitekitLayoutSamples).size === 1;
            }""",
            timeout=bounded_timeout,
        )
    except Exception:
        pass


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
          const nodes = document.querySelectorAll('body *');
          for (const node of nodes) {
            const style = window.getComputedStyle(node);
            const identity = `${node.id || ''} ${node.className || ''}`.toLowerCase();
            const semanticOverlay = node.getAttribute('role') === 'dialog' ||
              node.getAttribute('aria-modal') === 'true' ||
              /cookie|consent|modal|popup|overlay|dialog/.test(identity);
            const fixed = style.position === 'fixed';
            const zIndex = Number.parseInt(style.zIndex || '', 10);
            if (fixed && semanticOverlay && (Number.isNaN(zIndex) || zIndex >= 0)) {
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


def reveal_page_for_analysis(page: Page) -> None:
    """Trigger ordinary viewport-bound animations before evidence is captured."""
    page.evaluate("window.scrollTo(0, 0)")
    page.wait_for_timeout(150)
    page_height = int(
        page.evaluate(
            """() => Math.max(
                document.body?.scrollHeight || 0,
                document.documentElement.scrollHeight,
                document.body?.offsetHeight || 0,
                document.documentElement.offsetHeight
            )"""
        )
        or 0
    )
    viewport_height = int(page.evaluate("() => window.innerHeight") or 800)
    step = max(320, int(viewport_height * 0.8))
    for top in range(0, max(page_height, viewport_height), step):
        page.evaluate("(value) => window.scrollTo(0, value)", top)
        page.wait_for_timeout(120)
    page.evaluate("window.scrollTo(0, 0)")
    page.wait_for_timeout(350)


def capture_static_fallback(
    browser: Browser,
    url: str,
    screenshot_path: Path,
    width: int,
    height: int,
    *,
    mobile: bool,
    timeout_ms: int,
) -> dict[str, Any]:
    request = Request(url, headers={"User-Agent": "Mozilla/5.0 SiteKitScraper/1.0"})
    with urlopen(request, timeout=max(1, timeout_ms // 1000)) as response:
        html = response.read().decode(response.headers.get_content_charset() or "utf-8", errors="replace")
        final_url = response.geturl()

    page = browser.new_page(
        viewport={"width": width, "height": height},
        is_mobile=mobile,
        device_scale_factor=1,
        java_script_enabled=False,
    )
    page.set_content(html, wait_until="commit", timeout=timeout_ms)
    page.wait_for_timeout(300)
    page.screenshot(path=str(screenshot_path), full_page=True, timeout=timeout_ms)
    metadata = {
        "title": page.title(),
        "finalUrl": final_url,
        "viewport": {"width": width, "height": height},
        "html": page.content(),
    }
    page.close()
    return metadata


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
    # Third-party analytics, embeds, and font loaders can keep a page from
    # reaching network-idle even after the rendered document is usable.
    page.goto(url, wait_until="commit", timeout=timeout_ms)
    page.wait_for_selector("html", state="attached", timeout=timeout_ms)
    wait_for_render_ready(page, timeout_ms)
    if dismiss_overlays:
        dismiss_basic_overlays(page)
        page.wait_for_timeout(300)
    reveal_page_for_analysis(page)
    wait_for_render_ready(page, timeout_ms)
    try:
        page.screenshot(path=str(screenshot_path), full_page=True)
        metadata = {
            "title": page.title(),
            "finalUrl": page.url,
            "viewport": {"width": width, "height": height},
            "html": page.content(),
        }
        page.close()
        return metadata
    except PlaywrightTimeoutError:
        page.close()
        return capture_static_fallback(
            browser,
            url,
            screenshot_path,
            width,
            height,
            mobile=mobile,
            timeout_ms=timeout_ms,
        )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Capture a Showit page into scraper artifacts.")
    parser.add_argument("--url", required=True, help="Public URL to capture.")
    parser.add_argument("--site-slug", help="Override site slug.")
    parser.add_argument("--page-slug", help="Override page slug.")
    parser.add_argument("--output", help="Override the page artifact directory.")
    parser.add_argument("--desktop-width", type=int, default=1440)
    parser.add_argument("--desktop-height", type=int, default=2200)
    parser.add_argument("--mobile-width", type=int, default=430)
    parser.add_argument("--mobile-height", type=int, default=1600)
    parser.add_argument("--timeout-ms", type=int, default=DEFAULT_TIMEOUT_MS)
    parser.add_argument("--keep-overlays", action="store_true", help="Skip the basic overlay dismissal step.")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    paths = build_scrape_paths(args.url, site_slug=args.site_slug, page_slug=args.page_slug, output_dir=args.output)
    ensure_scrape_paths(paths)

    desktop_png = paths.screenshots_full_dir / "desktop.png"
    mobile_png = paths.screenshots_full_dir / "mobile.png"
    html_path = paths.html_dir / "rendered.html"
    mobile_html_path = paths.html_dir / "rendered-mobile.html"
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
    write_text(mobile_html_path, mobile.pop("html"))

    payload = {
        "version": "0.1",
        "capturedAt": utc_timestamp(),
        "sourceUrl": args.url,
        "siteSlug": paths.site_slug,
        "pageSlug": paths.page_slug,
        "artifacts": {
            "html": str(html_path.relative_to(paths.page_dir.parent.parent.parent)),
            "mobileHtml": str(mobile_html_path.relative_to(paths.page_dir.parent.parent.parent)),
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
