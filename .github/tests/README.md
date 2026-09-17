# PHPUnit CI sharding

`run-tests.yml` plans all test jobs once, alongside the image build. Every worker
downloads that same plan. `plan_phpunit_shards.py` discovers test files from the
suite directories in `phpunit.xml` and generates explicit PHPUnit configurations
using `build-phpunit-shard.py`. Bootstrap, environment, strictness and coverage
settings remain unchanged.

The bounded worker counts are defined in `SUITES`: one unit job, six kernel jobs,
four functional jobs and three Functional JavaScript jobs. Files stay intact so
test dependencies and data providers within a class remain together. The planner
assigns the longest files first to the shard with the lowest estimated duration.
It automatically changes membership, not the number of runners.

## Following one workflow run

Python runs as ordinary command-line scripts on the Ubuntu runner. The workflow
calls `python3 .github/tests/...`; these scripts use only Python's standard
library, so there is no `pip install` step. PHPUnit still runs in the Drupal
Docker container. Python prepares its configuration and reads its reports.

1. The `test-plan` job checks out the repository and tests the Python helpers.
   Meanwhile, the separate `quality` job builds the Docker image and checks code.
2. `restore_phpunit_timings.py` uses the `gh` command and the step's `GH_TOKEN` to
   find a usable previous artifact. It writes validated JSON to
   `previous-test-results/timings.json` on the planning runner.
3. `plan_phpunit_shards.py` reads that file and discovers today's test files from
   `phpunit.xml`. For each suite it divides whole files among the configured
   workers. `build-phpunit-shard.py` writes one XML configuration per worker.
4. The planner publishes a JSON matrix as a job output and the workflow uploads
   the generated files as the `phpunit-plan` artifact. After both `quality` and
   `test-plan` succeed, GitHub creates one PHPUnit job per matrix entry.
5. Each worker downloads the plan and image, copies its XML configuration into
   the container, and runs PHPUnit with `--log-junit`. It uploads that XML report
   and its separate code-coverage file when available, including after failures.
6. Once all workers pass, the `coverage` job downloads their reports.
   `summarize_phpunit_results.py` writes minimal JSON for future runs and a table
   of slow files for the current run. YAML uploads the JSON as timing history;
   the existing coverage steps also merge and report code coverage.

Each job has its own runner filesystem. Consecutive steps in one job can use
the same local files, but a different job needs an explicit transfer. Here the
matrix travels as a **job output**, while XML configurations and reports travel
as **artifacts**. The history artifact also carries data between workflow runs.

The Python/GitHub connection is small:

- `argparse` reads the arguments supplied by `run:` in YAML.
- `GITHUB_OUTPUT` contains a temporary file path. The planner appends
  `matrix={...}` there; YAML exposes `steps.plan.outputs.matrix` as a job output,
  then uses `fromJSON(needs.test-plan.outputs.matrix)` to create the matrix.
  Saving `matrix.json` alone would not tell GitHub to create any jobs.
- `GITHUB_STEP_SUMMARY` is another temporary file path, accepting Markdown for
  the run summary. Ordinary `print()` output goes to the step log.
- A printed `::warning::...` becomes a GitHub warning annotation. Unusable history
  can be reported this way without failing the step; an uncaught Python exception
  instead fails the step.

The scripts check for the GitHub output variables before writing to them, so
planning and summarizing also work locally. See GitHub's documentation for
[job outputs](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/pass-job-outputs)
and [workflow commands](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands).

## Reading the Python

Start with `plan_phpunit_shards.py`: `main()` coordinates the work and
`balance_files()` contains the assignment algorithm. With file times of 100, 80,
20 and 10 seconds and two workers, it produces groups of 100 + 10 and 80 + 20
seconds. Each next file goes to the worker with the lowest estimated total.

`phpunit_timings.py` is a shared module rather than a command-line entry point.
It owns JSON validation and JUnit parsing, so the restorer, planner and exporter
use the same format and limits. The other scripts use the usual
`if __name__ == "__main__": main()` guard: execute the command when invoked
directly, but only make functions available when loaded by another script.

