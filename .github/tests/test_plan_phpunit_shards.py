"""Regression checks for timing-based CI test selection and configuration."""

import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
import xml.etree.ElementTree as ET
import zipfile

import plan_phpunit_shards as planner
import phpunit_timings as timings
import restore_phpunit_timings as history


class ShardPlannerTest(unittest.TestCase):
    def test_balances_runtime_instead_of_file_count(self):
        files = ["a", "b", "c", "d"]
        shards, totals, measured = planner.balance_files(files, dict(zip(files, [100, 80, 20, 10])), 2)
        self.assertEqual(shards, [["a", "d"], ["b", "c"]])
        self.assertEqual(totals, [110, 100])
        self.assertTrue(measured)

    def test_cold_start_is_deterministic_and_complete(self):
        files = [f"file-{index}" for index in range(11)]
        first = planner.balance_files(files, {}, 3)
        self.assertEqual(first, planner.balance_files(list(reversed(files)), {}, 3))
        self.assertEqual(sorted(sum(first[0], [])), sorted(files))
        self.assertFalse(first[2])
        self.assertEqual(sorted(map(len, first[0])), [3, 4, 4])

    def test_new_files_use_suite_median_and_stale_files_are_ignored(self):
        shards, totals, _ = planner.balance_files(["a", "b", "new"], {"a": 100, "b": 20, "deleted": 9000}, 2)
        self.assertEqual(shards, [["a"], ["b", "new"]])
        self.assertEqual(totals, [100, 80])

    def test_rejects_empty_shards(self):
        for count in (0, 3):
            with self.assertRaises(ValueError):
                planner.balance_files(["a", "b"], {}, count)

    def test_generated_configs_cover_real_suites_exactly_once(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory)
            # The child script must not publish a fake matrix or summary into
            # the actual CI step while we are only testing it.
            env = {key: value for key, value in os.environ.items() if key not in ("GITHUB_OUTPUT", "GITHUB_STEP_SUMMARY")}
            subprocess.run([sys.executable, str(Path(planner.__file__)), "--output", directory, "--timings", str(output / "missing")], check=True, stdout=subprocess.DEVNULL, env=env)
            matrix = json.loads((output / "matrix.json").read_text())["include"]
            original = ET.parse("phpunit.xml").getroot()
            self.assertEqual(len({row["coverage"] for row in matrix}), len(matrix))
            for suite in planner.SUITES:
                assigned = []
                for row in matrix:
                    if row["testsuite"] != suite:
                        continue
                    root = ET.parse(output / f"phpunit-{row['coverage']}.xml").getroot()
                    self.assertEqual(root.attrib, original.attrib)
                    for element in ("php", "coverage"):
                        self.assertEqual(ET.tostring(root.find(element)), ET.tostring(original.find(element)))
                    self.assertEqual(len(root.findall("testsuites/testsuite")), 1)
                    files = root.findall("testsuites/testsuite/file")
                    self.assertTrue(files)
                    assigned.extend(str(Path(node.text)) for node in files)
                self.assertEqual(sorted(assigned), planner.discover_files(Path("phpunit.xml"), suite))


