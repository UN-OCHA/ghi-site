<?php

namespace Drupal\ghi_subpages\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\ghi_subpages\SubpageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides section menu item plugin definitions for all standard subpage types.
 *
 * @internal
 *   Plugin derivers are internal.
 */
class StandardSubpageSectionMenuItemDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The subpage manager.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a BlockContentDeriver object.
   *
   * @param \Drupal\ghi_subpages\SubpageManager $subpage_manager
   *   The subpage manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SubpageManager $subpage_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->subpageManager = $subpage_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('ghi_subpages.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    foreach ($this->subpageManager->getStandardSubpageTypes() as $type) {
      $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);
      if (!$node_type) {
        continue;
      }
      $this->derivatives[$type] = $base_plugin_definition;
      $this->derivatives[$type]['node_type'] = $node_type->id();
      $this->derivatives[$type]['admin_label'] = $node_type->label();
      $this->derivatives[$type]['config_dependencies'][$node_type->getConfigDependencyKey()][] = $node_type->getConfigDependencyName();
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
