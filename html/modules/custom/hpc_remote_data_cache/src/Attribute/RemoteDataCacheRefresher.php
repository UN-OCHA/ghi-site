<?php

declare(strict_types=1);

namespace Drupal\hpc_remote_data_cache\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a remote data cache refresher plugin.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class RemoteDataCacheRefresher extends Plugin {

  /**
   * Constructs a remote data cache refresher attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable label.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
  ) {}

}
