#!/usr/bin/env python3
"""Startup coverage must not depend on screenshots or the legacy count."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

SCRIPT = Path(__file__).with_name("audit-showcase-android-release-startup.py")
SPEC = importlib.util.spec_from_file_location("startup_audit", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class StartupCatalogTest(unittest.TestCase):
    def test_current_catalog_includes_universal_components_without_images(self):
        tags = MODULE.catalog_tags(SCRIPT.parent.parent)
        self.assertEqual(len(tags), 114)
        self.assertIn("p-tag-input", tags)
        self.assertIn("p-chart", tags)
        self.assertIn("p-app-scaffold", tags)

    def test_missing_route_fails_before_device_mutations(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "docs").mkdir()
            (root / "resources").mkdir()
            (root / "docs/catalog.md").write_text("<p-select>\n<p-select>")
            (root / "resources/material-parity.json").write_text(
                json.dumps({"reference": {"componentCount": 2}})
            )
            with self.assertRaises(MODULE.AuditFailure):
                MODULE.catalog_tags(root)


if __name__ == "__main__":
    unittest.main()
