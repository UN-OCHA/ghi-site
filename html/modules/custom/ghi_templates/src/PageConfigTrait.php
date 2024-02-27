<?php

namespace Drupal\ghi_templates;

use Drupal\Component\Serialization\Yaml;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Trait for working with page config for templates.
 */
trait PageConfigTrait {

  /**
   * Export a section storage to an array.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage to export.
   *
   * @return array
   *   An array representing the section storage.
   */
  public function exportSectionStorage(SectionStorageInterface $section_storage) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $section_storage->getContextValue('entity');
    $sections = $section_storage->getSections();
    $config_export = [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => (int) $entity->id(),
      'bundle' => $entity->bundle(),
      'url' => $entity->toUrl()->toString(),
      'validation' => count($sections) == 1,
      'page_config' => [],
    ];
    foreach ($sections as $delta => $section) {
      $config_export['page_config'][$delta] = $section->toArray();
      uasort($config_export['page_config'][$delta]['components'], function ($a, $b) {
        return $a['weight'] <=> $b['weight'];
      });
      foreach ($config_export['page_config'][$delta]['components'] as &$component) {
        unset($component['configuration']['uuid']);
        unset($component['configuration']['context_mapping']);
        unset($component['configuration']['data_sources']);
      }
    }

    $config_export['hash'] = md5(Yaml::encode(ArrayHelper::mapObjectsToString($config_export)));
    return $config_export;
  }

}
