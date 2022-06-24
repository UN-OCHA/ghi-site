<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\node\NodeInterface;

/**
 * Provides an entity counter item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "entity_name",
 *   label = @Translation("Entity name"),
 *   description = @Translation("This item displays the name of the entities that this block displays."),
 * )
 */
class EntityName extends ConfigurationContainerItemPluginBase {

  const SORT_TYPE = 'alfa';
  const DATA_TYPE = 'string';
  const ITEM_TYPE = 'name';

  /**
   * The icon query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQueryManager $endpoint_query_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query_manager);

    $this->iconQuery = $this->endpointQueryManager->createInstance('icon_query');
  }

  /**
   * Get a default label.
   *
   * @return string
   *   A default label.
   */
  public function getDefaultLabel() {
    return $this->t('Cluster');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $entity = $this->getContextValue('entity');
    if (!$entity) {
      return NULL;
    }
    // This should work for Api entity objects.
    if ($entity instanceof EntityObjectInterface) {
      /** @var \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface $entity */
      return $entity->getEntityName();
    }
    return $entity->name ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    /** @var \Drupal\node\NodeInterface $context_node */
    $context_node = $this->getContextValue('context_node');
    /** @var \Drupal\node\NodeInterface $section_node */
    $section_node = $this->getContextValue('section_node');
    $entity = $this->getContextValue('entity');
    if (!$entity) {
      return NULL;
    }
    $entity_name = $this->getValue();
    if (empty($entity->icon)) {
      return $entity_name;
    }

    $icon_embed = $this->iconQuery->getIconEmbedCode($entity->icon);
    $markup = [
      '#markup' => Markup::create($icon_embed . '<span class="name">' . $entity_name . '</span>'),
    ];
    if ($section_node && $section_node->access('view')) {
      return Link::fromTextAndUrl($markup, $section_node->toUrl())->toRenderable();
    }
    elseif ($context_node && $context_node instanceof NodeInterface && $context_node->access('view')) {
      return Link::fromTextAndUrl($markup, $context_node->toUrl())->toRenderable();
    }
    else {
      return $markup;
    }
  }

}
