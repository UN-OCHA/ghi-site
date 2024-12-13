<?php

namespace Drupal\ghi_plan_clusters\Entity;

use Drupal\ghi_base_objects\Entity\BaseObjectAwareEntityInterface;
use Drupal\ghi_subpages\Entity\SubpageIconInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;

/**
 * Interface class for plan cluster nodes.
 */
interface PlanClusterInterface extends SubpageNodeInterface, SubpageIconInterface, BaseObjectAwareEntityInterface {

  const BUNDLE = 'plan_cluster';
  const BASE_OBJECT_FIELD_NAME = 'field_base_object';
  const SECTION_REFERENCE_FIELD_NAME = 'field_entity_reference';
  const TITLE_OVERRIDE_FIELD_NAME = 'field_title_override';

  /**
   * Set the title override.
   *
   * @param string $title_override
   *   The title override.
   */
  public function setTitleOverride($title_override);

  /**
   * Get the logframe node associated to the plan cluster.
   *
   * @return \Drupal\ghi_subpages\Entity\LogframeSubpage|null
   *   A logframe node object.
   */
  public function getLogframeNode();

}
