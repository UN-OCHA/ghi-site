#!/usr/bin/env python3
"""Show measured slow test files in the GitHub Actions job summary."""

import argparse
import os
from pathlib import Path

from phpunit_timings import read_junit_timings, write_timings


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    # The coverage job downloads all workers' JUnit XML into one directory.
    # The shared helper reduces those reports to {repository_path: seconds}.
    timings = read_junit_timings(args.directory)
    if not timings:
        raise ValueError("No PHPUnit test timings found in the result artifacts")
    # Save all measured files for the next run, even though the human-readable
    # summary below only shows the slowest 20. YAML handles the artifact upload.
    write_timings(args.output, timings)
    lines = ["### Slowest PHPUnit test files", "", "Test execution only; excludes CI environment setup and coverage report generation.", "", "| Test file | Duration |", "|---|---:|"]
    for path, duration in sorted(timings.items(), key=lambda item: (-item[1], item[0]))[:20]:
        lines.append(f"| `{path}` | {duration:.1f}s |")
    summary = "\n".join(lines) + "\n"
    print(summary)
    # print() goes to the step log; this file puts Markdown on the summary page.
    # Locally the variable is absent, so printing the summary is enough.
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
            output.write(summary)


if __name__ == "__main__":
    main()
