<?php

namespace Drupal\ghi_subpages\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;

/**
 * Entity bundle class for logframe subpage nodes.
 */
class LogframeSubpage extends SubpageNode {

  use LayoutEntityHelperTrait;

  /**
   * Create the page elements for the logframe page.
   */
  public function createPageElements() {
    $logframe_manager = self::logframeManager();
    $section_storage = $logframe_manager->setupLogframePage($this);
    if ($section_storage) {
      $section_storage->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $parent = $this->getParentNode();
    if (!$parent instanceof SectionNodeInterface) {
      $label = $parent->label() . ' ' . $this->type->entity->id();
      $this->setTitle($label);
    }
  }

  /**
   * Get the logframe manager service.
   *
   * @return \Drupal\ghi_subpages\LogframeManager
   *   The logframe manager service.
   */
  private static function logframeManager() {
    return \Drupal::service('ghi_subpages.logframe_manager');
  }

}
