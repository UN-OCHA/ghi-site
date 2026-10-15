#!/usr/bin/env python3
"""Plan all CI shards from one timing snapshot, preserving test-file boundaries."""

import argparse
import glob
import json
import os
from pathlib import Path
import runpy
import statistics
import xml.etree.ElementTree as ET

from phpunit_timings import read_timings

# The older helper has hyphens in its filename, so a normal import will not work.
# run_path() returns its globals; take the function without running its main().
build_config = runpy.run_path(str(Path(__file__).with_name("build-phpunit-shard.py")))["build_config"]
# Keep concurrency bounded. Rebalance membership on every run, not runner count.
SUITES = {"unit": ("Unit", 1), "kernel": ("Kernel", 6), "functional": ("Functional", 4), "functional-javascript": ("Functional JavaScript", 3)}


def discover_files(config, suite_name):
    suite = ET.parse(config).find(f"testsuites/testsuite[@name='{suite_name}']")
    if suite is None:
        raise ValueError(f"Unknown test suite: {suite_name}")
    # Discover from this checkout, never from history: new tests must be included
    # and deleted tests must disappear even when the saved timings are stale.
    files = set()
    for directory in suite.findall("directory"):
        for match in glob.glob(directory.text):
            files.update(str(path) for path in Path(match).rglob("*Test.php"))
    if not files:
        raise ValueError(f"No test files found for {suite_name}")
    # Deduplicate overlapping directories and make filesystem order irrelevant.
    return sorted(files)


def balance_files(files, timings, count):
    if not 1 <= count <= len(files):
        raise ValueError("Shard count must be between one and the number of test files")
    known = [timings[path] for path in files if timings.get(path, 0) > 0]
    # Unknown/new files get the suite median; a cold start retains round-robin.
    fallback = statistics.median(known) if known else 1.0
    # Comprehension: build {filename: estimated_seconds} for this suite only.
    # Give zero-duration tests a small weight so they also spread across workers.
    weights = {path: max(timings.get(path, fallback), 0.001) for path in files}
    shards = [[] for _ in range(count)]
    totals = [0.0] * count
    # Place slow files first. The minus sign sorts durations descending; the
    # filename breaks ties so identical inputs always produce the same plan.
    for path in sorted(files, key=lambda path: (-weights[path], path)):
        # Tuple keys compare left to right: least estimated time, then fewest
        # files, then lowest shard index. With equal weights this is round-robin.
        index = min(range(count), key=lambda index: (totals[index], len(shards[index]), index))
        shards[index].append(path)
        totals[index] += weights[path]
    # Return the file lists, estimated totals and whether any times were measured.
    return [sorted(shard) for shard in shards], totals, bool(known)


def main():
    # Run from the repository root. argparse turns --config etc. into args.config
    # etc.; type=Path gives us path objects instead of plain strings.
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--config", type=Path, default=Path("phpunit.xml"))
    parser.add_argument("--timings", type=Path, default=Path("previous-test-results"))
    parser.add_argument("--output", type=Path, default=Path("phpunit-plan"))
    args = parser.parse_args()
    timings = read_timings(args.timings)
    matrix = []
    summary = ["### PHPUnit shard plan", "", "Target: about 6 minutes of tests, leaving room for job setup.", "", "| Shard | Files | Files with timings | Estimated test time |", "|---|---:|---:|---:|"]
    for suite_name, (label, count) in SUITES.items():
        files = discover_files(args.config, suite_name)
        shards, totals, measured = balance_files(files, timings, min(count, len(files)))
        for index, shard in enumerate(shards):
            name = suite_name if len(shards) == 1 else f"{suite_name}-{index + 1}"
            shard_label = label if len(shards) == 1 else f"{label} {index + 1}/{len(shards)}"
            build_config(args.config, name, args.output / f"phpunit-{name}.xml", shard)
            # One include entry becomes one GitHub job. 'coverage' doubles as the
            # unique shard ID used for config, JUnit and coverage filenames.
            matrix.append({"label": shard_label, "testsuite": suite_name, "coverage": name})
            estimate = f"{totals[index]:.0f}s" if measured else "No history"
            known = sum(path in timings for path in shard)
            summary.append(f"| {shard_label} | {len(shard)} | {known} | {estimate} |")
            print(f"{shard_label} ({estimate}):\n" + "\n".join(shard))
            if measured and totals[index] > 360:
                print(f"::warning::{shard_label} exceeds the 6-minute test budget; inspect its JUnit timings.")
    # Files go into the phpunit-plan artifact, which every worker downloads.
    matrix_json = json.dumps({"include": matrix})
    (args.output / "matrix.json").write_text(matrix_json + "\n")
    (args.output / "summary.md").write_text("\n".join(summary) + "\n")
    # GitHub sets this variable to a temporary FILE PATH. Appending matrix=JSON
    # publishes steps.plan.outputs.matrix; YAML exposes it as a job output and
    # fromJSON(needs.test-plan.outputs.matrix) creates the worker matrix.
    # Writing matrix.json or printing JSON alone would not publish that output.
    if os.environ.get("GITHUB_OUTPUT"):
        with open(os.environ["GITHUB_OUTPUT"], "a") as output:
            output.write(f"matrix={matrix_json}\n")
    # This separate file accepts Markdown for the run's summary page. Both env
    # checks let the same script run locally without GitHub-specific setup.
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
            output.write("\n".join(summary) + "\n")


if __name__ == "__main__":
    main()
