<?php

namespace Drupal\ghi_blocks\LayoutBuilder;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;

/**
 * Service calss for managing layout sections on content entities.
 */
class LayoutSectionManager {

  /**
   * Merge all sections of the given entity into a single one.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A content entity.
   */
  public function mergeSections(ContentEntityInterface $entity) {
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections) || count($sections) == 1) {
      return;
    }
    $merged_components = [];
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      uasort($components, function (SectionComponent $a, SectionComponent $b) {
        return $a->getWeight() <=> $b->getWeight();
      });
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        $sections[$delta]['section']->removeComponent($component->getUuid());
        $component->setWeight(count($merged_components));
        $merged_components[$component->getUuid()] = $component;
      }
    }
    foreach (array_keys($sections) as $delta) {
      if ($delta == 0) {
        continue;
      }
      unset($sections[$delta]);
    }
    uasort($merged_components, function (SectionComponent $a, SectionComponent $b) {
      return $a->getWeight() <=> $b->getWeight();
    });
    foreach ($merged_components as $component) {
      $sections[0]['section']->appendComponent($component);
    }
    $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $entity->setSyncing(TRUE);
    $entity->save();
  }

}
