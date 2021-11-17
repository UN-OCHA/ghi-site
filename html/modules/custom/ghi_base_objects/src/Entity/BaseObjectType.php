<?php

namespace Drupal\ghi_base_objects\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Base object type entity.
 *
 * @ConfigEntityType(
 *   id = "base_object_type",
 *   label = @Translation("Base object type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\ghi_base_objects\BaseObjectTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ghi_base_objects\Form\BaseObjectTypeForm",
 *       "edit" = "Drupal\ghi_base_objects\Form\BaseObjectTypeForm",
 *       "delete" = "Drupal\ghi_base_objects\Form\BaseObjectTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ghi_base_objects\BaseObjectTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "base_object_type",
 *   admin_permission = "administer base object entities",
 *   bundle_of = "base_object",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "hasYear",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/base-objects/types/{base_object_type}",
 *     "add-form" = "/admin/structure/base-objects/types/add",
 *     "edit-form" = "/admin/structure/base-objects/types/{base_object_type}/edit",
 *     "delete-form" = "/admin/structure/base-objects/types/{base_object_type}/delete",
 *     "collection" = "/admin/structure/base-objects/types"
 *   }
 * )
 */
class BaseObjectType extends ConfigEntityBundleBase implements BaseObjectTypeInterface {

  /**
   * The Base object type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Base object type label.
   *
   * @var string
   */
  protected $label;

  /**
   * Flag to indicate if the Base object type has a year.
   *
   * @var bool
   */
  protected $hasYear;

  /**
   * {@inheritdoc}
   */
  public function hasYear() {
    return $this->hasYear;
  }

  /**
   * {@inheritdoc}
   */
  public function needsYearForDataRetrieval() {
    return !$this->hasYear();
  }

}
