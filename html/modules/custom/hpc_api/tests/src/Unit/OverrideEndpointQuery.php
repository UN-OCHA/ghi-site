<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Disable caching during tests.
 */
class OverrideEndpointQuery extends EndpointQuery {

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function cache($cache_key, $data = NULL, $reset = FALSE, $cache_base_time = NULL) {
    return NULL;
  }

}