The older `build-phpunit-shard.py` filename contains hyphens, which cannot appear
in a normal Python import. The planner uses `runpy.run_path()` to load its
`build_config()` function. The underscore-named modules use normal imports;
Python finds them beside the script being executed.

`test_plan_phpunit_shards.py` uses `unittest`, Python's built-in test framework.
Its GitHub calls are replaced with mock responses, so the checks run without
network access or credentials. It also generates real shard configurations and
checks that every discovered test file is included exactly once.

## Timing history

Each worker uploads `phpunit-results-*` JUnit XML, including results available
after test failures, retained for three days. These diagnostic reports can contain
test names, assertion values and captured output; they are downloadable by users
with repository read access.

Once all test workers pass, the coverage job reduces these reports to a JSON map
of repository test filenames to durations in seconds. Only this `timings.json`
file enters the `phpunit-timings-v1` artifact, retained for 30 days. Test names,
failure messages and captured output are excluded. The job summary lists only
filenames and durations. Retention changes apply to newly uploaded artifacts;
previously uploaded diagnostic artifacts retain their existing expiration.

`restore_phpunit_timings.py` checks the 30 most recent successful runs of this
workflow. It prefers the current PR branch, then falls back to another successful
run. Missing, expired, inaccessible or invalid history does not prevent tests
from running. At most five archive downloads are attempted per run, each with a
30-second timeout. Archives are read in memory without extracting historical files.
They must contain exactly one `timings.json`, with both the archive and JSON
limited to 1 MiB. The map accepts at most 10,000 canonical custom-module test paths
and finite numeric durations between zero and one hour per file. Duplicate keys,
unexpected paths and invalid values reject the entire map. Historical data can
affect balancing, but the checkout determines which tests execute and the fixed
worker limits determine concurrency.

PHPUnit reports absolute container paths; the exporter normalizes these to
existing `html/modules/custom/...Test.php` files and sums test-case durations,
including data sets. Current-run XML input is limited to 64 reports, 8 MiB per
report and 64 MiB total; DTD and entity declarations are rejected.
Parent suite durations are not added again. Deleted files are ignored. New files
use the median measured file duration within their suite. A suite with no history
uses deterministic round-robin assignment.

Old XML timing artifacts are not imported. The first successful run with the new
format collects timings; subsequent runs can balance against them. The plan
summary distinguishes measured files from estimates and flags
shards exceeding six minutes of estimated test execution. This leaves room for
roughly three minutes of setup within a ten-minute job target; it is not a timeout
or a guarantee against runner variation. A single expensive file may need to be
split or optimized before balancing can meet that target.

## Test efficiency

- Page-template import/export browser scenarios have their own class, sharing
  setup and UI helpers through `PageTemplateUiTestBase`.
- Embargo scenarios are split into page, section/article and subpage classes.
  `EmbargoedAccessTestBase` installs only the bundles declared by each class.
- Existing test methods and assertions are preserved. Functional team-form tests
  and kernel access-record tests protect different behavior and are both retained.
- Full CI site installation and code coverage are retained. Removing installation
  requires separate verification in a clean CI container; existing-site tests
  are not included in this workflow.

## Local validation

From the repository root:

```sh
python3 -m unittest discover -s .github/tests -p 'test_*.py'
python3 .github/tests/plan_phpunit_shards.py --output /tmp/phpunit-plan
python3 .github/tests/summarize_phpunit_results.py /tmp/test-results --output /tmp/timing-history/timings.json
python3 .github/tests/plan_phpunit_shards.py --timings /tmp/timing-history --output /tmp/phpunit-plan
```

Inspect `/tmp/phpunit-plan/summary.md` and the generated XML files. These XML files
use repository-relative paths and must be placed at the repository root when
executing PHPUnit, as the workflow does inside its container.
