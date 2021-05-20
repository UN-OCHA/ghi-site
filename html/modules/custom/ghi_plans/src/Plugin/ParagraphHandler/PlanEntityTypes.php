<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\hpc_api\Helpers\ApiEntityHelper;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Entity types.
 *
 * @ParagraphHandler(
 *   id = "plan_entity_types",
 *   label = @Translation("Plan entity types"),
 *   data_sources = {
 *     "data" = {
 *       "service" = "ghi_plans.plan_entities_query"
 *     },
 *   },
 * )
 */
class PlanEntityTypes extends PlanBaseClass implements SyncableParagraphInterface {

  /**
   * {@inheritdoc}
   */
  const KEY = 'plan_entity_types';

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config) {
    return [
      'entity_ids' => (array) $config->entity_ids,
      'entity_type' => $config->entity_type,
      'id_type' => $config->id_type,
      'sort' => $config->sort,
      'sort_column' => $config->sort_column,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSourceElementKey() {
    return 'plan_entity_types';
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, array $element) {
    parent::preprocess($variables, $element);

    if (!isset($this->parentEntity->field_original_id) || $this->parentEntity->field_original_id->isEmpty()) {
      return;
    }

    $config = $this->getConfig();

    if (empty($config['entity_ids'])) {
      return;
    }

    $matching_entities = $this->getPlanEntities($config['entity_type']);

    if (empty($matching_entities)) {
      // Nothing to render.
      return;
    }

    $valid_entities = $this->getValidPlanEntities($matching_entities, $config);
    if (empty($valid_entities)) {
      // Nothing to render.
      return;
    }

    $first_entity = reset($matching_entities);

    // Assemble the list.
    $items = $this->buildPlanEntityItemList($valid_entities, $config);
    if (empty($items)) {
      return;
    }

    // Render the individual items.
    $rendered_items = array_map(function ($item) {
      return Markup::create('<p class="entity-type-title">' . $item['id'] . '</p>' . $item['description']);
    }, $items);
    $count = count($rendered_items);

    $variables['content'][] = [
      '#theme' => 'item_list',
      '#title' => ApiEntityHelper::getEntityPrototypeName($first_entity),
      '#items' => $rendered_items,
      '#attributes' => [
        'class' => ['plan-entity-types', $count >= 5 ? 'up-5' : 'up-' . $count],
      ],
      '#prefix' => '<div class="entity-type-description">',
      '#suffix' => '</div>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function widgetAlter(&$element, &$form_state, $context) {
    parent::widgetAlter($element, $form_state, $context);

    if (!isset($this->parentEntity->field_original_id) || $this->parentEntity->field_original_id->isEmpty()) {
      return;
    }

    $config = $this->getConfig($form_state);
    $plan_id = $this->parentEntity->field_original_id->value;

    $entity_type_options = $this->getEntityTypeOptions();
    $entity_type_option_keys = array_keys($entity_type_options);

    // Get the defaults for easier access.
    $defaults = [
      'entity_type' => $config['entity_type'] ?: reset($entity_type_option_keys),
      'id_type' => $config['id_type'] ?: NULL,
      'sort' => $config['sort'] ?: NULL,
      'sort_column' => $config['sort_column'] ?: NULL,
      'entity_ids' => $config['entity_ids'] ?: NULL,
    ];

    $subform = &$element['subform'];
    $subform_parents = $subform['#parents'];

    $subform['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('The type of plan entity to show, e.g. <em>Cluster Objective</em> or <em>Strategic Objective</em>'),
      '#options' => $entity_type_options,
      '#default_value' => $defaults['entity_type'],
      '#required' => count($entity_type_options),
      '#disabled' => empty($entity_type_options),
    ];
    $subform['id_type'] = [
      '#type' => 'select',
      '#title' => $this->t('ID type'),
      '#description' => $this->t('Define how the ID element should be constructed. See the table below for a preview of the data.'),
      '#options' => [
        'custom_id' => $this->t('Custom ID'),
        'custom_id_prefixed_refcode' => $this->t('Custom ID, prefixed with object type (CA, CO, SO, ...)'),
        'composed_reference' => $this->t('Composed reference'),
      ],
      '#default_value' => $defaults['id_type'],
      '#disabled' => empty($entity_type_options),
    ];

    // If we have a plan context, add checkboxes to select individual entities.
    if ($plan_id && count($entity_type_options)) {
      $wrapper_id = 'hpc-plan-entities-wrapper';

      // Bind ajax callback for auto-update of available entities when the type
      // is changed.
      $subform['entity_type']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateEntitiesList'],
        'wrapper' => $wrapper_id,
      ];

      $subform['id_type']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateEntitiesList'],
        'wrapper' => $wrapper_id,
      ];

      $matching_entities = [];
      $entity_options = [];

      $matching_entities = $this->getPlanEntities($defaults['entity_type']);
      if (count($matching_entities)) {
        // Assemble the list.
        $entity_options = $this->buildPlanEntityItemList($matching_entities, $defaults, TRUE);
      }

      $subform['sort'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Sort the data'),
        '#default_value' => $defaults['sort'],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateEntitiesList'],
          'wrapper' => $wrapper_id,
        ],
      ];
      $subform['sort_column'] = [
        '#type' => 'select',
        '#title' => $this->t('Sort column'),
        '#options' => [
          'id_' . EndpointQuery::SORT_ASC => $this->t('ID (asc)'),
          'id_' . EndpointQuery::SORT_DESC => $this->t('ID (desc)'),
          'description_' . EndpointQuery::SORT_ASC => $this->t('Description (asc)'),
          'description_' . EndpointQuery::SORT_DESC => $this->t('Description (desc)'),
        ],
        '#default_value' => $defaults['sort_column'],
        '#states' => [
          'visible' => [
            ':input[name="' . $subform_parents[0] . '[' . implode('][', array_slice($subform_parents, 1)) . '][sort]"]' => ['checked' => TRUE],
          ],
        ],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateEntitiesList'],
          'wrapper' => $wrapper_id,
        ],
      ];

      $subform['entity_ids_header'] = [
        '#type' => 'markup',
        '#markup' => $this->t('If you do not want to show all entities of this type, select the ones that should be visible below. If no entity is selected, all entities will be shown. Please note that some rows might not be available for selection because of incomplete data sets. These will also be hidden from public display.'),
        '#prefix' => '<div>',
        '#suffix' => '</div><br />',
      ];

      $subform['entity_ids'] = [
        '#type' => 'tableselect',
        '#header' => [
          'id' => $this->t('ID'),
          'description' => $this->t('Description'),
        ],
        '#options' => $entity_options,
        '#default_value' => !empty($defaults['entity_ids']) ? array_combine($defaults['entity_ids'], $defaults['entity_ids']) : [],
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#empty' => $this->t('No suitable plan entities found. If you save this form like this, the block will not be displayed.'),
      ];

      if (count($matching_entities)) {
        $validation_options = $defaults;
        $validation_options['entity_ids'] = NULL;
        foreach ($matching_entities as $entity) {
          if ($this->validatePlanEntity($entity, $validation_options)) {
            continue;
          }
          $subform['entity_ids'][$entity->id]['#disabled'] = TRUE;
        }
      }
    }
  }

  /**
   * Ajax callback to update the entities list.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return mixed
   *   The part of the form array that should be updated.
   */
  public function updateEntitiesList(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    array_pop($parents);
    $parents[] = 'entity_ids';
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Get options for the entity type dropdown.
   *
   * @return array
   *   An array with valid options for the current context.
   */
  private function getEntityTypeOptions() {
    $plan_id = $this->parentEntity->field_original_id->value;

    if (!$plan_id) {
      // Without a plan context, we show a default set of availabe plan entity
      // types.
      $default_options = [
        'CA' => $this->t('Cluster Activities'),
        'CO' => $this->t('Cluster Objectives'),
        'CQ' => $this->t('Humanitarian Consequences'),
        'SO' => $this->t('Strategic Objectives'),
        'SP' => $this->t('Specific Objectives'),
        'SSO' => $this->t('Sub Strategic Objectives'),
        'OC' => $this->t('Outcomes'),
        'OP' => $this->t('Outputs'),
      ];
      return $default_options;
    }

    $matching_entities = $this->getPlanEntities();

    $options = [];
    if (empty($matching_entities)) {
      return $options;
    }
    $weight = [];
    foreach ($matching_entities as $entity) {
      $ref_code = $entity->ref_code;
      if (empty($options[$ref_code])) {
        $options[$ref_code] = $entity->plural_name;
        $weight[$ref_code] = $entity->order_number;
      }
    }
    uksort($options, function ($ref_code_a, $ref_code_b) use ($weight) {
      return $weight[$ref_code_a] > $weight[$ref_code_b];
    });
    return $options;
  }

  /**
   * Get available plan entities for the current context.
   *
   * @param string $entity_type
   *   The entity type to restrict the context.
   *
   * @return array
   *   An array of plan entity objects for the current context.
   */
  private function getPlanEntities($entity_type = NULL) {
    return $this->getQueryHandler()->getPlanEntities($this->parentEntity, $entity_type);
  }

  /**
   * Build the item list for rendering.
   *
   * @param array $entities
   *   The entity objects to build.
   * @param array $conf
   *   The current element configuration used to apply formatting.
   * @param bool $truncate_description
   *   Flag indicating whether to truncate the description or not.
   *
   * @return array
   *   An array of prepared entity items, keyed by the entity id, each item is
   *   an array that has 2 keys: id and description.
   */
  private function buildPlanEntityItemList(array $entities, array $conf, $truncate_description = FALSE) {
    $items = [];
    $id_type = !empty($conf['id_type']) ? $conf['id_type'] : 'custom_id';
    foreach ($entities as $entity) {
      $description = $entity->description;
      $items[$entity->id] = [
        'id' => $entity->$id_type,
        'description' => $truncate_description ? Unicode::truncate($description, 120, TRUE, TRUE) : $description,
      ];
    }
    if (!empty($conf['sort'])) {
      list($key, $sort) = explode('_', $conf['sort_column']);
      uasort($items, function ($a, $b) use ($key, $sort) {
        $a_value = !empty($a[$key]) ? $a[$key] : 0;
        $b_value = !empty($b[$key]) ? $b[$key] : 0;
        if ($sort == EndpointQuery::SORT_ASC) {
          return strnatcmp($a_value, $b_value);
        }
        if ($sort == EndpointQuery::SORT_DESC) {
          return strnatcmp($b_value, $a_value);
        }
      });
    }
    return $items;
  }

  /**
   * Get entities that are valid for display.
   *
   * @param array $entities
   *   The entity objects to check.
   * @param array $conf
   *   The current element configuration used to apply validation.
   *
   * @return array
   *   An array with the entity objects that passed validation.
   */
  private function getValidPlanEntities(array $entities, array $conf) {
    $valid_entities = [];
    if (empty($entities)) {
      return $valid_entities;
    }
    foreach ($entities as $entity) {
      if (!$this->validatePlanEntity($entity, $conf)) {
        continue;
      }
      $valid_entities[] = $entity;
    }
    return $valid_entities;
  }

  /**
   * Validate that the given entity is valid for display.
   *
   * @param object $entity
   *   An entity object.
   * @param array $conf
   *   The current element configuration used to apply validation.
   *
   * @return object
   *   True if the entity passed validation, False otherwhise.
   */
  private function validatePlanEntity($entity, array $conf) {
    $entity_ids = !empty($conf['entity_ids']) ? array_filter($conf['entity_ids']) : [];
    if (!empty($entity_ids) && !in_array($entity->id, $entity_ids)) {
      return FALSE;
    }
    if (empty($entity->description)) {
      return FALSE;
    }

    $id_type = !empty($conf['id_type']) ? $conf['id_type'] : 'custom_id';
    if (empty($entity->$id_type)) {
      return FALSE;
    }
    return TRUE;
  }

}
