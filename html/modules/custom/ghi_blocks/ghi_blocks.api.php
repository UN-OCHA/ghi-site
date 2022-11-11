<?php

/**
 * @file
 * Documentation of supported hooks.
 */

/**
 * Alter query arguments for the plan overview query.
 *
 * @param array $query_args
 *   The query arguments passed in to plan overview query endpoint.
 * @param int $year
 *   The year which data will be retrieved.
 */
function hook_plan_overview_query_arguments_alter(array &$query_args, $year) {
  $query_args['version'] = 'latest';
}
