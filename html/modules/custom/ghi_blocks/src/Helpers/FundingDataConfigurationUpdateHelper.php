<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;

/**
 * Helper class configuration updates related to links.
 */
class FundingDataConfigurationUpdateHelper {

  /**
   * Update a node.
   *
   * @param int $node_id
   *   The id of the node to update.
   *
   * @return bool
   *   TRUE if changed, FALSE otherwise.
   */
  public static function updateNode($node_id) {
    return self::updateEntity('node', $node_id);
  }

  /**
   * Update a page template.
   *
   * @param int $page_template_id
   *   The id of the page template to update.
   *
   * @return bool
   *   TRUE if changed, FALSE otherwise.
   */
  public static function updatePageTemplate($page_template_id) {
    return self::updateEntity('page_template', $page_template_id);
  }

  /**
   * Update an entity.
   *
   * @param string $entity_type_id
   *   The id of the entity type.
   * @param int $entity_id
   *   The id of the entity to update.
   *
   * @return bool
   *   TRUE if changed, FALSE otherwise.
   */
  public static function updateEntity($entity_type_id, $entity_id) {
    $changed = FALSE;
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    if (!$entity) {
      return $changed;
    }

    $changed = FALSE;
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return $changed;
    }
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      return $changed;
    }
    /** @var \Drupal\layout_builder\Section $section */
    $section = &$sections[0]['section'];
    $components = $section->getComponents();
    if (empty($components)) {
      return $changed;
    }
    foreach ($components as $component) {
      switch ($component->getPluginId()) {
        case 'global_key_figures':
          $changed = self::updateGlobalKeyFiguresComponent($component) || $changed;
          break;

        case 'plan_headline_figures':
          $changed = self::updatePlanHeadlineFiguresComponent($component) || $changed;
          break;

        case 'plan_governing_entities_table':
        case 'plan_organizations_table':
          $changed = self::updateStandardTableComponent($component) || $changed;
          break;
      }
    }

    if ($changed) {
      $entity->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $entity->save();
    }
    return $changed;
  }

  /**
   * Update funding data configuration in plan headline figures components.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component to update.
   *
   * @return bool
   *   TRUE if a change has been made, FALSE otherwise.
   */
  public static function updateGlobalKeyFiguresComponent(SectionComponent $component) {
    $changed = FALSE;
    $configuration = $component->get('configuration');
    if (empty($configuration['hpc']['key_figures']['items'])) {
      return $changed;
    }
    foreach ($configuration['hpc']['key_figures']['items'] as &$item) {
      if ($item['item_type'] != 'plan_overview_data') {
        continue;
      }

      if ($item['config']['type'] != 'funding_progress') {
        continue;
      }

      if ($item['config']['label'] != 'Coverage') {
        continue;
      }
      $item['config']['label'] = NULL;
      $changed = TRUE;
      $component->setConfiguration($configuration);
    }
    return $changed;
  }

  /**
   * Update funding data configuration in plan headline figures components.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component to update.
   *
   * @return bool
   *   TRUE if a change has been made, FALSE otherwise.
   */
  public static function updatePlanHeadlineFiguresComponent(SectionComponent $component) {
    $changed = FALSE;
    $configuration = $component->get('configuration');
    if (empty($configuration['hpc']['key_figures']['items'])) {
      return $changed;
    }
    foreach ($configuration['hpc']['key_figures']['items'] as &$item) {
      if ($item['item_type'] != 'funding_data') {
        continue;
      }

      if ($item['config']['data_type'] != 'funding_coverage') {
        continue;
      }

      if ($item['config']['label'] != 'Coverage') {
        continue;
      }
      $item['config']['label'] = NULL;
      $changed = TRUE;
      $component->setConfiguration($configuration);
    }
    return $changed;
  }

  /**
   * Update funding data configuration in standard table components.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component to update.
   *
   * @return bool
   *   TRUE if a change has been made, FALSE otherwise.
   */
  public static function updateStandardTableComponent(SectionComponent $component) {
    $changed = FALSE;
    $configuration = $component->get('configuration');
    if (empty($configuration['hpc']['table']['columns'])) {
      return $changed;
    }
    foreach ($configuration['hpc']['table']['columns'] as &$item) {
      if ($item['item_type'] != 'funding_data') {
        continue;
      }

      if ($item['config']['data_type'] != 'funding_coverage') {
        continue;
      }

      if ($item['config']['label'] != 'Coverage') {
        continue;
      }
      $item['config']['label'] = NULL;
      $changed = TRUE;
      $component->setConfiguration($configuration);
    }
    return $changed;
  }

}
