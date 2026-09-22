# Drupal 11 Core Patches

Target: Drupal **11.4.7**, prepared on 2026-09-18 and last updated on
2026-09-22, including the latest groups fix and the group-rendering guard.
This patch set was activated in the root Composer configuration for the local
Drupal 11.4.7 upgrade on 2026-09-22. Commit the referenced local patches together
with the upgraded Composer files. Do not apply these backports to Drupal 10 or
replace the complete project patch manifest with this core-only file.

## Patch Decisions

`core.patches.json` preserves the existing patch order and adds #2926030 after
#2190333. It uses two local backports, an exact downloaded MR diff, and the
published element-submit and decimal backports, retains five unchanged patches, and omits
two changes now provided by core and the unused GROUP_CONCAT feature.
It also includes the committed upstream #3622419 EntityQuery fix from after
the 11.4.7 tag, plus the #3568380 OpenTelemetry backport, for a total of twelve
patches.
Both manifests use the compact description-to-URL format. SHA-256 checksums are
pinned in `patches.lock.json`; core patch depth 2 is configured in
`composer.json` under `extra.composer-patches.package-depths`.

| Issue | Drupal 11 disposition |
| --- | --- |
| [#2820359](https://www.drupal.org/project/drupal/issues/2820359) | Uses the published [comment-124 backport for Drupal 11.4.7](https://www.drupal.org/files/issues/2026-09-19/2820359-11.4.7.patch), replacing the local D11 copy. Matches the changes prepared for MR !17188 against `main`. Uses the core callable resolver, retains bottom-up element submission, and compares complete subtree keys so `group_1` does not also submit `group_10`. Tests cover callback order, references, form-object callbacks, subtree limits and validation errors. `FALSE` and `[]` both remain unlimited, as in comment 121. |
| [#2844620](https://www.drupal.org/project/drupal/issues/2844620) | Omitted: core now splits oversized debug cache headers. Native output uses repeated header values, not the old patch's numbered header suffixes. Review any downstream header consumers. |
| [#3008924](https://www.drupal.org/project/drupal/issues/3008924) | Existing Layout Builder view-mode patch retained unchanged. |
| [#2902481](https://www.drupal.org/project/drupal/issues/2902481) | Removed from both the active Drupal 10 and staged Drupal 11 manifests on 2026-09-19: no exported or active local Views, language overrides or custom code use its aggregation types or filter plugin. The core issue is closed in favor of Views String Aggregation, but no replacement module is needed for this site. |
| [#151311](https://www.drupal.org/project/drupal/issues/151311) | Existing roles-permission patch retained unchanged. |
| [#2755791](https://www.drupal.org/project/drupal/issues/2755791) | Existing AJAX form patch retained unchanged. |
| [#3001188](https://www.drupal.org/project/drupal/issues/3001188) | Existing Layout Builder relationships patch retained unchanged. |
| [#2329253](https://www.drupal.org/project/drupal/issues/2329253) | Omitted: core already preserves changed timestamps when synchronizing entities. |
| Missing Layout Builder context | Existing local patch retained unchanged. |
| [#2190333](https://www.drupal.org/project/drupal/issues/2190333) | Local D11.4.7 backport through MR !11953 commit `5ed659655baa16b4bbede15cc37a5ac31efe8658`. Starts with the published comment-46 backport and adds the AJAX select test and managed-file callback fix. Also updates D11-only `DeprecatedElementTest` expectations for the added callbacks; that test no longer exists on main. |
| [#2926030](https://www.drupal.org/project/drupal/issues/2926030) | Exact MR !17212 diff at `1320eae75970b3ca1d7fee79ee608bec399cf04b`, downloaded after pipeline 971428 succeeded. Prevents reinserting group members on the `#render_children` pass and includes `FormElementGroupingTest`. No D11 adaptation needed. |
| [#2230909](https://www.drupal.org/project/drupal/issues/2230909) | Uses the published [Drupal 11.4.7 backport](https://www.drupal.org/files/issues/2026-09-19/2230909-11.4.7.patch) from [comment 16776839](https://www.drupal.org/project/drupal/issues/2230909#comment-16776839), replacing the local D11 copy. Matches MR !8282 at `23f8e8b4b6`, including the scale-zero field/widget fixes and SQLite trailing-zero assertion. Keeps the MR's current step-validation algorithm and tests, not the older local normalization implementation. Restores core's original storage-setting limits (precision 32, scale 10); no PHP-INI-dependent limits are carried forward. Preserves D11-only deprecation coverage when adapting `NumberTest`. |
| [#2585169](https://www.drupal.org/project/drupal/issues/2585169) | Local D11.4.7 backport of MR !10284 plus the prepared follow-up tests and documentation. Adds action/task render hooks; the old `menu_local_tasks_alter` still runs first, with a deprecation warning. Includes the Contact hook/test rename needed on D11. Tests deprecation, hook order, altered task data and cache metadata. Do not implement both task hooks for the same alteration. |
| [#3622419](https://www.drupal.org/project/drupal/issues/3622419) | Exact upstream 11.4.x commit `802f07cf141d4332aa7edd261e8fd70abbd18329`, checksum-pinned. Fixes AND conditions on unlimited-cardinality fields, exposed by the project's DocumentManagerTest. Remove when core includes this commit; no local adaptation. |
| [#3568380](https://www.drupal.org/project/drupal/issues/3568380) | Local D11.4.7 backport of MR !14414 at `b38dba1f739009f4f9a79db69d013120e44c0d9b`. Propagates OpenTelemetry context into renderer, BigPipe and entity-reference fibers. Runtime changes are unchanged from the MR; only two test callback signatures retain their D11 form. Remove when the installed core version provides this fix. |

The #2585169 backport is intentionally kept as a local patch for the upgrade;
upstream publication is not a prerequisite. It is byte-for-byte identical to
`artifacts/drupal-2585169-handoff/2585169-11.4.7.patch`. Its proposed deprecation
versions (12.0.0/13.0.0) are carried over from the MR preparation, not a claim
that core has shipped this API change. The element-submit and decimal backports
are attached to their upstream issues; publication does not mean they have been
merged into core. The latest groups backport is local because comment 46 lacks
the two subsequent MR commits. Its only new D11-specific adjustment is a test
expectation, not an additional runtime change.
Views String Aggregation 1.0.2 was inspected but not installed. It uses
`string_aggregation` / `string_aggregation_distinct` rather than `group_concat` /
`group_concat_distinct`, and its word-search and negative-regex filtering do not
match the removed rebase. Do not treat it as an automatic drop-in replacement
if string aggregation is needed later. Before deployment, confirm the target
environment has no unexported Views using the removed feature.

History confirms the feature is obsolete here: `3abb245845` (2021-11-25,
HPC-8283) added patch #33 and concatenation to the Teams View. `cbb3c69f77`
(2023-03-03, HPC-8996) removed all four aggregation references, replacing them
with standard fields or distinct counts, but left the Composer patch in place.
After removal on Drupal 10, 59 local Views SQL smoke checks were unchanged;
the project unit suite (583 tests, 1,783 assertions) and core Views aggregation
suite (12 tests, 120 assertions) passed. This does not verify production's
unexported configuration.

## Activation Procedure

1. Back up the database/files and keep the current Drupal 10 lock files recoverable.
2. Replace **only** `patches.drupal/core` in `composer.patches.json` with the
   corresponding array from `core.patches.json`, preserving all contrib entries.
   Paths in this staged manifest are relative to the project root.
3. Upgrade the core packages, Gin stack and PHPUnit/toolchain together. Begin
   with a Composer dry run. Do not enable this manifest while installing core 10.
4. Run `fin composer patches-relock` and verify the regenerated `patches.lock.json`
   contains these twelve core patches and all retained contrib patches. Reinstall
   with the new lock files and verify Composer's complete patch application.
5. Run updates, configuration import, project tests and staging workflow checks.
   The temporary Lenient allowances still need their own removal plan.

See `docs/drupal-11-upgrade.md` for the full upgrade checklist and remaining work.

The local installation completed all twelve core patches and the retained contrib
patches. The contrib entries in `patches.lock.json` are unchanged from the
Drupal 10 preparation snapshot. The checks below describe earlier isolated
verification; application-level upgrade results belong in the upgrade checklist.

## Verification

Verification uses an isolated official Drupal 11.4.7 checkout, not the active
site. Its release archive SHA-256 is
`bedfcfa9651f4dfa7c1b9e5b1feb64321177f1bc937b6c88cbef77d4082a1a8d`.
The ten prepared patches passed checksum verification and applied in order to a
fresh core package tree at Composer depth 2 on 2026-09-22. The
initial local patch set passed a combined regression run of **122 tests,
1,192 assertions** on PHP 8.4.21 / PHPUnit 11.5.56 / SQLite. Drupal and
DrupalPractice PHPCS found no violations on the rebases' changed PHP lines;
unchanged upstream files still contain baseline violations. Detailed suites and
limitations are recorded in the upgrade checklist.

The earlier published groups backport separately passed **398 tests, 2,010 assertions**
on Drupal 11.4.7, including grouped rendering, managed-file AJAX, callback
expectations and field-display browser tests. The earlier combined regression
run was not repeated with this replacement.

The published element-submit patch is byte-for-byte identical to the tested
backport, which separately passed **837 tests, 3,772 assertions**
on Drupal 11.4.7, covering Form API and render unit suites and the complete system
Form functional suite. The matching main changes passed **837 tests, 3,632
assertions** on PHP 8.5.6 / PHPUnit 12.5.34. Both passed core PHPCS and PHPStan.
The decimal follow-up changes two production lines and strengthens the existing
functional tests in MR !8282; it does not rebase or rewrite that branch. The
complete backport and a small MR-only follow-up patch are in
`artifacts/drupal-2230909-handoff/`. The active D10 decimal patch is unchanged.
The MR branch passes **5,288 tests, 43,049 assertions** and the D11.4.7 backport
passes **5,289 tests, 43,057 assertions**, covering the Utility and Core/Field
unit directories and `NumberFieldTest`. Core PHPCS and PHPStan pass for all five
files on both versions. All nine staged patches were checked again for checksum
and sequential application after this replacement.
The published decimal attachment is byte-for-byte identical to the tested
backport. Its URL replaces the duplicate local patch with the checksum unchanged;
no runtime tests were rerun for this URL-only replacement.

On 2026-09-22, the earlier #2585169 adaptation was replaced with the tested
MR-aligned backport as a local patch. Its recorded D11 menu and Contact checks
passed: 218 tests, 6,016 assertions, four skips and no failures. The replacement
only adopts those tested bytes and updates the checksum; it does not change the
active D10 installation or either active lock file. All nine manifest checksums
were verified again, and the complete sequence applies at depth 2 to a fresh
official Drupal 11.4.7 core tree. Runtime tests were not rerun for this adoption.

On 2026-09-22, the complete ten-patch set passed **831 tests, 2,984 assertions**
on PHP 8.4.21 / PHPUnit 11.5.56 / SQLite and Selenium Chromium. This covers the
Core/Form and Core/Render unit directories, Core/Render kernel directory,
grouping and managed-file kernel checks, datetime tests, form element rendering,
managed-file functional/JavaScript tests, AJAX groups and grouping visibility.
A separate test-only overlay also verified managed-file child uniqueness and
value preservation through AJAX upload and removal. The overlay is not shipped
in either upstream-derived patch. PHPCS passed on the changed follow-up files.
Evidence and the overlay are in `artifacts/drupal-11-upgrade-check-20260922/`.

Full GHI integration, MariaDB-specific behavior, browser/JavaScript and
production infrastructure checks remain required after the actual core upgrade.

## OpenTelemetry Fiber Backport

`core-opentelemetry-fibers-3568380.patch` is derived from
[MR !14414](https://git.drupalcode.org/project/drupal/-/merge_requests/14414),
downloaded on 2026-09-22 at source branch commit
`b38dba1f739009f4f9a79db69d013120e44c0d9b`. The only adaptations remove the
newer return type declarations from both sides of two test hunks, matching
the existing D11.4.7 callbacks in `FiberPlaceholderTest` and `RendererTest`.
No additional production logic or warning suppression is included.

The site's warning occurs when a Layout Builder access check requests an
uncached Fabric token from inside a rendering fiber. Microsoft's Kiota token
provider activates an OpenTelemetry span before checking the allowed host.
A local regression fixture uses a disallowed host to exercise that code without
contacting Microsoft. It produces the warning before the patch and passes
afterward. Additional checks cover placeholder rendering, sibling fibers
suspending/resuming with separate contexts, and scope cleanup after exceptions.
These local verification fixtures are in `artifacts/drupal-3568380-check/` and
are not additions to the upstream-derived patch.

Verification on PHP 8.4.21 / PHPUnit 11.5.56: four focused regression tests
(24 assertions), 412 renderer/BigPipe/local-task unit tests (1,547 assertions),
51 entity/render kernel tests (511 assertions), and 625 project unit tests
(2,320 assertions) pass. The project unit suite retains its existing one PHP
deprecation and 313 PHPUnit metadata deprecations. Core PHPCS passes on all ten
patched files and the local regression fixture/bootstrap. Composer reinstalled
core successfully with all twelve patches; the existing lock entries and
package versions are unchanged. Tests ran sequentially with a 512 MB cap.

The same placeholder regression reproduces with the unchanged Drupal 10.6.17
renderer and the same Kiota/OpenTelemetry versions. This is an isolated renderer
comparison using the current test harness, not a full Drupal 10 site reinstall.
The older renderer does not use a fiber for an ordinary render-context callback,
so that case passes on D10; placeholder rendering already uses fibers and warns.
Project commit `94018327b0` promoted the SDK from a development-only dependency
to a production dependency without changing these library versions. No committed
fiber-context initialization fix was found in the custom code or runtime config.
