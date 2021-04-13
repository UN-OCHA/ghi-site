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
  public function cache($data = NULL, $reset = FALSE) {
    return NULL;
  }

}
