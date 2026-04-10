<?php

namespace Drupal\ghi_plans\ApiObjects\Prototypes;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for plan prototype objects.
 */
class PlanPrototype extends ApiObjectBase {

  /**
   * The plan id.
   *
   * @var int
   */
  protected int $planId;

  /**
   * The items.
   *
   * @var array
   */
  protected array $items;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $prototypes) {
    $this->rawData = $prototypes;
    $this->planId = reset($prototypes)->getPlanId();
    $this->id = $this->planId;
    $this->items = [];
    /** @var \Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype[] $prototypes */
    foreach ($prototypes as $prototype) {
      $this->items[$prototype->getOrderNumber() ?? $prototype->id()] = $prototype;
    }
    ksort($this->items);
  }

  /**
   * Get the plan id.
   *
   * @return int
   *   The plan id.
   */
  public function getPlanId(): int {
    return $this->planId;
  }

  /**
   * Get the entity prototypes that make up this plan prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype[]
   *   An array of entity prototypes.
   */
  public function getEntityPrototypes(): array {
    return $this->items;
  }

}
