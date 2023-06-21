<?php

namespace Drupal\ghi_menu\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local action definitions for all backend content listings.
 *
 * This assumes a single backend view "content" with one page display per
 * relevant node type and filter by node type with a single value. In that
 * case, a local action will be created for each view display that fulfills
 * these criteria. The local action provides a way of creating new content of
 * the same type as listed in the view.
 */
class ContentLocalActions extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a FieldUiLocalAction object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $derivatives = [];

    /** @var \Drupal\views\Entity\View $view */
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load('content');
    $displays = $view->get('display');
    $exclude_displays = [
      'page_all',
      'page_overview',
    ];

    foreach ($displays as $display) {
      if ($display['display_plugin'] != 'page' || in_array($display['id'], $exclude_displays)) {
        continue;
      }
      $options = $display['display_options'];
      $type_filter = $options['filters']['type_1'] ?? NULL;
      if (!$type_filter || $type_filter['entity_type'] != 'node' || count($type_filter['value']) != 1) {
        // No node type filter or too many values.
        continue;
      }
      $node_type_id = reset($options['filters']['type_1']['value']);
      /** @var \Drupal\node\NodeTypeInterface $node_type */
      $node_type = $this->entityTypeManager->getStorage('node_type')->load($node_type_id);
      if (!$node_type) {
        // If the node type doesn't exist, skip it.
        continue;
      }

      $derivatives['ghi_menu.content_add.' . $display['id']] = [
        'route_name' => 'node.add',
        'route_parameters' => [
          'node_type' => $node_type->id(),
        ],
        'title' => $this->t('Add @label', [
          '@label' => strtolower($node_type->label()),
        ]),
        'appears_on' => ['view.content.' . $display['id']],
      ];
    }

    foreach ($derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $derivatives;
  }

}
