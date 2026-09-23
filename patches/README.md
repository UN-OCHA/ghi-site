# Patches

`composer.patches.json` lists the active patches; `patches.lock.json` pins their
checksums.

## Local Core Backports

These patches target Drupal 11.4.7. Their upstream origins and local differences
are recorded here because the filenames alone do not capture them.

| Patch | Origin and local differences |
| --- | --- |
| `core-form-groups-2190333.patch` | MR !11953 through `5ed659655baa16b4bbede15cc37a5ac31efe8658`. Includes AJAX select coverage and the managed-file callback fix. Adjusts D11-only deprecated-element test expectations. |
| `core-group-render-2926030.patch` | Exact MR !17212 diff at `1320eae75970b3ca1d7fee79ee608bec399cf04b`; no local adaptation. Prevents duplicate group members during `#render_children`. |
| `core-local-actions-render-2585169.patch` | Based on MR !10284, with local follow-up tests/documentation and the Contact adaptation required by D11. Adds local-action/task render hooks. Do not implement both the old and new task-alter hooks for the same alteration. |
| `core-opentelemetry-fibers-3568380.patch` | MR !14414 at `b38dba1f739009f4f9a79db69d013120e44c0d9b`. Only two test signatures are adapted; runtime code is unchanged. Preserves OpenTelemetry context during rendering-fiber execution. |

Issue numbers in the patch manifest identify the corresponding Drupal.org issues.
