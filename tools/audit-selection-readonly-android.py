#!/usr/bin/env python3
"""Exercise generated readonly selection controls on the showcase only."""
from __future__ import annotations

import argparse
import importlib.util
import json
import sys
from pathlib import Path

SPEC = importlib.util.spec_from_file_location(
    "selection_readonly_shared", Path(__file__).with_name("audit-autocomplete-android.py"),
)
assert SPEC is not None and SPEC.loader is not None
M = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = M
SPEC.loader.exec_module(M)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--serial", required=True)
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-selection-readonly"))
    args = parser.parse_args()
    audit = M.AutocompleteAudit(args.serial, "dev.pam.mobileui.catalog",
        "dev.pam.nativeapp.PamActivity", args.output, "p-pagination", "Pagination")
    results = []
    audit.prepare()
    try:
        for tag, title, next_section, expected in [
            ("p-pagination", "Pagination", "One visible page", 3),
            ("p-segmented-button", "Segmented Button", "Single", 3),
            ("p-filter-bar", "Filter Bar", "Orders", 2),
        ]:
            audit.component_tag, audit.component_label = tag, title
            audit.launch()

            def controls(root):
                lower = M.node_bounds(audit.exact(root, "Read only")[0]).bottom
                upper = M.node_bounds(audit.exact(root, next_section)[0]).top
                return [n for n in audit.nodes(root)
                    if n.attrib.get("class") in {"android.widget.Button", "android.widget.ToggleButton"}
                    and lower <= M.node_bounds(n).top < upper]

            def signature(nodes):
                return [(n.attrib.get("content-desc"), n.attrib.get("selected"),
                    n.attrib.get("checked"), n.attrib.get("enabled")) for n in nodes]

            root = audit.dump(tag + "-before")
            initial = controls(root)
            if len(initial) != expected or not any(n.attrib.get("selected") == "true" for n in initial):
                raise M.AuditFailure(f"{tag}: missing readonly controls/selection")
            if any(n.attrib.get("enabled") != "false" for n in initial):
                raise M.AuditFailure(f"{tag}: readonly child is enabled")
            baseline = signature(initial)
            for index, control in enumerate(initial):
                audit.tap(M.node_bounds(control))
                after = controls(audit.dump(f"{tag}-tap-{index}"))
                if signature(after) != baseline:
                    raise M.AuditFailure(f"{tag}: tapping readonly control changed selection")
            audit.screenshot(tag + "-verified")
            results.append({"component": tag, "taps": len(initial), "resultStatus": 1})
            print("PASS", tag, flush=True)
        apk = audit.shell("pm", "path", audit.package).strip().splitlines()[0].removeprefix("package:")
        report = {"fullApproval": False, "buildSha256": audit.shell("sha256sum", apk).split()[0],
            "device": args.serial, "results": results}
        (args.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
    finally:
        audit.restore()


if __name__ == "__main__":
    main()
