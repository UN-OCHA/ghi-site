<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectMetaDataInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
use Drupal\node\Entity\Node;

/**
 * Bundle class for section nodes.
 */
class Section extends Node implements SectionNodeInterface {

  use ShortNameTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    if ($this->isAutocompleteRoute() || $this->isAdminPage()) {
      return $label;
    }
    $base_object = $this->get('field_base_object')->entity;
    return $this->getShortName($base_object) ?? $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle() {
    $base_object = BaseObjectHelper::getBaseObjectFromNode($this);
    if (!$base_object->needsYear()) {
      return $this->label();
    }
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitleMetaData() {
    $base_object = BaseObjectHelper::getBaseObjectFromNode($this);
    $meta_data = $base_object instanceof BaseObjectMetaDataInterface ? $base_object->getPageTitleMetaData() : NULL;
    return $meta_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    return $this->get('field_hero_image');
  }

  /**
   * See if the current page is an admin page.
   *
   * @return bool
   *   TRUE if the current page is an admin page, FALSE otherwise.
   */
  private static function isAdminPage() {
    return \Drupal::service('router.admin_context')->isAdminRoute();
  }

  /**
   * See if the current request is for an entity autocomplete element.
   *
   * @return bool
   *   TRUE if the current request is an autocomplete request, FALSE otherwise.
   */
  private static function isAutocompleteRoute() {
    $route_name = \Drupal::routeMatch()->getRouteName();
    return $route_name == 'system.entity_autocomplete';
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Make sure we have a valid alias for this node.
    \Drupal::service('pathauto.generator')->updateEntityAlias($this, $this->isNew() ? 'insert' : 'update');

    // Due to the way that the pathauto module works, the alias generation for
    // the subpages is not finished at this point. This is because at the time
    // when each subpage gets created and it's alias build, the section alias
    // itself has not been build, so that token replacements are not fully
    // available. To fix this, we invoke a custom hook that lets the
    // GHI Subpages module react just after a section has been fully build.
    \Drupal::moduleHandler()->invokeAll('section_post_save', [$this]);
  }

}
