#!/usr/bin/env python3
"""Restore optional timing data from a successful run of this workflow."""

import argparse
import io
import json
from pathlib import Path
import subprocess
import zipfile
import zlib

from phpunit_timings import ARTIFACT_NAME, HISTORY_FILE, MAX_HISTORY_BYTES, decode_timings, write_timings

MAX_DOWNLOADS = 5


def gh_json(*args):
    # Call the GitHub CLI, which inherits GH_TOKEN from the workflow step.
    # The argument list avoids shell interpretation; text=True decodes stdout,
    # then json.loads() turns the response into ordinary Python lists/dicts.
    return json.loads(subprocess.check_output(["gh", *args], text=True, timeout=30))


def archive_timings(data):
    if len(data) > MAX_HISTORY_BYTES:
        raise ValueError("Timing archive exceeds the size limit")
    # The artifact endpoint returns ZIP bytes. BytesIO lets ZipFile read them
    # like a file, without saving or unpacking the downloaded archive on disk.
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        members = archive.infolist()
        if len(members) != 1 or members[0].filename != HISTORY_FILE or members[0].file_size > MAX_HISTORY_BYTES:
            raise ValueError("Timing archive must contain only a bounded timings.json")
        # Never extract historical files, including symlinks or traversal paths.
        with archive.open(members[0]) as source:
            return decode_timings(source.read(MAX_HISTORY_BYTES + 1))


def restore(repository, branch, output):
    runs = gh_json("run", "list", "--repo", repository, "--workflow", "run-tests.yml", "--status", "success", "--limit", "30", "--json", "databaseId,headBranch")
    # Prefer this PR's history, then another successful run of the same workflow.
    # False sorts before True; Python's stable sort keeps the existing recency
    # order within each group. Use one snapshot, rather than mixing past runs.
    runs.sort(key=lambda run: run["headBranch"] != branch)
    downloads = 0
    for run in runs:
        run_id = str(run["databaseId"])
        artifacts = gh_json("api", f"repos/{repository}/actions/runs/{run_id}/artifacts?per_page=100")["artifacts"]
        for item in artifacts:
            if item["name"] != ARTIFACT_NAME or item["expired"] or not 0 < item["size_in_bytes"] <= MAX_HISTORY_BYTES:
                continue
            if downloads >= MAX_DOWNLOADS:
                print("::warning::Timing download limit reached; using cold-start shards.")
                return
            downloads += 1
            try:
                # GitHub reports the immutable archive size before downloading.
                data = subprocess.check_output(["gh", "api", f"repos/{repository}/actions/artifacts/{item['id']}/zip"], timeout=30)
                timings = archive_timings(data)
            except (subprocess.SubprocessError, OSError, ValueError, zipfile.BadZipFile, zlib.error, EOFError, RuntimeError, NotImplementedError):
                print("::warning::Ignoring unavailable or invalid PHPUnit timing artifact.")
                continue
            # Only validated data reaches this local file. The next workflow
            # step runs on the same runner and reads it from this directory.
            write_timings(output / HISTORY_FILE, timings)
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
    except (subprocess.SubprocessError, OSError, ValueError, KeyError):
        # Timing history is an optimization, never a prerequisite for testing.
        print("::warning::Could not restore PHPUnit timings; using cold-start shards.")


if __name__ == "__main__":
    main()
