<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Helper trait for handling page elements.
 */
trait PageElementsTrait {

  /**
   * Do an action on components of an entity.
   *
   * @param string $action
   *   The action to do.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $uuids
   *   The uuids.
   */
  public function actionComponentOnEntity($action, EntityInterface $entity, array $uuids) {
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    $uuids = array_combine($uuids, $uuids);
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $components = [];
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = &$sections[$delta]['section'];
      $components = $section->getComponents();
      if (!array_intersect_key($components, $uuids)) {
        continue;
      }
      foreach ($uuids as $uuid) {
        if ($action == 'remove') {
          $section->removeComponent($uuid);
          continue;
        }
        $component = &$components[$uuid];
        $configuration = $component->get('configuration');
        switch ($action) {
          case 'hide':
            $configuration['visibility_status'] = 'hidden';
            break;

          case 'unhide':
            $configuration['visibility_status'] = NULL;
            break;
        }
        $component->setConfiguration($configuration);
      }
    }
    $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $entity->save();
  }

}
