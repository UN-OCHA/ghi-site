<?php

namespace Drupal\ghi_menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a content specific implementation for local action plugins.
 */
class LocalActionContent extends LocalActionDefault {

  use StringTranslationTrait;

  const DEFAULT_BUNDLE = 'section';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    $node_type = $this->entityTypeManager->getStorage('node_type')->load(self::DEFAULT_BUNDLE);
    if (!$node_type) {
      return parent::getTitle($request);
    }
    return $this->t('Add @type', [
      '@type' => strtolower($node_type->label()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return 'node.add';
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $route_parameters['node_type'] = self::DEFAULT_BUNDLE;
    return $route_parameters;
  }

}
