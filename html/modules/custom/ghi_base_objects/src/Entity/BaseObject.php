<?php

namespace Drupal\ghi_base_objects\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Base object entity.
 *
 * @ingroup ghi_base_objects
 *
 * @ContentEntityType(
 *   id = "base_object",
 *   label = @Translation("Base object"),
 *   bundle_label = @Translation("Base object type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\ghi_base_objects\BaseObjectListBuilder",
 *     "views_data" = "Drupal\ghi_base_objects\Entity\BaseObjectViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\ghi_base_objects\Form\BaseObjectForm",
 *       "add" = "Drupal\ghi_base_objects\Form\BaseObjectForm",
 *       "edit" = "Drupal\ghi_base_objects\Form\BaseObjectForm",
 *       "delete" = "Drupal\ghi_base_objects\Form\BaseObjectDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ghi_base_objects\BaseObjectHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\ghi_base_objects\BaseObjectAccessControlHandler",
 *   },
 *   base_table = "base_object",
 *   translatable = FALSE,
 *   permission_granularity = "bundle",
 *   admin_permission = "administer base object entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/base-objects/{base_object}",
 *     "add-page" = "/admin/content/base-objects/add",
 *     "add-form" = "/admin/content/base-objects/add/{base_object_type}",
 *     "edit-form" = "/admin/content/base-objects/{base_object}/edit",
 *     "delete-form" = "/admin/content/base-objects/{base_object}/delete",
 *   },
 *   bundle_entity_type = "base_object_type",
 *   field_ui_base_route = "entity.base_object_type.edit_form"
 * )
 */
class BaseObject extends ContentEntityBase implements BaseObjectInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceId() {
    if (!$this->hasField('field_original_id')) {
      return NULL;
    }
    return $this->get('field_original_id')->value ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUniqueIdentifier() {
    return $this->bundle() . '--' . $this->getSourceId();
  }

  /**
   * {@inheritdoc}
   */
  public function equals(BaseObjectInterface $base_object) {
    if (!$this->getSourceId() || !$base_object->getSourceId() || $this->getSourceId() != $base_object->getSourceId()) {
      return FALSE;
    }
    if ($this->bundle() != $base_object->bundle()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function needsYear() {
    return $this->type->entity->needsYearForDataRetrieval();
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Base object entity.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
