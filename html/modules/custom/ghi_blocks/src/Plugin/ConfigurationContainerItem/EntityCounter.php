<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an entity counter item for configuration containers.
 *
 * This item type allows the following options when using as part of a
 * configuration container:
 * - entity_type: Sets a preselected entity type and hides the entity type
 *   select element.
 * - value_preview: If set and set to FALSE, will hide the value preview.
 *
 * @ConfigurationContainerItem(
 *   id = "entity_counter",
 *   label = @Translation("Entity counter"),
 *   description = @Translation("This item displays the number of entities of a specific type."),
 * )
 */
class EntityCounter extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemValuePreviewTrait;

  /**
   * The plan entities query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   */
  public $planEntitiesQuery;

  /**
   * The icon query.
   *
   * @var \Drupal\hpc_api\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->iconQuery = $instance->endpointQueryManager->createInstance('icon_query');
    $instance->planEntitiesQuery = $instance->endpointQueryManager->createInstance('plan_entities_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $entity_type_options = $this->getEntityTypeOptions();

    $preset_entity_type = $this->getEntityTypePreset();
    $entity_type = !$preset_entity_type ? $this->getSubmittedOptionsValue($element, $form_state, 'entity_type', $entity_type_options) : $preset_entity_type;

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
    if ($preset_entity_type) {
      $element['entity_type']['#type'] = 'hidden';
      $element['entity_type']['#value'] = $entity_type;
      $element['entity_type']['#default_value'] = $entity_type;
    }

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
    if ($this->shouldDisplayPreview()) {
      $preview_value = $this->getValue($entity_type, $entity_prototype);
      $element['value_preview'] = $this->buildValuePreviewFormElement($preview_value);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if (!empty($this->config['label'])) {
      return $this->config['label'];
    }
    $entity_type = $this->get('entity_type');
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
    $matching_entities = $this->getMatchingEntities($entity_type, $entity_prototype);
    return count($matching_entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $popover = $this->getPopover();
    if (!$popover) {
      return parent::getRenderArray();
    }
    return [
      '#type' => 'container',
      0 => parent::getRenderArray(),
      1 => $popover,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = parent::getClasses();
    $classes[] = Html::getClass($this->getPluginId() . '--' . $this->get('entity_type'));
    return $classes;
  }

  /**
   * Get a popover trigger.
   */
  private function getPopover() {

    $entity = $this->getContextValue('entity');

    // Get the icon if there is any.
    $icon = NULL;
    if ($entity && !empty($entity->icon)) {
      $icon = $this->iconQuery->getIconEmbedCode($entity->icon);
    }

    $popover_content = NULL;
    $entities = $this->getMatchingEntities();
    if (!empty($entities)) {
      usort($entities, function ($a, $b) {
        return strnatcmp($a->name, $b->name);
      });
      $items = array_map(function ($item) {
        return Markup::create($item->name . '<br /> ' . $item->description);
      }, $entities);

      $popover_content = [
        '#theme' => 'item_list',
        '#items' => $items,
        '#list_type' => 'ol',
        '#gin_lb_theme_suggestions' => FALSE,
      ];
    }

    return [
      '#theme' => 'hpc_popover',
      '#title' => Markup::create($icon . '<span class="name">' . $this->getLabel() . '</span>'),
      '#content' => $popover_content,
      '#class' => 'entity-counter entity-counter-popover',
      '#material_icon' => 'view_list',
      '#disabled' => empty($popover_content),
    ];
  }

  /**
   * Get the matching entities for this item.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_prototype
   *   The entity prototype.
   *
   * @return array
   *   An array of entity objects.
   */
  private function getMatchingEntities($entity_type = NULL, $entity_prototype = NULL) {
    $entity_type = $entity_type ?? $this->get('entity_type');
    $entity_prototype = $entity_prototype ?? $this->get('entity_prototype');
    $entities = $this->getEntities($entity_type);
    if (empty($entities)) {
      return [];
    }
    return array_filter($entities, function ($entity) use ($entity_prototype) {
      return $entity->entity_prototype_id == $entity_prototype;
    });
  }

  /**
   * Get entities of the specified type.
   *
   * @param string $entity_type
   *   Can be either "plan" or "governing".
   *
   * @return array|null
   *   An array of entity objects or NULL.
   */
  private function getEntities($entity_type) {
    $context = $this->getContext();
    if (empty($context['base_object']) || !$context['base_object'] instanceof ContentEntityInterface) {
      return [];
    }
    return $this->planEntitiesQuery->getPlanEntities($context['base_object'], $entity_type, NULL);
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
    foreach ($this->getEntities($entity_type) ?? [] as $entity) {
      $prototype_id = $entity->entity_prototype_id;
      if (empty($entity_prototype_options[$prototype_id])) {
        $name = $entity->plural_name;
        $entity_prototype_options[$prototype_id] = $name;
        $weight[$prototype_id] = $entity->order_number;
      }
    }

    uksort($entity_prototype_options, function ($prototype_id_a, $prototype_id_b) use ($weight) {
      return $weight[$prototype_id_a] - $weight[$prototype_id_b];
    });
    return $entity_prototype_options;
  }

  /**
   * Get the preset entity type if one is set.
   *
   * @return string|null
   *   The preset entity type or NULL.
   */
  private function getEntityTypePreset() {
    $plugin_configuration = $this->getPluginConfiguration();
    $entity_type_options = $this->getEntityTypeOptions();
    if (!array_key_exists('entity_type', $plugin_configuration) || !array_key_exists($plugin_configuration['entity_type'], $entity_type_options)) {
      return NULL;
    }
    return $plugin_configuration['entity_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    $errors = [];
    $entity_type = $this->get('entity_type') ?? NULL;
    $entity_prototype = $this->get('entity_prototype') ?? NULL;
    if ($entity_type) {
      $entity_prototype_options = $this->getEntityPrototypeOptions($entity_type);
      if (!array_key_exists($entity_prototype, $entity_prototype_options)) {
        $errors[] = $this->t('Configured entity prototype is not available in the context of the current plan');
      }
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigurationErrors() {
    $entity_type = $this->get('entity_type') ?? NULL;
    $entity_prototype = $this->get('entity_prototype') ?? NULL;
    if (!$entity_type) {
      return;
    }
    $entity_prototype_options = $this->getEntityPrototypeOptions($entity_type);

    if (!empty($entity_prototype_options) && !array_key_exists($entity_prototype, $entity_prototype_options)) {
      $this->set('entity_prototype', array_key_first($entity_prototype_options));
    }
  }

}
