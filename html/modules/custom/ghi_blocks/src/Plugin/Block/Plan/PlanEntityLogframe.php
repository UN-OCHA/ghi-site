<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Provides a 'PlanEntityLogframe' block.
 *
 * @Block(
 *  id = "plan_entity_logframe",
 *  admin_label = @Translation("Entity Logframe"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "entity" = "entity_query",
 *    "attachment" = "attachment_query",
 *    "attachment_search" = "attachment_search_query",
 *    "attachment_prototype" = "attachment_prototype_query",
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "entities" = {
 *      "title" = @Translation("Entities"),
 *      "callback" = "entitiesForm"
 *    },
 *    "tables" = {
 *      "title" = @Translation("Tables"),
 *      "callback" = "tablesForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class PlanEntityLogframe extends GHIBlockBase implements MultiStepFormBlockInterface, ConfigurableTableBlockInterface, OverrideDefaultTitleBlockInterface {

  use ConfigurationContainerTrait;
  use AttachmentTableTrait;

  /**
   * {@inheritdoc}
   */
  public function getDefaultTitle() {
    // Get the entities to render.
    $entities = $this->getRenderableEntities();
    $first_entity = !empty($entities) ? reset($entities) : NULL;
    return $first_entity ? $first_entity->plural_name : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (empty($conf['entities']['entity_ids'])) {
      return 'entities';
    }
    return 'tables';
  }

  /**
   * Retrieve the renderable entities for this instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[]
   *   An array of preprocessed HPC entities.
   */
  private function getRenderableEntities() {
    $conf = $this->getBlockConfig();
    if (empty($conf['entities']['entity_ref_code'])) {
      return [];
    }

    $matching_entities = $this->getPlanEntities($conf['entities']['entity_ref_code']);
    if (empty($matching_entities)) {
      // Nothing to render.
      return [];
    }
    $valid_entities = $this->getValidPlanEntities($matching_entities, $conf);
    if (empty($valid_entities)) {
      // Nothing to render.
      return [];
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

    $attachments = $this->getAttachmentsForEntities($entities);

    // Get the config.
    $conf = $this->getBlockConfig();

    // Sort the entities.
    $this->sortPlanEntities($entities, $conf['entities']);

    // Assemble the list.
    $rendered_items = [];
    foreach ($entities as $entity) {
      $tables = $this->buildTables($entity, $conf['tables'], $attachments);
      $contributes_heading = $this->buildContributesToHeading($entity);
      $entity_id = $this->getPlanEntityId($entity, $conf['entities']);
      $entity_description = $this->getPlanEntityDescription($entity, $conf['entities']);
      $rendered_items[] = [
        'label' => [
          '#markup' => Markup::create('<p class="label">' . $entity_id . '</p><p class="description">' . $entity_description . '</p>'),
        ],
        'contributes_heading' => $contributes_heading,
        'attachment_tables' => $tables ?: NULL,
      ];
    }
    $count = count($rendered_items);

    return [
      '#theme' => 'item_list',
      '#items' => $rendered_items,
      '#attributes' => [
        'class' => [
          'plan-entity-logframe',
          $count >= 5 ? 'up-5' : 'up-' . $count,
        ],
      ],
      '#gin_lb_theme_suggestions' => FALSE,
    ];
  }

  /**
   * Get the formatted plan entity id according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $entity
   *   The plan entity.
   * @param array $conf
   *   The entity configuration.
   *
   * @return string
   *   The formatted plan entity id.
   */
  private function getPlanEntityId(PlanEntity $entity, array $conf) {
    $id_type = $conf['id_type'] ?? 'custom_id';
    return $entity->getCustomName($id_type);
  }

  /**
   * Get the formatted plan entity description according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $entity
   *   The plan entity.
   * @param array $conf
   *   The entity configuration.
   * @param bool $truncate_description
   *   Whether to truncate the description or not.
   *
   * @return string
   *   The formatted plan entity description.
   */
  private function getPlanEntityDescription(PlanEntity $entity, array $conf, $truncate_description = FALSE) {
    $description = $entity->description;
    return $truncate_description ? Unicode::truncate($description, 120, TRUE, TRUE) : $description;
  }

  /**
   * Get the entity attachment tables according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $entity
   *   The plan entity.
   * @param array $conf
   *   The entity configuration.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[] $attachments
   *   An array of attachments.
   *
   * @return array
   *   An array of entity attachment tables.
   */
  private function buildTables(PlanEntity $entity, array $conf, array $attachments) {
    $tables = [];
    if (empty($conf['attachment_tables'])) {
      return $tables;
    }

    $context = $this->getBlockContext();
    $context['attachments'] = $attachments;
    $context['plan_entity'] = $entity;

    foreach ($conf['attachment_tables'] as $table) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($table, $context);
      $table = $item_type->getRenderArray();
      if (!$table) {
        continue;
      }
      $tables[] = $table;
    }
    if (empty($tables)) {
      return $tables;
    }
    return [
      '#type' => 'container',
      'tables' => $tables,
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
      'entities' => [
        'entity_ids' => NULL,
        'entity_ref_code' => NULL,
        'id_type' => NULL,
        'sort' => FALSE,
        'sort_column' => NULL,
      ],
      'tables' => [
        'attachment_tables' => [],
      ],
    ];
  }

  /**
   * Form builder for the entities form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function entitiesForm(array $form, FormStateInterface $form_state) {
    $entity_ref_code_options = $this->getEntityRefCodeOptions();
    $wrapper_id = Html::getId('form-wrapper-ghi-block-config');
    $ajax_parents = array_merge($form['#array_parents']);
    array_pop($ajax_parents);

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
      '#options' => AttachmentHelper::idTypes(),
      '#default_value' => $defaults['id_type'],
      '#disabled' => empty($entity_ref_code_options),
    ];

    // If we have a plan context, add checkboxes to select individual entities.
    if ($this->getCurrentPlanId() && count($entity_ref_code_options)) {
      // Bind ajax callback for auto-update of available entities when the type
      // is changed.
      $form['entity_ref_code']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateAjax'],
        'wrapper' => $wrapper_id,
        'array_parents' => $ajax_parents,
      ];

      $form['id_type']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateAjax'],
        'wrapper' => $wrapper_id,
        'array_parents' => $ajax_parents,
      ];

      $matching_entities = [];
      $entity_options = [];

      $matching_entities = $this->getPlanEntities($defaults['entity_ref_code']);
      if (count($matching_entities)) {
        $this->sortPlanEntities($matching_entities, $defaults);
        // Assemble the list.
        $entity_options = [];
        foreach ($matching_entities as $entity) {
          $entity_options[$entity->id()] = [
            'id' => $this->getPlanEntityId($entity, $defaults),
            'description' => $this->getPlanEntityDescription($entity, $defaults, TRUE),
          ];
        }
      }

      $form['sort'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Sort the data'),
        '#default_value' => $defaults['sort'],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateAjax'],
          'wrapper' => $wrapper_id,
          'array_parents' => $ajax_parents,
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
            ':input[name="entities[sort]"]' => ['checked' => TRUE],
          ],
        ],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateAjax'],
          'wrapper' => $wrapper_id,
          'array_parents' => $ajax_parents,
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

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'second-level-actions-wrapper',
        ],
      ],
    ];

    $form['actions']['select_entities'] = [
      '#type' => 'submit',
      '#value' => $this->t('Use selected entities'),
      '#element_submit' => [get_class($this) . '::ajaxMultiStepSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $this->getContainerWrapper(),
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'tables',
    ];
    return $form;
  }

  /**
   * Form builder for the tables form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function tablesForm(array $form, FormStateInterface $form_state) {
    $form['attachment_tables'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured attachment tables'),
      '#title_display' => 'invisible',
      '#edit_label' => $this->t('Type'),
      '#item_type_label' => $this->t('Attachment table'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'attachment_tables'),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Table'),
          'prototype' => $this->t('Type'),
          'columns_summary' => $this->t('Columns'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
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
      return $weight[$ref_code_a] - $weight[$ref_code_b];
    });
    return $options;
  }

  /**
   * Get available plan entities for the current context.
   *
   * @param string $entity_ref_code
   *   The entity type to restrict the context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[]
   *   An array of plan entity objects for the current context.
   */
  private function getPlanEntities($entity_ref_code = NULL) {
    $context_object = $this->getCurrentBaseObject();

    $filter = NULL;
    if ($entity_ref_code) {
      $filter = ['ref_code' => $entity_ref_code];
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    $entities = $query->getPlanEntities($context_object, 'plan', $filter);
    // This should give us only PlanEntity objects, but let's make sure.
    $entities = is_array($entities) ? array_filter($entities, function ($entity) {
      return $entity instanceof PlanEntity;
    }) : [];
    return $entities;
  }

  /**
   * Get entities that are valid for display.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[] $entities
   *   The entity objects to check.
   * @param array $conf
   *   The current element configuration used to apply validation.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[]
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
   * Sort entities according to the given configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[] $entities
   *   The entity objects to sort.
   * @param array $conf
   *   The current element configuration used to apply validation.
   */
  private function sortPlanEntities(array &$entities, $conf) {
    if (!empty($conf['sort'])) {
      [$key, $sort] = explode('_', $conf['sort_column']);
      uasort($entities, function ($a, $b) use ($key, $sort, $conf) {
        $a_value = $key == 'id' ? $this->getPlanEntityId($a, $conf) : (!empty(($a)->{$key}) ? ($a)->{$key} : 0);
        $b_value = $key == 'id' ? $this->getPlanEntityId($b, $conf) : (!empty(($b)->{$key}) ? ($b)->{$key} : 0);
        if ($sort == EndpointQuery::SORT_ASC) {
          return strnatcmp($a_value, $b_value);
        }
        if ($sort == EndpointQuery::SORT_DESC) {
          return strnatcmp($b_value, $a_value);
        }
      });
    }
  }

  /**
   * Validate that the given entity is valid for display.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity $entity
   *   An entity object.
   * @param array $conf
   *   The current element configuration used to apply validation.
   *
   * @return object
   *   True if the entity passed validation, False otherwhise.
   */
  private function validatePlanEntity(PlanEntity $entity, array $conf) {
    $entity_ids = !empty($conf['entities']['entity_ids']) ? array_filter($conf['entities']['entity_ids']) : [];
    if (!empty($entity_ids) && !in_array($entity->id(), $entity_ids)) {
      return FALSE;
    }
    if (empty($entity->description)) {
      return FALSE;
    }

    $id_type = $conf['entities']['id_type'] ?? 'custom_id';
    if (empty($entity->getCustomName($id_type))) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    $plan_entities = $this->getRenderableEntities();
    $this->sortPlanEntities($plan_entities, [
      'sort' => TRUE,
      'sort_column' => 'id_ASC',
    ]);
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $this->getPageNode(),
      'entities' => $plan_entities,
      'attachment_prototypes' => $this->getAttachmentPrototypes(),
      'used_attachment_prototypes' => $this->getUsedAttachmentPrototypeIds(),
    ];
  }

  /**
   * Get the ids of already used attachment prototypes.
   *
   * @return int[]
   *   An array of attachment prototype ids.
   */
  private function getUsedAttachmentPrototypeIds() {
    $conf = $this->getBlockConfig()['tables'];
    $attachment_prototype_ids = [];
    foreach ($conf['attachment_tables'] as $table) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($table, []);
      $attachment_prototype_ids[] = $item_type->get('attachment_prototype');
    }
    return $attachment_prototype_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'attachment_table' => [],
    ];
    return $item_types;
  }

  /**
   * Get attachments for the given set of entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The plan entity objects.
   * @param int $prototype_id
   *   An optional prototype id to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of data attachments.
   */
  public function getAttachmentsForEntities(array $entities, $prototype_id = NULL) {
    if (empty($entities)) {
      return NULL;
    }
    $entity_ids = array_map(function ($entity) {
      return $entity->id;
    }, $entities);

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->endpointQueryManager->createInstance('attachment_search_query');
    $attachments = $query->getAttachmentsByObject('planEntity', $entity_ids);
    // Filter out non-data attachments.
    $attachments = array_filter($attachments, function ($attachment) use ($prototype_id) {
      if (!$attachment instanceof DataAttachment) {
        return FALSE;
      }
      if ($prototype_id && $prototype_id == $attachment->getPrototype()->id()) {
        return FALSE;
      }
      return TRUE;
    });
    return $attachments;
  }

  /**
   * Get the available attachment prototypes for the current plan context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   An array of attachment prototypes.
   */
  private function getAttachmentPrototypes() {
    $plan_id = $this->getCurrentPlanId();
    if (!$plan_id) {
      return [];
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentPrototypeQuery $query */
    $query = $this->endpointQueryManager->createInstance('attachment_prototype_query');
    $attachment_prototypes = $query->getDataPrototypesForPlan($plan_id);
    $entities = $this->getRenderableEntities();
    return $this->filterAttachmentPrototypesByPlanEntities($attachment_prototypes, $entities);
  }

}