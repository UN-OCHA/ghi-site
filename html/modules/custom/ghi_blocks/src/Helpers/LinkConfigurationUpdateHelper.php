<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\layout_builder\SectionComponent;

/**
 * Helper class configuration updates related to links.
 */
class LinkConfigurationUpdateHelper {

  /**
   * Update links in a plan headline figures component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component to update.
   *
   * @return bool
   *   TRUE if a change has been made, FALSE otherwise.
   */
  public static function updatePlanHeadlinerFiguresComponent(SectionComponent $component) {
    $changed = FALSE;
    $configuration = $component->get('configuration');
    if (empty($configuration['hpc']['key_figures']['items'])) {
      return $changed;
    }
    foreach ($configuration['hpc']['key_figures']['items'] as &$item) {
      if ($item['item_type'] != 'item_group') {
        continue;
      }
      if (empty($item['config']['link'])) {
        continue;
      }
      if (array_key_exists('add_link', $item['config']['link']) && !empty($item['config']['link']['link_type'])) {
        continue;
      }
      if (array_key_exists('add_link', $item['config']['link'])) {
        $item['config']['link'] = [
          'add_link' => $item['config']['link']['add_link'],
          'label' => $item['config']['link']['link']['label'] ?? NULL,
          'link_type' => $item['config']['link']['add_link'] ? 'custom' : NULL,
          'link_custom' => [
            'url' => $item['config']['link']['link']['url'] ?? NULL,
          ],
          'link_related' => [
            'target' => NULL,
          ],
        ];
      }
      else {
        $has_link = !empty($item['config']['link']['label']) && !empty($item['config']['link']['url']);
        $item['config']['link'] = [
          'add_link' => $has_link,
          'label' => $item['config']['link']['label'] ?? NULL,
          'link_type' => $has_link ? 'custom' : NULL,
          'link_custom' => [
            'url' => $item['config']['link']['url'] ?? NULL,
          ],
          'link_related' => [
            'target' => NULL,
          ],
        ];
      }
      $changed = TRUE;
      $component->setConfiguration($configuration);
    }
    return $changed;
  }

  /**
   * Update links in a links component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component to update.
   *
   * @return bool
   *   TRUE if a change has been made, FALSE otherwise.
   */
  public static function updateLinksComponent(SectionComponent $component) {
    $changed = FALSE;
    $configuration = $component->get('configuration');
    if (empty($configuration['hpc']['links']['links'])) {
      return $changed;
    }
    foreach ($configuration['hpc']['links']['links'] as &$item) {
      if ($item['item_type'] != 'link') {
        continue;
      }
      if (empty($item['config']['link'])) {
        continue;
      }
      if (!empty($item['config']['link']['link']['link_type'])) {
        continue;
      }
      $has_link = !empty($item['config']['link']['url']);
      $item['config']['content'] = [
        'date' => $item['config']['link']['date'],
        'description' => $item['config']['link']['description'],
        'description_toggle' => $item['config']['link']['description_toggle'],
      ];
      $item['config']['link'] = [
        'link' => [
          'label' => NULL,
          'link_type' => $has_link ? 'custom' : NULL,
          'link_custom' => [
            'url' => $item['config']['link']['url'] ?? NULL,
          ],
          'link_related' => [
            'target' => NULL,
          ],
        ],
      ];
      $changed = TRUE;
      $component->setConfiguration($configuration);
    }
    return $changed;
  }

  /**
   * Update links in a plan entity types component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   The section component to update.
   *
   * @return bool
   *   TRUE if a change has been made, FALSE otherwise.
   */
  public static function updatePlanEntityTypesComponent(SectionComponent $component) {
    $changed = FALSE;
    $configuration = $component->get('configuration');
    if (empty($configuration['hpc']['display']['link'])) {
      return $changed;
    }
    $item = &$configuration['hpc']['display']['link'];
    $has_link = !empty($item['link']['url']);
    $item = [
      'add_link' => $item['add_link'],
      'label' => $item['link']['label'] ?? NULL,
      'link_type' => $has_link ? 'custom' : NULL,
      'link_custom' => [
        'url' => $item['link']['url'] ?? NULL,
      ],
      'link_related' => [
        'target' => NULL,
      ],
    ];
    $changed = TRUE;
    $component->setConfiguration($configuration);
    return $changed;
  }

}
