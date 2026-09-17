#!/usr/bin/env python3
"""Build a PHPUnit config containing one explicit test-file shard."""

import argparse
from pathlib import Path
import xml.etree.ElementTree as ET


# Keep the familiar xsi: prefix when ElementTree writes the original XML back.
ET.register_namespace("xsi", "http://www.w3.org/2001/XMLSchema-instance")


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", required=True, type=Path)
    parser.add_argument("--suite", required=True)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("files", nargs="+")
    return parser.parse_args()


def build_config(config, suite_name, output, files):
    tree = ET.parse(config)
    root = tree.getroot()
    testsuites = root.find("testsuites")
    if testsuites is None:
        raise RuntimeError(f"No <testsuites> element found in {config}")

    # Replace only test selection. Bootstrap, environment and coverage settings
    # elsewhere in phpunit.xml must stay the same for every worker.
    testsuites.clear()
    suite = ET.SubElement(testsuites, "testsuite", {"name": suite_name})
    for file_name in files:
        file_path = Path(file_name).as_posix()
        file_element = ET.SubElement(suite, "file")
        # The workflow puts this config at /srv/www, beside the original config,
        # so repository-relative paths still resolve inside the Drupal container.
        file_element.text = file_path if file_path.startswith("./") else f"./{file_path}"

    output.parent.mkdir(parents=True, exist_ok=True)
    tree.write(output, encoding="UTF-8", xml_declaration=True)


def main():
    args = parse_args()
    build_config(args.config, args.suite, args.output, args.files)


# Allow the planner to load build_config() without also parsing CLI arguments.
if __name__ == "__main__":
    main()
