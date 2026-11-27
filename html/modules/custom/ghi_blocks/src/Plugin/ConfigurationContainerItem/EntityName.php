<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an entity name item for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'entity_name',
  label: new TranslatableMarkup('Entity name'),
  description: new TranslatableMarkup('This item displays the name of an entity.'),
)]
class EntityName extends ConfigurationContainerItemPluginBase {

  const SORT_TYPE = 'alfa';
  const DATA_TYPE = 'string';
  const ITEM_TYPE = 'name';

  /**
   * The icon query.
   *
   * @var \Drupal\hpc_api\Plugin\FabricQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): EntityName {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->iconQuery = $instance->fabricQueryManager->createInstance('icon');
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
    if (isset($configuration['default_label'])) {
      return $configuration['default_label'];
    }
    $plan = $this->getContextValue('plan_object');
    return $this->t('Cluster', [], $this->buildPlanTranslationOptions($plan instanceof Plan ? $plan : NULL));
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
      return $entity->getDisplayName();
    }
    return $entity->getName() ?? NULL;
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
    $icon_embed = $entity instanceof GoverningEntity && $entity->hasIcon() ? $this->iconQuery->getIconEmbedCode($entity->getIcon()) : NULL;

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
