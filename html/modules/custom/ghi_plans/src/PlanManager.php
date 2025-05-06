<?php

namespace Drupal\ghi_plans;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Plan manager service class.
 */
class PlanManager {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a section create form.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $user;
  }

  /**
   * Get related plans for the given plan base object.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan base object for which to retrieve related plans.
   *
   * @return \Drupal\ghi_plans\Entity\Plan[]
   *   An array of plan base objects.
   */
  public function getRelatedPlans(Plan $plan) {
    $plans = [];

    $focus_country = $plan->getFocusCountry();
    if (!$focus_country) {
      return $plans;
    }

    // Find other object candidates that have the same focus country.
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_object_candidates */
    $plans = $this->entityTypeManager->getStorage($plan->getEntityTypeId())->loadByProperties([
      'type' => $plan->bundle(),
      'field_focus_country' => $focus_country->id(),
    ]);

    $plans = array_filter($plans, function (Plan $plan_candidate) use ($plan) {
      // If the current base object is of type RRP, we want to retain only
      // candidates that are also RRPs. If it's not an RRP, we only want
      // other candiates that are not RRPs either.
      return $plan->isRrp() ? $plan_candidate->isRrp() : !$plan_candidate->isRrp();
    });
    return $plans;
  }

}
