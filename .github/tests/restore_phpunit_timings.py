#!/usr/bin/env python3
"""Restore optional timing data from a successful run of this workflow."""

import argparse
import json
from pathlib import Path
import shutil
import subprocess
import tempfile


def gh_json(*args):
    return json.loads(subprocess.check_output(["gh", *args], text=True, timeout=30))


def restore(repository, branch, output):
    runs = gh_json("run", "list", "--repo", repository, "--workflow", "run-tests.yml", "--status", "success", "--limit", "30", "--json", "databaseId,headBranch")
    # Prefer this PR's history, then another successful run of the same workflow.
    runs.sort(key=lambda run: run["headBranch"] != branch)
    for run in runs:
        run_id = str(run["databaseId"])
        artifacts = gh_json("api", f"repos/{repository}/actions/runs/{run_id}/artifacts?per_page=100")["artifacts"]
        if any(item["name"] == "phpunit-timings" and not item["expired"] for item in artifacts):
            # A failed download must not leave a partial timing snapshot behind.
            with tempfile.TemporaryDirectory() as directory:
                subprocess.run(["gh", "run", "download", run_id, "--repo", repository, "--name", "phpunit-timings", "--dir", directory], check=True, timeout=60)
                shutil.copytree(directory, output, dirs_exist_ok=True)
            print(f"Using PHPUnit timings from successful run {run_id}")
            return
    print("No previous timing artifact found; using deterministic cold-start shards.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repository", required=True)
    parser.add_argument("--branch", required=True)
    parser.add_argument("--output", type=Path, default=Path("previous-test-results"))
    args = parser.parse_args()
    try:
        restore(args.repository, args.branch, args.output)
    except (subprocess.SubprocessError, OSError, ValueError, KeyError) as error:
        # Timing history is an optimization, never a prerequisite for testing.
        print(f"::warning::Could not restore PHPUnit timings: {error}")


if __name__ == "__main__":
    main()
