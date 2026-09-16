#!/usr/bin/env python3
"""Show measured slow test files in the GitHub Actions job summary."""

import argparse
import os
from pathlib import Path

from plan_phpunit_shards import read_timings


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=Path)
    args = parser.parse_args()
    timings = read_timings(args.directory)
    if not timings:
        raise ValueError("No PHPUnit test timings found in the result artifacts")
    lines = ["### Slowest PHPUnit test files", "", "Test execution only; excludes CI environment setup and coverage report generation.", "", "| Test file | Duration |", "|---|---:|"]
    for path, duration in sorted(timings.items(), key=lambda item: (-item[1], item[0]))[:20]:
        lines.append(f"| `{path}` | {duration:.1f}s |")
    summary = "\n".join(lines) + "\n"
    print(summary)
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
            output.write(summary)


if __name__ == "__main__":
    main()
