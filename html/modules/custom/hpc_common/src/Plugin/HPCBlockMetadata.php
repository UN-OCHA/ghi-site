<?php

namespace Drupal\hpc_common\Plugin;

/**
 * Class representing meta data for HPC block plugins.
 */
class HPCBlockMetadata {

  /**
   * Construct a meta data object for HPC blocks.
   */
  public function __construct(
    public readonly ?string $defaultTitle = NULL,
    public readonly bool $usesTitle = TRUE,
    public readonly array $dataSources = [],
    public readonly array $configForms = [],
  ) {}

}
