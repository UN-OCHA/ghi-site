#!/usr/bin/env python3
"""Build a PHPUnit config containing one explicit test-file shard."""

import argparse
from pathlib import Path
import xml.etree.ElementTree as ET


ET.register_namespace("xsi", "http://www.w3.org/2001/XMLSchema-instance")


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--config", required=True, type=Path)
    parser.add_argument("--suite", required=True)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("files", nargs="+")
    return parser.parse_args()


def main():
    args = parse_args()
    tree = ET.parse(args.config)
    root = tree.getroot()
    testsuites = root.find("testsuites")
    if testsuites is None:
        raise RuntimeError(f"No <testsuites> element found in {args.config}")

    testsuites.clear()
    suite = ET.SubElement(testsuites, "testsuite", {"name": args.suite})
    for file_name in args.files:
        file_path = Path(file_name).as_posix()
        file_element = ET.SubElement(suite, "file")
        file_element.text = file_path if file_path.startswith("./") else f"./{file_path}"

    args.output.parent.mkdir(parents=True, exist_ok=True)
    tree.write(args.output, encoding="UTF-8", xml_declaration=True)


if __name__ == "__main__":
    main()
