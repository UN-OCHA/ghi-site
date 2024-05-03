<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectMetaDataInterface;
use Drupal\ghi_base_objects\Traits\ShortNameTrait;
use Drupal\node\Entity\Node;

/**
 * Bundle class for section nodes.
 */
class Section extends Node implements SectionNodeInterface, ImageNodeInterface {

  use ShortNameTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = parent::label();
    if ($this->isAutocompleteRoute() || $this->isAdminPage()) {
      return $label;
    }
    $base_object = $this->getBaseObject() ?? NULL;
    if (!$base_object) {
      return $label;
    }
    return $this->getShortName($base_object) ?? $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle() {
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitleMetaData() {
    $base_object = $this->getBaseObject();
    $meta_data = $base_object instanceof BaseObjectMetaDataInterface ? $base_object->getPageTitleMetaData() : NULL;
    return $meta_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    $tags = [];
    $entities = $this->get('field_tags')->referencedEntities() ?? [];
    foreach ($entities as $tag) {
      $tags[$tag->id()] = $tag->label();
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    return $this->get('field_hero_image');
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseObject() {
    if (!$this->hasField(self::BASE_OBJECT_FIELD_NAME)) {
      return NULL;
    }
    return $this->get(self::BASE_OBJECT_FIELD_NAME)->entity ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSectionType() {
    $base_object = $this->getBaseObject();
    return $base_object->type->entity->label();
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
    /** @var \Drupal\pathauto\PathautoGeneratorInterface $pathauto_generator */
    $pathauto_generator = \Drupal::service('pathauto.generator');
    $result = $pathauto_generator->updateEntityAlias($this, $this->isNew() ? 'insert' : 'update', [
      'language' => $this->language()->getId(),
    ]);

    // The current alias is either the result of the pathauto generator, or the
    // alias set manually by a user.
    $alias = $result['alias'] ?? $this->path->alias;

    // If we have a valid path alias, set for this node, make sure it's
    // persistet, so that other nodes that have alias patterns using this nodes
    // path alias, can have direct access to it.
    // It should not be necessary to do this here, but it seems that the path
    // alias module persists the alias after this code has run.
    if (!empty($alias)) {
      /** @var \Drupal\path_alias\PathAliasStorage $path_alias_storage */
      $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
      $path_alias_storage->resetCache();

      /** @var \Drupal\path_alias\Entity\PathAlias $path_alias */
      $path_aliases = $path_alias_storage->loadByProperties([
        'path' => '/' . $this->toUrl()->getInternalPath(),
      ]);
      if (!empty($path_aliases)) {
        $path_alias = end($path_aliases);
        $path_alias->setAlias($alias)->save();
      }
      else {
        $path_alias_storage->create([
          'path' => '/' . $this->toUrl()->getInternalPath(),
          'alias' => $alias,
          'language' => $this->language()->getId(),
        ])->save();
      }
    }

    // Due to the way that the pathauto module works, the alias generation for
    // the subpages is not finished at this point. This is because at the time
    // when each subpage gets created and it's alias build, the section alias
    // itself has not been build, so that token replacements are not fully
    // available. To fix this, we invoke a custom hook that lets the
    // GHI Subpages module react just after a section has been fully build.
    \Drupal::moduleHandler()->invokeAll('section_post_save', [$this->load($this->id())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $cache_tags = parent::getCacheTagsToInvalidate();
    $base_object = $this->getBaseObject();
    if ($base_object) {
      $cache_tags = Cache::mergeTags($cache_tags, $base_object->getCacheTagsToInvalidate());
    }
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiCacheTagsToInvalidate() {
    $base_object = $this->getBaseObject();
    if (!$base_object) {
      return [];
    }
    return $base_object->getApiCacheTagsToInvalidate();
  }

}
