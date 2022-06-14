<?php

namespace Drupal\ghi_subpages\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\ghi_subpages\SubpageTrait;
use Drupal\node\Access\NodeAddAccessCheck;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for autocomplete plan loading.
 */
class SubpagesAdminController extends ControllerBase {

  use SubpageTrait;

  /**
   * The node add access check service.
   *
   * @var \Drupal\node\Access\NodeAddAccessCheck
   */
  private $nodeAddAccessCheck;

  /**
   * Public constructor.
   */
  public function __construct(NodeAddAccessCheck $node_add_access_check) {
    $this->nodeAddAccessCheck = $node_add_access_check;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('access_check.node.add')
    );
  }

  /**
   * Access callback for the subpages page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    if (!$this->isBaseTypeNode($node) && !$this->isSubpageTypeNode($node)) {
      // We allow subpage listings on base type nodes and subpage nodes.
      return AccessResult::forbidden();
    }
    $base_type = $this->getBaseTypeNode($node);
    if (!$base_type) {
      // Base type node not found.
      return AccessResult::forbidden();
    }
    // Then check if the current user has update rights on the base node.
    return $base_type->access('update', NULL, TRUE);
  }

  /**
   * Access callback for the plan structure page.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function nodeCreateAccess(NodeTypeInterface $node_type) {
    if (in_array($node_type->id(), SubpageHelper::SUPPORTED_SUBPAGE_TYPES)) {
      // We don't want subpages to be created manually, as this process is
      // automatic whenever a base page is created.
      return AccessResult::forbidden();
    }
    // Fall back to node type access check.
    return $this->nodeAddAccessCheck->access($this->currentUser(), $node_type);
  }

  /**
   * The _title_callback for the page that renders the admin form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(NodeInterface $node) {
    $base_type = $this->getBaseTypeNode($node);
    return $this->t('Subpages for @type %label', [
      '@type' => $base_type->type->entity->label(),
      '%label' => $base_type->label(),
    ]);
  }

}
