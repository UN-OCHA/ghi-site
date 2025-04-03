<?php

namespace Drupal\ghi_base_objects;

use Drupal\Core\Entity\EntityAutocompleteMatcherInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_plans\Entity\GoverningEntity;

/**
 * Matcher class to get autocompletion results for entity reference.
 */
class RestrictClusterByPlanAutocompleteMatcher implements EntityAutocompleteMatcherInterface {

  use StringTranslationTrait;

  /**
   * Original entity autocomplete matcher.
   *
   * @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface
   */
  protected $entityAutocompleteMatcher;

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Public constructor.
   *
   * @param \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $entity_autocomplete_matcher
   *   The original entity autocomplete matcher service.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityAutocompleteMatcherInterface $entity_autocomplete_matcher, SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityAutocompleteMatcher = $entity_autocomplete_matcher;
    $this->selectionManager = $selection_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_id, $selection_handler, $selection_settings, $string = '') {
    $matches = $this->entityAutocompleteMatcher->getMatches($target_id, $selection_handler, $selection_settings, $string);
    if ($target_id !== 'base_object' || !array_key_exists('selected_plans', $selection_settings)) {
      return $matches;
    }
    // We want to filter out any clusters that do not belong to an already
    // selected plan.
    $selected_plans = $selection_settings['selected_plans'] ?: [];
    if (empty($selected_plans)) {
      $matches = array_filter($matches, function ($item) {
        return !str_ends_with($item['label'], '(Governing entity)');
      });
    }
    else {
      foreach ($matches as $key => $item) {
        if (!str_ends_with($item['label'], '(Governing entity)')) {
          continue;
        }
        $entity_id = $this->extractEntityIdFromAutocompleteInput($item['value']);
        if (!$entity_id) {
          unset($matches[$key]);
          continue;
        }
        $entity = $this->entityTypeManager->getStorage('base_object')->load($entity_id);
        if (!$entity instanceof GoverningEntity) {
          unset($matches[$key]);
          continue;
        }
        $plan_object = $entity->getPlan();
        if (!$plan_object || !in_array($plan_object->id(), $selected_plans)) {
          unset($matches[$key]);
          continue;
        }

        $matches[$key]['label'] = $plan_object->label() . ': ' . $matches[$key]['label'];
        $matches[$key]['value'] = $plan_object->label() . ': ' . $matches[$key]['value'];
      }
    }
    return array_values($matches);
  }

  /**
   * Extracts the entity ID from the autocompletion result.
   *
   * @param string $input
   *   The input coming from the autocompletion result.
   *
   * @return mixed|null
   *   An entity ID or NULL if the input does not contain one.
   */
  private function extractEntityIdFromAutocompleteInput($input) {
    $match = NULL;

    // Take "label (entity id)', match the ID from inside the parentheses.
    if (preg_match("/.+\s\(([^\)]+)\)/", $input, $matches)) {
      $match = $matches[1];
    }

    return $match;
  }

}
