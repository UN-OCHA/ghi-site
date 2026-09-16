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

## Timing history

Each worker uploads `phpunit-results-*` JUnit XML, including results available
after test failures. Once all test workers pass, the coverage job combines these
reports into `phpunit-timings`, retained for 30 days, and reports the slowest files
in its Actions summary.

`restore_phpunit_timings.py` checks the 30 most recent successful runs of this
workflow. It prefers the current PR branch, then falls back to another successful
run. Missing, expired or inaccessible history does not prevent tests from running.
Only timing data is consumed; the checkout determines which tests execute.

PHPUnit reports absolute container paths; the planner normalizes these to
`html/modules/custom/...` and sums test-case durations, including data sets.
Parent suite durations are not added again. Deleted files are ignored. New files
use the median measured file duration within their suite. A suite with no history
uses deterministic round-robin assignment.

The first successful run collects timings. Subsequent runs can balance against
them. The plan summary distinguishes measured files from estimates and flags
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
python3 .github/tests/plan_phpunit_shards.py --timings /tmp/test-results --output /tmp/phpunit-plan
```

Inspect `/tmp/phpunit-plan/summary.md` and the generated XML files. These XML files
use repository-relative paths and must be placed at the repository root when
executing PHPUnit, as the workflow does inside its container.
