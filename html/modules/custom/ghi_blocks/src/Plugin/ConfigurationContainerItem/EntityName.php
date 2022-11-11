<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an entity name item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "entity_name",
 *   label = @Translation("Entity name"),
 *   description = @Translation("This item displays the name of an entity."),
 * )
 */
class EntityName extends ConfigurationContainerItemPluginBase {

  const SORT_TYPE = 'alfa';
  const DATA_TYPE = 'string';
  const ITEM_TYPE = 'name';

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
    /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\EntityName $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->iconQuery = $instance->endpointQueryManager->createInstance('icon_query');
    return $instance;
  }

  /**
   * Get a default label.
   *
   * @return string
   *   A default label.
   */
  public function getDefaultLabel() {
    $configuration = $this->getPluginConfiguration();
    return $configuration['default_label'] ?? $this->t('Cluster');
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
    /** @var \Drupal\ghi_base_objects\ApiObjects\BaseObjectInterface $entity */
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

    /** @var \Drupal\node\NodeInterface $context_node */
    $context_node = $this->getContextValue('context_node');
    if ($context_node && $context_node instanceof NodeInterface && $context_node->access('view')) {
      return Link::fromTextAndUrl($markup, $context_node->toUrl())->toRenderable();
    }
    else {
      return $markup;
    }
  }

}
