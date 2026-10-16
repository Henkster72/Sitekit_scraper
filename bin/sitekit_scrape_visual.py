#!/usr/bin/env python3
from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path
from urllib.parse import urlparse


ROOT = Path(__file__).resolve().parent.parent


def source_url(value: str) -> str:
    parsed = urlparse(value)
    if parsed.scheme:
        return value
    path = Path(value).expanduser()
    if path.is_file():
        return path.resolve().as_uri()
    raise FileNotFoundError(f"Source is not a URL or readable local file: {value}")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run the canonical SiteKit visual-evidence frontend.")
    parser.add_argument("source", help="Source URL or local rendered HTML page.")
    parser.add_argument("--output", required=True, help="Page artifact directory to create or reuse.")
    parser.add_argument("--source-type", default="unknown", help="Observed source type, such as showit or jinja.")
    parser.add_argument("--desktop-width", type=int, default=1440)
    parser.add_argument("--desktop-height", type=int, default=900)
    parser.add_argument("--mobile-width", type=int, default=390)
    parser.add_argument("--mobile-height", type=int, default=844)
    parser.add_argument("--timeout-ms", type=int, default=60_000)
    parser.add_argument("--keep-overlays", action="store_true")
    return parser.parse_args()


def run_stage(script: str, source: str, output: Path, args: argparse.Namespace, extra: list[str] | None = None) -> None:
    command = [
        sys.executable,
        str(ROOT / "bin" / script),
        "--url",
        source,
        "--output",
        str(output),
    ]
    if script == "showit_capture.py":
        command.extend(
            [
                "--desktop-width",
                str(args.desktop_width),
                "--desktop-height",
                str(args.desktop_height),
                "--mobile-width",
                str(args.mobile_width),
                "--mobile-height",
                str(args.mobile_height),
                "--timeout-ms",
                str(args.timeout_ms),
            ]
        )
        if args.keep_overlays:
            command.append("--keep-overlays")
    if extra:
        command.extend(extra)
    subprocess.run(command, check=True, cwd=ROOT)


def main() -> int:
    args = parse_args()
    source = source_url(args.source)
    output = Path(args.output).expanduser().resolve()
    run_stage("showit_capture.py", source, output, args)
    run_stage("showit_segment.py", source, output, args)
    run_stage("showit_extract.py", source, output, args)
    run_stage("showit_export_visual_evidence.py", source, output, args, ["--source-type", args.source_type])

    evidence_path = output / "evidence" / "visual-evidence.v1.json"
    print(f"VISUAL EVIDENCE:\n{evidence_path}")
    print(
        "MAIN SITEKIT NEXT STEP:\n"
        "No visual-evidence.v1 consumer CLI is present yet. The current HTML mapper is:\n"
        "php /home/henk/Documents/php_websites/SiteKit/bin/showit_to_sitekit_payload.php "
        f"--html={output / 'html' / 'rendered.html'} --url={source} "
        f"--output={output / 'sitekit' / 'sitekit.json'} "
        f"--opensite-output={output / 'sitekit' / 'opensite.json'}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
