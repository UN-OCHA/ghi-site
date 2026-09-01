<?php

declare(strict_types=1);

namespace Drupal\ghi_form_elements\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ConfigurationContainerItem attribute for plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ConfigurationContainerItem extends Plugin {

  /**
   * Constructs a ConfigurationContainerItem attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the confirguration container item
   *   type.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The human-readable description of the confirguration
   *   container item type.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}

}
