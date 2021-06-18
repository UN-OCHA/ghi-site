<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Provides a 'PlanEntityTypes' block.
 *
 * @Block(
 *  id = "plan_entity_types",
 *  admin_label = @Translation("Entity Types"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = {
 *      "service" = "ghi_plans.plan_entities_query"
 *    },
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  }
 * )
 */
class PlanEntityTypes extends GHIBlockBase implements AutomaticTitleBlockInterface, SyncableBlockInterface {

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config) {
    return [
      'label' => '',
      'label_display' => TRUE,
      'hpc' => [
        'entity_ids' => property_exists($config, 'entity_ids') ? (array) $config->entity_ids : [],
        'entity_ref_code' => $config->entity_type,
        'id_type' => $config->id_type,
        'sort' => $config->sort,
        'sort_column' => $config->sort_column,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    // Get the entities to render.
    $entities = $this->getRenderableEntities();
    $first_entity = !empty($entities) ? reset($entities) : NULL;
    return $first_entity ? $first_entity->plural_name : NULL;
  }

  /**
   * Retrieve the renderable entities for this instance.
   *
   * @return array
   *   An array of preprocessed HPC entities.
   */
  private function getRenderableEntities() {
    $conf = $this->getBlockConfig();
    if (empty($conf['entity_ref_code'])) {
      return NULL;
    }

    $matching_entities = $this->getPlanEntities($conf['entity_ref_code']);
    $valid_entities = $this->getValidPlanEntities($matching_entities, $conf);
    if (empty($valid_entities)) {
      // Nothing to render.
      return NULL;
    }
    return $valid_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    // Get the entities to render.
    $entities = $this->getRenderableEntities();
    if (empty($entities)) {
      return;
    }

    // Get the config.
    $conf = $this->getBlockConfig();

    // Assemble the list.
    $items = $this->buildPlanEntityItemList($entities, $conf);
    if (empty($items)) {
      return;
    }

    // Render the individual items.
    $rendered_items = array_map(function ($item) {
      return Markup::create('<p class="entity-type-title">' . $item['id'] . '</p>' . $item['description']);
    }, $items);
    $count = count($rendered_items);

    return [
      '#theme' => 'item_list',
      '#items' => $rendered_items,
      '#attributes' => [
        'class' => ['plan-entity-types', $count >= 5 ? 'up-5' : 'up-' . $count],
      ],
      '#prefix' => '<div class="entity-type-description">',
      '#suffix' => '</div>',
    ];
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'entity_ids' => [],
      'entity_ref_code' => NULL,
      'id_type' => NULL,
      'sort' => FALSE,
      'sort_column' => NULL,
    ];
  }

  /**
   * Form builder for the config form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $entity_ref_code_options = $this->getEntityRefCodeOptions();

    // Get the defaults for easier access.
    $defaults = [
      'entity_ref_code' => $this->getDefaultFormValueFromFormState($form_state, 'entity_ref_code') ?: array_key_first($entity_ref_code_options),
      'id_type' => $this->getDefaultFormValueFromFormState($form_state, 'id_type') ?: NULL,
      'sort' => $this->getDefaultFormValueFromFormState($form_state, 'sort') ?: NULL,
      'sort_column' => $this->getDefaultFormValueFromFormState($form_state, 'sort_column') ?: NULL,
      'entity_ids' => $this->getDefaultFormValueFromFormState($form_state, 'entity_ids') ?: NULL,
    ];

    $form['entity_ref_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('The type of plan entity to show, e.g. <em>Cluster Objective</em> or <em>Strategic Objective</em>'),
      '#options' => $entity_ref_code_options,
      '#default_value' => $defaults['entity_ref_code'],
      '#required' => count($entity_ref_code_options),
      '#disabled' => empty($entity_ref_code_options),
    ];
    $form['id_type'] = [
      '#type' => 'select',
      '#title' => $this->t('ID type'),
      '#description' => $this->t('Define how the ID element should be constructed. See the table below for a preview of the data.'),
      '#options' => [
        'custom_id' => $this->t('Custom ID'),
        'custom_id_prefixed_refcode' => $this->t('Custom ID, prefixed with object type (CA, CO, SO, ...)'),
        'composed_reference' => $this->t('Composed reference'),
      ],
      '#default_value' => $defaults['id_type'],
      '#disabled' => empty($entity_ref_code_options),
    ];

    // If we have a plan context, add checkboxes to select individual entities.
    if ($this->getCurrentPlanId() && count($entity_ref_code_options)) {
      $wrapper_id = 'hpc-plan-entities-wrapper';

      // Bind ajax callback for auto-update of available entities when the type
      // is changed.
      $form['entity_ref_code']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateAjax'],
        'wrapper' => $wrapper_id,
        'array_parents' => array_merge($form['#array_parents'], ['entity_ids']),
      ];

      $form['id_type']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateAjax'],
        'wrapper' => $wrapper_id,
        'array_parents' => array_merge($form['#array_parents'], ['entity_ids']),
      ];

      $matching_entities = [];
      $entity_options = [];

      $matching_entities = $this->getPlanEntities($defaults['entity_ref_code']);
      if (count($matching_entities)) {
        // Assemble the list.
        $entity_options = $this->buildPlanEntityItemList($matching_entities, $defaults, TRUE);
      }

      $form['sort'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Sort the data'),
        '#default_value' => $defaults['sort'],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateAjax'],
          'wrapper' => $wrapper_id,
          'array_parents' => array_merge($form['#array_parents'], ['entity_ids']),
        ],
      ];
      $form['sort_column'] = [
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
            ':input[name="basic[sort]"]' => ['checked' => TRUE],
          ],
        ],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateAjax'],
          'wrapper' => $wrapper_id,
          'array_parents' => array_merge($form['#array_parents'], ['entity_ids']),
        ],
      ];

      $form['entity_ids_header'] = [
        '#type' => 'markup',
        '#markup' => $this->t('If you do not want to show all entities of this type, select the ones that should be visible below. If no entity is selected, all entities will be shown. Please note that some rows might not be available for selection because of incomplete data sets. These will also be hidden from public display.'),
        '#prefix' => '<div>',
        '#suffix' => '</div><br />',
      ];

      $form['entity_ids'] = [
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
          $form['entity_ids'][$entity->id]['#disabled'] = TRUE;
        }
      }
    }
    $form['#preview_button_hidden'] = FALSE;

    return $form;
  }

  /**
   * Get options for the entity type dropdown.
   *
   * @return array
   *   An array with valid options for the current context.
   */
  private function getEntityRefCodeOptions() {
    if (!$this->getCurrentPlanId()) {
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
        $name = $entity->plural_name;
        $options[$ref_code] = $name;
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
   * @param string $entity_ref_code
   *   The entity type to restrict the context.
   *
   * @return array
   *   An array of plan entity objects for the current context.
   */
  private function getPlanEntities($entity_ref_code = NULL) {
    $page_node = $this->getPageNode();
    $filter = NULL;
    if ($entity_ref_code) {
      $filter = ['ref_code' => $entity_ref_code];
    }
    /** @var \Drupal\ghi_plans\Query\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    return $query->getPlanEntities($page_node, 'plan', $filter);
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
