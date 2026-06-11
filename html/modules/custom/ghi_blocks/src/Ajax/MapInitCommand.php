<?php

namespace Drupal\ghi_blocks\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to initialize a lazy-loaded map.
 */
class MapInitCommand implements CommandInterface {

  /**
   * The map configuration.
   *
   * @var array
   */
  protected array $map;

  /**
   * Constructs a MapInitCommand object.
   *
   * @param array $map
   *   The map configuration.
   */
  public function __construct(array $map) {
    $this->map = $map;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'ghiMapInit',
      'map' => $this->map,
    ];
  }

}