class TimingDataTest(unittest.TestCase):
    def setUp(self):
        self.test_path = planner.discover_files(Path("phpunit.xml"), "unit")[0]
        self.data = {self.test_path: 5.5}

    def test_json_round_trip_and_rejection_fallback(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            timings.write_timings(path / timings.HISTORY_FILE, self.data)
            self.assertEqual(planner.read_timings(path), self.data)
            (path / timings.HISTORY_FILE).write_text("invalid diagnostic sentinel")
            with patch("builtins.print") as output:
                self.assertEqual(planner.read_timings(path), {})
            self.assertNotIn("sentinel", str(output.call_args_list))
            (path / timings.HISTORY_FILE).unlink()
            (path / "legacy.xml").write_text("old reports must never be read")
            self.assertEqual(planner.read_timings(path), {})

    def test_rejects_invalid_json_paths_and_durations(self):
        invalid = [[], {"/" + self.test_path: 1}, {self.test_path.replace("/src/", "/../"): 1}, {self.test_path + "\n": 1}]
        invalid.extend({self.test_path: value} for value in (True, None, "1", {}, -1, 3601, float("nan"), float("inf")))
        for value in invalid:
            with self.subTest(value=value), self.assertRaises(ValueError):
                timings.decode_timings(json.dumps(value).encode())
        key = json.dumps(self.test_path)
        for value in (b"broken", b"\xff", ('{' + key + ':1,' + key + ':2}').encode(), json.dumps(self.data).encode("utf-16")):
            with self.subTest(value=value), self.assertRaises(ValueError):
                timings.decode_timings(value)
        with patch.object(timings, "MAX_FILES", 0), self.assertRaises(ValueError):
            timings.decode_timings(json.dumps(self.data).encode())
        with patch.object(timings, "MAX_HISTORY_BYTES", 10), self.assertRaises(ValueError):
            timings.decode_timings(json.dumps(self.data).encode())

    def test_junit_export_sums_data_sets_and_removes_diagnostics(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            root = ET.Element("testsuites", time="900")
            suite = ET.SubElement(root, "testsuite", time="900")
            for duration in ("2.5", "3", "nan", "inf", "-1", "invalid", "3601"):
                case = ET.SubElement(suite, "testcase", file="/srv/www/" + self.test_path, time=duration, name="private-sentinel")
                ET.SubElement(case, "failure").text = "private-sentinel"
                ET.SubElement(case, "system-out").text = "private-sentinel"
            ET.SubElement(suite, "testcase", file="html/modules/custom/missing/UnknownTest.php", time="100")
            ET.SubElement(suite, "testcase", file="/srv/www/vendor/ExampleTest.php", time="100")
            ET.ElementTree(root).write(path / "junit.xml", encoding="utf-8")
            env = {key: value for key, value in os.environ.items() if key != "GITHUB_STEP_SUMMARY"}
            result = subprocess.run([sys.executable, ".github/tests/summarize_phpunit_results.py", directory, "--output", str(path / timings.HISTORY_FILE)], check=True, capture_output=True, text=True, env=env)
            self.assertEqual(planner.read_timings(path), self.data)
            self.assertNotIn("private-sentinel", result.stdout + result.stderr + (path / timings.HISTORY_FILE).read_text())

    def test_rejects_unsafe_and_oversized_reports(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            for value in ('<!DOCTYPE x [<!ENTITY x "private-sentinel">]><testsuites/>', "broken", "\x00", "<testsuites/>"):
                (path / "junit.xml").write_text(value)
                with patch("builtins.print"):
                    self.assertEqual(timings.read_junit_timings(path), {})
            for constant in ("MAX_REPORT_BYTES", "MAX_REPORT_TOTAL_BYTES", "MAX_REPORTS"):
                with patch.object(timings, constant, 0), self.assertRaises(ValueError):
                    timings.read_junit_timings(path)


def timing_archive(entries):
    output = io.BytesIO()
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for name, value in entries.items():
            archive.writestr(name, value)
    return output.getvalue()


class TimingHistoryTest(unittest.TestCase):
    def setUp(self):
        self.data = {"html/modules/custom/example/ExampleTest.php": 5.5}
        self.archive = timing_archive({timings.HISTORY_FILE: json.dumps(self.data)})
        self.artifact = {"id": 123, "name": timings.ARTIFACT_NAME, "expired": False, "size_in_bytes": len(self.archive)}

    def test_archive_accepts_only_one_bounded_json_file(self):
        self.assertEqual(history.archive_timings(self.archive), self.data)
        invalid = [
            {"../timings.json": "{}"},
            {"/timings.json": "{}"},
            {timings.HISTORY_FILE: "{}", "extra.txt": "diagnostics"},
            {timings.HISTORY_FILE: " " * (timings.MAX_HISTORY_BYTES + 1)},
            {timings.HISTORY_FILE: "invalid"},
        ]
        for entries in invalid:
            with self.subTest(names=list(entries)), self.assertRaises(ValueError):
                history.archive_timings(timing_archive(entries))
        with self.assertRaises(ValueError):
            history.archive_timings(b"x" * (timings.MAX_HISTORY_BYTES + 1))
        with self.assertRaises(zipfile.BadZipFile):
            history.archive_timings(b"broken")

    # Replace GitHub calls with canned responses: these tests need no token or
    # network. Stacked patch decorators pass mocks in from the bottom upwards.
    @patch.object(history.subprocess, "check_output")
    @patch.object(history, "gh_json")
    def test_prefers_successful_branch_history(self, gh_json, download):
        gh_json.side_effect = [
            [{"databaseId": 2, "headBranch": "other"}, {"databaseId": 1, "headBranch": "current"}],
            {"artifacts": [self.artifact]},
        ]
        download.return_value = self.archive
        with tempfile.TemporaryDirectory() as directory, patch("builtins.print"):
            history.restore("owner/repo", "current", Path(directory))
            self.assertEqual(planner.read_timings(Path(directory)), self.data)
        self.assertIn("success", gh_json.call_args_list[0].args)
        self.assertIn("runs/1/artifacts", gh_json.call_args_list[1].args[1])
        self.assertEqual(download.call_args.args[0], ["gh", "api", "repos/owner/repo/actions/artifacts/123/zip"])

    @patch.object(history.subprocess, "check_output")
    @patch.object(history, "gh_json")
    def test_skips_expired_oversized_legacy_and_invalid_history(self, gh_json, download):
        gh_json.side_effect = [
            [{"databaseId": index, "headBranch": "current"} for index in (3, 2, 1)],
            {"artifacts": [dict(self.artifact, expired=True), dict(self.artifact, size_in_bytes=timings.MAX_HISTORY_BYTES + 1), dict(self.artifact, name="phpunit-timings")]},
            {"artifacts": [self.artifact]},
            {"artifacts": [dict(self.artifact, id=456)]},
        ]
        download.side_effect = [b"invalid archive", self.archive]
        with tempfile.TemporaryDirectory() as directory, patch("builtins.print"):
            history.restore("owner/repo", "current", Path(directory))
            self.assertEqual(planner.read_timings(Path(directory)), self.data)
        self.assertEqual(download.call_count, 2)
        self.assertIn("artifacts/456/zip", download.call_args.args[0][2])

    @patch.object(history.subprocess, "check_output")
    @patch.object(history, "gh_json", return_value=[])
    def test_missing_history_does_not_prevent_testing(self, gh_json, download):
        with tempfile.TemporaryDirectory() as directory, patch("builtins.print"):
            history.restore("owner/repo", "current", Path(directory))
            self.assertFalse((Path(directory) / timings.HISTORY_FILE).exists())
        download.assert_not_called()

    @patch.object(history.subprocess, "check_output", side_effect=history.zlib.error("invalid compressed data"))
    @patch.object(history, "gh_json")
    def test_bounds_downloads_even_when_all_archives_are_corrupt(self, gh_json, download):
        gh_json.side_effect = [
            [{"databaseId": 1, "headBranch": "current"}],
            {"artifacts": [self.artifact] * (history.MAX_DOWNLOADS + 1)},
        ]
        with tempfile.TemporaryDirectory() as directory, patch("builtins.print"):
            history.restore("owner/repo", "current", Path(directory))
            self.assertFalse((Path(directory) / timings.HISTORY_FILE).exists())
        self.assertEqual(download.call_count, history.MAX_DOWNLOADS)

    @patch.object(history.subprocess, "check_output", side_effect=subprocess.CalledProcessError(1, "private-sentinel"))
    @patch.object(history, "gh_json")
    def test_failed_download_does_not_publish_history_or_diagnostics(self, gh_json, download):
        gh_json.side_effect = [
            [{"databaseId": 1, "headBranch": "current"}],
            {"artifacts": [self.artifact]},
        ]
        with tempfile.TemporaryDirectory() as directory, patch("builtins.print") as output:
            history.restore("owner/repo", "current", Path(directory))
            self.assertFalse((Path(directory) / timings.HISTORY_FILE).exists())
            self.assertNotIn("private-sentinel", str(output.call_args_list))


if __name__ == "__main__":
    unittest.main()
