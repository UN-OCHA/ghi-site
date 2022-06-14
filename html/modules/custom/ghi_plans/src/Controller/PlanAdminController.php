<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;

/**
 * Controller for admin features on plans.
 */
class PlanAdminController extends ControllerBase {

  /**
   * Access callback for the plan structure page.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(BaseObjectInterface $base_object) {
    return AccessResult::allowedIf($base_object->bundle() == 'plan');
  }

  /**
   * The _title_callback for the page that renders the admin form.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return string
   *   The page title.
   */
  public function planSettingsTitle(BaseObjectInterface $base_object) {
    return $this->t('Plan settings for %label', ['%label' => $base_object->label()]);
  }

  /**
   * The _title_callback for the page that renders the admin form.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return string
   *   The page title.
   */
  public function planStructureTitle(BaseObjectInterface $base_object) {
    return $this->t('Plan structure for %label', ['%label' => $base_object->label()]);
  }

}
