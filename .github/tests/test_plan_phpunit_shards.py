"""Regression checks for timing-based CI test selection and configuration."""

import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
import xml.etree.ElementTree as ET

import plan_phpunit_shards as planner
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

    def test_junit_sums_data_sets_without_double_counting_suites(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            (path / "junit.xml").write_text('''<testsuites time="900"><testsuite time="900">
              <testcase file="/srv/www/html/modules/custom/example/Test.php" name="test with data set 0" time="2.5"/>
              <testcase file="/var/www/html/modules/custom/example/Test.php" name="test with data set 1" time="3"/>
              <testcase file="/srv/www/html/modules/custom/invalid/Test.php" time="nan"/>
              <testcase file="/srv/www/html/modules/custom/invalid/Test.php" time="-1"/>
              <testcase file="/srv/www/html/modules/custom/invalid/Test.php" time="inf"/>
              <testcase file="/srv/www/html/modules/custom/invalid/Test.php" time="invalid"/>
              <testcase file="/srv/www/vendor/ExampleTest.php" time="50"/>
            </testsuite></testsuites>''')
            (path / "broken.xml").write_text("incomplete")
            self.assertEqual(planner.read_timings(path), {"html/modules/custom/example/Test.php": 5.5})

    def test_generated_configs_cover_real_suites_exactly_once(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory)
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


class TimingHistoryTest(unittest.TestCase):
    @patch.object(history.shutil, "copytree")
    @patch.object(history.subprocess, "run")
    @patch.object(history, "gh_json")
    def test_prefers_successful_branch_history(self, gh_json, run, copytree):
        gh_json.side_effect = [
            [{"databaseId": 2, "headBranch": "other"}, {"databaseId": 1, "headBranch": "current"}],
            {"artifacts": [{"name": "phpunit-timings", "expired": False}]},
        ]
        history.restore("owner/repo", "current", Path("unused"))
        self.assertIn("success", gh_json.call_args_list[0].args)
        self.assertEqual(run.call_args.args[0][3], "1")

    @patch.object(history.shutil, "copytree")
    @patch.object(history.subprocess, "run")
    @patch.object(history, "gh_json")
    def test_falls_back_past_expired_history(self, gh_json, run, copytree):
        gh_json.side_effect = [
            [{"databaseId": 2, "headBranch": "current"}, {"databaseId": 1, "headBranch": "other"}],
            {"artifacts": [{"name": "phpunit-timings", "expired": True}]},
            {"artifacts": [{"name": "phpunit-timings", "expired": False}]},
        ]
        history.restore("owner/repo", "current", Path("unused"))
        self.assertEqual(run.call_args.args[0][3], "1")

    @patch.object(history.subprocess, "run")
    @patch.object(history, "gh_json", return_value=[])
    def test_missing_history_does_not_prevent_testing(self, gh_json, run):
        history.restore("owner/repo", "current", Path("unused"))
        run.assert_not_called()

    @patch.object(history.shutil, "copytree")
    @patch.object(history.subprocess, "run", side_effect=subprocess.CalledProcessError(1, "gh"))
    @patch.object(history, "gh_json")
    def test_failed_download_does_not_publish_partial_history(self, gh_json, run, copytree):
        gh_json.side_effect = [
            [{"databaseId": 1, "headBranch": "current"}],
            {"artifacts": [{"name": "phpunit-timings", "expired": False}]},
        ]
        with patch.object(sys, "argv", ["restore", "--repository", "owner/repo", "--branch", "current"]), patch("builtins.print") as output:
            history.main()
        self.assertIn("Could not restore PHPUnit timings", output.call_args.args[0])
        copytree.assert_not_called()


if __name__ == "__main__":
    unittest.main()
