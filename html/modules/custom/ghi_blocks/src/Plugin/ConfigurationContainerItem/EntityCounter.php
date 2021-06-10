<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Query\PlanEntitiesQuery;

/**
 * Provides an entity counter item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "entity_counter",
 *   label = @Translation("Entity counter"),
 * )
 */
class EntityCounter extends ConfigurationContainerItemPluginBase {

  /**
   * The plan entities query.
   *
   * @var \Drupal\ghi_plans\Query\PlanEntitiesQuery
   */
  public $planEntitiesQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PlanEntitiesQuery $plan_entities_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->planEntitiesQuery = $plan_entities_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ghi_plans.plan_entities_query'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#description'] = $this->t('Leave empty to use a default label');

    $entity_type_options = $this->getEntityTypeOptions();
    $entity_type = $this->getSubmittedOptionsValue($element, $form_state, 'entity_type', $entity_type_options);

    $entity_prototype_options = $this->getEntityPrototypeOptions($entity_type);
    $entity_prototype = $this->getSubmittedOptionsValue($element, $form_state, 'entity_prototype', $entity_prototype_options);

    $element['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $entity_type_options,
      '#default_value' => $entity_type,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#weight' => 0,
    ];

    $element['entity_prototype'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity prototype'),
      '#options' => $entity_prototype_options,
      '#default_value' => $entity_prototype,
      '#validated' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#weight' => 1,
    ];

    $element['label']['#weight'] = 2;
    $element['label']['#placeholder'] = $this->getDefaultLabel($entity_type, $entity_prototype);

    // Add a preview.
    $element['value_preview'] = [
      '#type' => 'item',
      '#title' => $this->t('Value preview'),
      '#markup' => $this->getValue($entity_type, $entity_prototype),
      '#weight' => 3,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if (!empty($this->config['label'])) {
      return $this->config['label'];
    }
    $entity_type = $this->get['entity_type'];
    $entity_prototype = $this->get('entity_prototype');
    return $this->getDefaultLabel($entity_type, $entity_prototype);
  }

  /**
   * Get a default label.
   *
   * @return string|null
   *   A default label or NULL.
   */
  public function getDefaultLabel($entity_type = NULL, $entity_prototype = NULL) {
    $entity_type = $entity_type ?: $this->get('entity_type');
    $entity_prototype = $entity_prototype ?: $this->get('entity_prototype');
    $entity_prototype_options = $this->getEntityPrototypeOptions($entity_type);
    return !empty($entity_prototype_options[$entity_prototype]) ? $entity_prototype_options[$entity_prototype] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($entity_type = NULL, $entity_prototype = NULL) {
    $entity_type = $entity_type ?? $this->get('entity_type');
    $entity_prototype = $entity_prototype ?? $this->get('entity_prototype');
    $matching_entities = array_filter($this->getEntities($entity_type), function ($entity) use ($entity_prototype) {
      return $entity->prototype_id == $entity_prototype;
    });
    return count($matching_entities);
  }

  /**
   * Get entities of the specified type.
   *
   * @param string $entity_type
   *   Can be either "plan" or "governing".
   *
   * @return array
   *   An array of entity objects.
   */
  private function getEntities($entity_type) {
    $context = $this->getContext();
    return $this->planEntitiesQuery->getPlanEntities($context['page_node'], $entity_type, NULL);
  }

  /**
   * Get the options for the entity type dropdown.
   *
   * @return array
   *   An options array suitable to be used in a select element.
   */
  private function getEntityTypeOptions() {
    return [
      'plan' => $this->t('Plan entities'),
      'governing' => $this->t('Governing entities'),
    ];
  }

  /**
   * Get the options for the entity prototype dropdown.
   *
   * @param string $entity_type
   *   Can be either "plan" or "governing".
   *
   * @return array
   *   An options array suitable to be used in a select element.
   */
  private function getEntityPrototypeOptions($entity_type) {
    $entity_prototype_options = [];
    $weight = [];
    foreach ($this->getEntities($entity_type) as $entity) {
      $prototype_id = $entity->prototype_id;
      if (empty($entity_prototype_options[$prototype_id])) {
        $name = $entity->plural_name;
        $entity_prototype_options[$prototype_id] = $name;
        $weight[$prototype_id] = $entity->order_number;
      }
    }

    uksort($entity_prototype_options, function ($prototype_id_a, $prototype_id_b) use ($weight) {
      return $weight[$prototype_id_a] > $weight[$prototype_id_b];
    });
    return $entity_prototype_options;
  }

}
