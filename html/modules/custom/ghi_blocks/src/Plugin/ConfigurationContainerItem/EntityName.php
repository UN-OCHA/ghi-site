<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Query\IconQuery;

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
   * @var \Drupal\ghi_plans\Query\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, IconQuery $icon_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->iconQuery = $icon_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ghi_plans.icon_query'),
    );
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
    return $entity && property_exists($entity, 'name') ? $entity->name : NULL;
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
    elseif ($context_node && $context_node->access('view')) {
      return Link::fromTextAndUrl($markup, $context_node->toUrl())->toRenderable();
    }
    else {
      return $markup;
    }
  }

}
