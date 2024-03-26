<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_common\Helpers\NodeHelper;

/**
 * Provides an entity select element.
 *
 * @FormElement("entity_select")
 */
class EntitySelect extends FormElement {

  use AjaxElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processEntitySelect'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderEntitySelect'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#multiple' => TRUE,
      '#disabled' => FALSE,
      '#summary_only' => FALSE,
      '#include_main_plan' => TRUE,
    ];
  }

  /**
   * Element submit callback.
   *
   * @param array $element
   *   The base element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The full form.
   *
   * @todo Check if this is actually needed.
   */
  public static function elementSubmit(array &$element, FormStateInterface $form_state, array $form) {
    $values = $form_state->getValue($element['#parents']);
    $fixed_entity_ids = self::getFixedEntityIds($element);
    foreach ($fixed_entity_ids as $id) {
      $values['entity_ids'][$id] = $id;
    }
    $form_state->setValue($element['#parents'], $values);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (!empty($input)) {
      // Make sure input is returned as normal during item configuration.
      $fixed_entity_ids = self::getFixedEntityIds($element);
      $selected_entities = $input['entity_ids'] ?? [];
      $input['entity_ids'] = array_combine($fixed_entity_ids, $fixed_entity_ids) + $selected_entities;
      $form_state->setValues($input);
      return $input;
    }
    return NULL;
  }

  /**
   * Get the fixed entity ids based on the element context.
   *
   * @param array $element
   *   The base element.
   *
   * @return array
   *   An array of fixed entity ids.
   */
  private static function getFixedEntityIds($element) {
    if (empty($element['#include_main_plan'])) {
      return [];
    }
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $element['#element_context']['base_object'] ?? NULL;
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $element['#element_context']['plan_object'] ?? NULL;

    $fixed_entity_ids = [];
    if ($plan_object) {
      $fixed_entity_ids[] = $plan_object->getSourceId();
    }

    $plan_id = $plan_object?->getSourceId() ?? NULL;
    if ($base_object && !$base_object instanceof Plan && $base_object->getSourceId() != $plan_id) {
      $fixed_entity_ids[] = $base_object->getSourceId();
    }

    return $fixed_entity_ids;
  }

  /**
   * Process the entity select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processEntitySelect(array &$element, FormStateInterface $form_state) {
    $element['#attached']['library'][] = 'ghi_form_elements/entity_select';

    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $element['#element_context']['base_object'] ?? NULL;
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $element['#element_context']['plan_object'] ?? NULL;

    $plan_id = $plan_object->getSourceId();
    $plan_entities = self::getPlanEntitiesQuery($plan_id)->getPlanEntities($base_object);
    $plan_data = self::getPlanEntitiesQuery($plan_id)->getData();

    $is_hidden = array_key_exists('#hidden', $element) && $element['#hidden'];

    $wrapper_id = self::getWrapperId($element);
    $classes = ['ghi-element-wrapper'];
    $element['#prefix'] = '<div id="' . $wrapper_id . '" class="' . implode(' ', $classes) . ($is_hidden ? ' visually-hidden' : NULL) . '">';
    $element['#suffix'] = '</div>';

    $values = (array) $form_state->getValue($element['#parents']) + (array) $element['#default_value'];
    $fixed_entity_ids = self::getFixedEntityIds($element);

    $defaults = [
      'entity_ids' => $values['entity_ids'],
    ];
    $defaults['entity_ids'] = array_filter($defaults['entity_ids'], function ($key) {
      return strpos($key, 'group_') === FALSE;
    }, ARRAY_FILTER_USE_KEY);

    $entities = self::getEntityOptionsFromEntities($plan_entities);
    $sorted_entity_options = self::sortEntitiesByPlanStructure($entities, $plan_data, $base_object, TRUE, FALSE);
    $found_entities = array_reduce($sorted_entity_options, function ($carry, $items) {
      return $carry + count($items);
    });

    $entity_options = [];
    $entity_header_parts = [];

    // If requested, the main plan context is always force-set for inclusion,
    // so we add this here, check it and mark it as disabled.
    if (!empty($element['#include_main_plan'])) {
      $entity_options['group_plan'] = [
        'id' => [
          'data' => t('Main plan context'),
          'colspan' => 3,
        ],
        '#attributes' => [
          'class' => [
            'entity-group-row',
            'entity-' . Html::getClass('main-plan-context'),
          ],
        ],
        '#disabled' => TRUE,
      ];
      $entity_options[$plan_id] = [
        'id' => $plan_id,
        'name' => [
          'data' => $plan_data->planVersion->name,
          'colspan' => 2,
        ],
        '#disabled' => TRUE,
      ];
      $entity_header_parts[] = t('The main plan context is always present and cannot be unselected.');
    }

    if (!empty($fixed_entity_ids)) {
      $defaults['entity_ids'] = array_combine($fixed_entity_ids, $fixed_entity_ids) + $defaults['entity_ids'];
    }
    $has_additional_entities = count($entities) > count($fixed_entity_ids);

    // Then we add the actual entity options in groups.
    foreach ($sorted_entity_options as $group_name => $items) {
      $entity_options['group_' . Html::getClass($group_name)] = [
        'id' => [
          'data' => $group_name,
          'colspan' => 3,
        ],
        '#attributes' => [
          'class' => [
            'entity-group-row',
            'entity-' . Html::getClass($group_name),
          ],
        ],
        '#disabled' => TRUE,
      ];
      $entity_options = $entity_options + $items;

      if (!$base_object instanceof Plan && !empty($entity_options[$base_object->getSourceId()])) {
        $entity_options[$base_object->getSourceId()]['#disabled'] = TRUE;
        $entity_header_parts[0] = t('The main plan context and the current @type is always present and cannot be unselected.', [
          '@type' => strtolower($base_object->type->entity->label()),
        ]);
      }
    }

    $columns = [
      'id' => t('ID'),
      'name' => t('Name'),
      'description' => t('Description'),
    ];

    // Build the explanation that should show above the attachment select table.
    $select_message = $element['#multiple'] ? t('Select the entities that you want to use.') : t('Select the entity that you want to use.');

    $element['header'] = [
      '#type' => 'markup',
      '#markup' => ($has_additional_entities ? t('Found @count additional entities in @count_groups groups matching your selection.', [
        '@count' => $found_entities,
        '@count_groups' => count($sorted_entity_options),
      ]) . ' ' . $select_message : t('No additional entities found.')) . '<br />' . implode(' ', $entity_header_parts),
      '#prefix' => '<div>',
      '#suffix' => '</div><br />',
    ];

    $element['entity_ids'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#required' => TRUE,
      '#header' => $columns,
      '#validated' => TRUE,
      '#options' => $entity_options,
      '#default_value' => $defaults['entity_ids'],
      '#multiple' => $element['#multiple'],
      '#disabled' => $element['#disabled'],
      '#empty' => t('No entities found.'),
    ];
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderEntitySelect(array $element) {
    $element['#attributes']['type'] = 'entity_select';
    Element::setAttributes($element, ['id', 'name', 'value']);

    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-entity-select']);

    // Make sure that the fixed defaults are really checked.
    foreach (self::getFixedEntityIds($element) as $id) {
      $element['entity_ids'][$id]['#attributes']['checked'] = 'checked';
    }

    return $element;
  }

  /**
   * Get the endpoint query manager service.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager service.
   */
  private static function getEndpointQueryManager() {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
  }

  /**
   * Get the plan entities query service.
   *
   * @param int $plan_id
   *   The plan id for which a query should be build.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   *   The plan entities query plugin.
   */
  public static function getPlanEntitiesQuery($plan_id) {
    $query_handler = self::getEndpointQueryManager()->createInstance('plan_entities_query');
    $query_handler->setPlaceholder('plan_id', $plan_id);
    return $query_handler;
  }

  /**
   * Transform a list of entities (GVE or PE) into an options list.
   *
   * By default this creates an options list suitable for tableselecct elements.
   * By providing a label callback, it is possible to make this a flat array.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   Array of entity objects.
   *
   * @return array
   *   Either an array containing arrays, or a simple flat array.
   */
  public static function getEntityOptionsFromEntities(array $entities) {
    $entity_options = [];
    if (!empty($entities)) {
      foreach ($entities as $entity) {
        $entity_options[$entity->id()] = [
          'id' => $entity->id(),
          'name' => $entity->getFullName(),
          'description' => $entity->description ?? '',
        ];
      }
    }
    return $entity_options;
  }

  /**
   * Sort an entity options array by the structure of the given plan data.
   *
   * @param array $entity_options
   *   A flat array of entity objects, keyed by the entity id.
   * @param object $plan_data
   *   The plan data.
   * @param object $context_node
   *   An optional context node object. If given this will set the high level
   *   group of the resulting sorted options array to the level represented by
   *   this node.
   * @param bool $hierarchical
   *   If TRUE, the resulting structure will reflect the hierarchy of the PLEs
   *   by also adding child items, if FALSE the options will be limited to the
   *   top most PLEs in the hierarchy.
   * @param bool $label_only
   *   Whether to return labels only per option, or the full value that is
   *   passed in per item in $entity_options.
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $ple_structure
   *   An optional preset of the PLE structure or parts of it. This is mainly
   *   used to support recursion here when a context node has been given.
   *
   * @return array
   *   Array with all entities of the plan, represented in a
   *   multi-dimensional, hierarchical structure.
   */
  public static function sortEntitiesByPlanStructure(array $entity_options, $plan_data, $context_node = NULL, $hierarchical = FALSE, $label_only = TRUE, array $ple_structure = NULL) {
    $ple_structure = $ple_structure ?? PlanStructureHelper::getPlanEntityStructure($plan_data);

    $context_entity_original_id = $context_node ? NodeHelper::getOriginalIdFromNode($context_node) : NULL;

    // Create a nested option list based on the plan structure.
    $options = [];
    foreach ($ple_structure as $first_level_item) {
      if ($first_level_item instanceof GoverningEntity && $context_entity_original_id !== NULL && $first_level_item->id() == $context_entity_original_id && !empty($first_level_item->getChildren()) && empty($entity_options[$context_entity_original_id])) {
        // Only for GVE subpages, if we are on a plan subpage we don't want the
        // entities to be grouped under the root level GVE as per the plan
        // structure, but we want to start one level lower. So we call this
        // function again with the childs of this first level item as a default
        // PLE structure.
        $options = self::sortEntitiesByPlanStructure($entity_options, $plan_data, $context_node, $hierarchical, $label_only, $first_level_item->getChildren());
      }
      else {
        if (empty($options[$first_level_item->group_name])) {
          $options[$first_level_item->group_name] = [];
        }
        if (!empty($entity_options[$first_level_item->id()])) {
          $options[$first_level_item->group_name][$first_level_item->id()] = $label_only ? $first_level_item->getEntityName() : $entity_options[$first_level_item->id()];
        }
        $children = $first_level_item->getChildren();
        if (!empty($children) && $hierarchical) {
          $options[$first_level_item->group_name] += self::addChildItemsToOptionsGroup($first_level_item, $entity_options, 1, !$label_only);
        }
      }
    }

    // Abort if nothing is there.
    if (empty($options)) {
      return $options;
    }

    // Now filter out empty sections.
    foreach ($options as $key => $items) {
      if (empty($items)) {
        unset($options[$key]);
      }
    }
    return $options;
  }

  /**
   * Recursively add child entities to an option group.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface $parent_item
   *   The parent item object.
   * @param array $entity_options
   *   An entity options array.
   * @param int $level
   *   The current level of processing.
   * @param bool $full_childs
   *   Whether to add the full child object or only the label.
   *
   * @return array
   *   An array containing all child entities.
   */
  public static function addChildItemsToOptionsGroup(EntityObjectInterface $parent_item, array $entity_options, $level = 1, $full_childs = FALSE) {
    if (empty($parent_item->getChildren())) {
      return [];
    }
    $options = [];
    foreach ($parent_item->getChildren() as $child_item) {
      if (!empty($entity_options[$child_item->id()])) {
        if ($full_childs) {
          $options[$child_item->id()] = $entity_options[$child_item->id()];
        }
        else {
          $options[$child_item->id()] = str_repeat('—', $level) . ' ' . $child_item->getEntityName();
        }
      }
      if (!empty($child_item->getChildren())) {
        $options += self::addChildItemsToOptionsGroup($child_item, $entity_options, $level + 1, $full_childs);
      }
    }

    // Now filter out empty sections.
    foreach ($options as $key => $items) {
      if (empty($items)) {
        unset($options[$key]);
      }
    }
    return $options;
  }

}
