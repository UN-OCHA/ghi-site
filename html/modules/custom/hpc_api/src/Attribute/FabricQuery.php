<?php

declare(strict_types=1);

namespace Drupal\hpc_api\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FabricQuery attribute for plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FabricQuery extends Plugin {

  /**
   * Constructs a FabricQuery attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the fabric query type.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
  ) {}

}
