<?php

namespace Drupal\ghi_embargoed_access\Plugin\views\field;

use Drupal\node\NodeInterface;
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
   * The embargoed access manager service.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->embargoedAccessManager = $container->get('ghi_embargoed_access.manager');
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
    $node = $values->_entity ?? NULL;
    if (!$node instanceof NodeInterface) {
      return;
    }
    $pid = $this->embargoedAccessManager->loadProtectedPageIdForNode($node);
    return !empty($pid);
  }

}
