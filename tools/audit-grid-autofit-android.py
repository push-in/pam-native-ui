#!/usr/bin/env python3
"""Check the narrow-phone auto-fit showcase and retain raw visual evidence."""
from __future__ import annotations

import argparse
import importlib.util
import json
import sys
from pathlib import Path

SPEC = importlib.util.spec_from_file_location(
    "grid_autofit_shared", Path(__file__).with_name("audit-autocomplete-android.py"),
)
assert SPEC is not None and SPEC.loader is not None
M = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = M
SPEC.loader.exec_module(M)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--serial", required=True)
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-grid-autofit"))
    parser.add_argument("--responsive", action="store_true")
    parser.add_argument("--font-scale", type=float, choices=(1.0, 1.3, 2.0), default=None)
    args = parser.parse_args()
    audit = M.AutocompleteAudit(args.serial, "dev.pam.mobileui.catalog",
        "dev.pam.nativeapp.PamActivity", args.output, "p-responsive-grid", "Responsive Grid")
    try:
        audit.prepare()
        if args.font_scale is not None:
            audit.set_setting("system", "font_scale", str(args.font_scale))
        audit.launch()
        width, height = audit.screenshot("initial")
        section = "Responsive columns and gutters" if args.responsive else "Up to four columns — adapts to width"
        cells = []
        for attempt in range(5):
            root = audit.dump(f"grid-{attempt}")
            heading = audit.exact(root, section)
            if heading:
                lower = M.node_bounds(heading[0]).bottom
                following = audit.exact(root, "Responsive columns and gutters") if not args.responsive else []
                upper = M.node_bounds(following[0]).top if following else height - 80
                cells = []
                for label in ["Discover", "Create", "Review", "Ship"]:
                    matches = [M.node_bounds(n) for n in audit.exact(root, label)
                        if lower <= M.node_bounds(n).top and M.node_bounds(n).bottom <= upper]
                    if len(matches) == 1:
                        cells.append(matches[0])
                if len(cells) == 4:
                    break
            audit.assert_foreground("scroll grid section")
            audit.shell("input", "swipe", str(width // 2), str(height * 4 // 5),
                str(width // 2), str(height // 2), "450")
        if len(cells) != 4:
            raise M.AuditFailure(f"Four unique labels not visible in {section}")
        if not (cells[0].top == cells[1].top < cells[2].top == cells[3].top
                and cells[0].left == cells[2].left < cells[1].left == cells[3].left):
            raise M.AuditFailure("Narrow phone must place four cells in an aligned 2x2 grid")
        if any(c.left < 0 or c.right > width or c.bottom > height - 80 for c in cells):
            raise M.AuditFailure("Auto-fit labels are outside the visible content")
        audit.screenshot("autofit-verified")
        apk = audit.shell("pm", "path", audit.package).strip().splitlines()[0].removeprefix("package:")
        report = {"fullApproval": False, "device": args.serial, "columns": 2, "rows": 2,
            "section": section,
            "buildSha256": audit.shell("sha256sum", apk).split()[0],
            "fontScale": audit.setting("system", "font_scale"), "resultStatus": 1}
        (args.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
        print("PASS: auto-fit 2x2 labels visible and aligned", flush=True)
    finally:
        audit.restore()


if __name__ == "__main__":
    main()
