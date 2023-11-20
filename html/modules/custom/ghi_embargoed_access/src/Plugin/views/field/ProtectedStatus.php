<?php

namespace Drupal\ghi_embargoed_access\Plugin\views\field;

use Drupal\views\Plugin\views\field\Boolean;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display the protected status of a node.
 *
 * @ViewsField("protected_status")
 */
class ProtectedStatus extends Boolean {

  /**
   * The protected pages storage service.
   *
   * @var \Drupal\protected_pages\ProtectedPagesStorage
   */
  protected $protectedPagesStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->protectedPagesStorage = $container->get('protected_pages.storage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Overridden to prevent additional query.
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $path = '/node/' . $values->_entity->id();
    $fields = ['pid'];
    $conditions = [
      'general' => [],
    ];
    $conditions['general'][] = [
      'field' => 'path',
      'value' => $path,
      'operator' => '=',
    ];
    $pid = $this->protectedPagesStorage->loadProtectedPage($fields, $conditions, TRUE);
    return !empty($pid);
  }

}
