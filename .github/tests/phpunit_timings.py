"""Bounded, diagnostic-free PHPUnit timing history."""

import json
import math
from pathlib import Path
import re
import xml.etree.ElementTree as ET


# Shared by restore, planner and exporter so they agree on the history format.
# The artifact name lives on GitHub; HISTORY_FILE is the file inside it.
ARTIFACT_NAME = "phpunit-timings-v1"
HISTORY_FILE = "timings.json"
MAX_HISTORY_BYTES = 1024 * 1024
MAX_FILES = 10000
MAX_DURATION = 3600
MAX_REPORT_BYTES = 8 * 1024 * 1024
MAX_REPORTS = 64
MAX_REPORT_TOTAL_BYTES = 64 * 1024 * 1024
# Allow only repository-relative custom test paths. In particular, no '..',
# absolute paths or newlines that could end up in generated XML or summaries.
TEST_PATH = re.compile(r"html/modules/custom/(?:[A-Za-z0-9_-]+/)+[A-Za-z0-9_-]*Test\.php")


def valid_duration(value):
    # bool is a subclass of int in Python; exact types reject True as a duration.
    return type(value) in (int, float) and 0 <= value <= MAX_DURATION and math.isfinite(value)


def validate_timings(timings):
    if not isinstance(timings, dict) or len(timings) > MAX_FILES:
        raise ValueError("Timing history must be a bounded filename-to-duration map")
    for path, duration in timings.items():
        if not isinstance(path, str) or len(path) > 512 or not TEST_PATH.fullmatch(path) or not valid_duration(duration):
            raise ValueError("Timing history contains an invalid test path or duration")
    return timings


def unique_pairs(pairs):
    # json.loads normally keeps the last value for duplicate keys. Its
    # object_pairs_hook lets us reject ambiguous input before it becomes a dict.
    values = {}
    for key, value in pairs:
        if key in values:
            raise ValueError("Timing history contains duplicate keys")
        values[key] = value
    return values


def decode_timings(data):
    if len(data) > MAX_HISTORY_BYTES:
        raise ValueError("Timing history exceeds the size limit")
    try:
        return validate_timings(json.loads(data.decode("utf-8"), object_pairs_hook=unique_pairs))
    except (UnicodeError, RecursionError) as error:
        raise ValueError("Timing history is not valid UTF-8 JSON") from error


def write_timings(path, timings):
    data = json.dumps(validate_timings(timings), sort_keys=True, allow_nan=False).encode("utf-8")
    if len(data) > MAX_HISTORY_BYTES:
        raise ValueError("Timing history exceeds the size limit")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)


def read_timings(directory):
    """Read only the minimal JSON file; never consume old JUnit history."""
    try:
        with (directory / HISTORY_FILE).open("rb") as source:
            # Read one byte past the limit so oversized files can be detected
            # without first loading the whole file into memory.
            return decode_timings(source.read(MAX_HISTORY_BYTES + 1))
    except FileNotFoundError:
        return {}
    except (OSError, ValueError):
        # Do not echo rejected content: it may contain diagnostics or secrets.
        print("::warning::Ignoring invalid or oversized PHPUnit timing history.")
        return {}


def read_junit_timings(directory):
    """Reduce current-run reports to known source filenames and summed durations."""
    timings = {}
    total_bytes = 0
    for index, report in enumerate(sorted(directory.glob("*.xml"))):
        if index >= MAX_REPORTS:
            raise ValueError("Too many PHPUnit reports")
        with report.open("rb") as source:
            data = source.read(MAX_REPORT_BYTES + 1)
        total_bytes += len(data)
        if len(data) > MAX_REPORT_BYTES or total_bytes > MAX_REPORT_TOTAL_BYTES:
            raise ValueError("PHPUnit reports exceed the size limit")
        try:
            document = data.decode("utf-8")
            if "\x00" in document or "<!DOCTYPE" in document.upper() or "<!ENTITY" in document.upper():
                raise ValueError("DTD and entity declarations are not allowed")
            root = ET.fromstring(document)
        except (ValueError, ET.ParseError):
            print("::warning::Ignoring an invalid PHPUnit report.")
            continue
        # Count only test cases: parent testsuite times already contain their
        # children's durations, so adding those would count the work twice.
        for case in root.iter("testcase"):
            path = case.get("file", "")
            marker = "html/modules/custom/"
            if marker not in path:
                continue
            # PHPUnit ran in /srv/www, but the next checkout may live elsewhere.
            # Keep the common repository path and discard the container prefix.
            path = marker + path.split(marker, 1)[1]
            # Only actual test files may enter the saved map or job summary.
            if len(path) > 512 or not TEST_PATH.fullmatch(path) or not Path(path).is_file():
                continue
            try:
                duration = float(case.get("time", ""))
            except ValueError:
                continue
            if not valid_duration(duration):
                continue
            # Methods and data-provider cases share a file. Sum their times
            # because the planner moves whole files, never individual methods.
            # Nothing from names, failures or captured output enters this map.
            timings[path] = timings.get(path, 0) + duration
    return validate_timings(timings)
