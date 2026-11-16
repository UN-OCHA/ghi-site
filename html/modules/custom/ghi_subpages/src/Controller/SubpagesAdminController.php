<?php

namespace Drupal\ghi_subpages\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for subpage backend page access and title.
 */
class SubpagesAdminController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(private readonly PrivateTempStoreFactory $tempStoreFactory, private readonly SubpageManager $subpageManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('tempstore.private'), $container->get('ghi_subpages.manager'));
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
    if (!$this->subpageManager->isBaseTypeNode($node)) {
      // We allow subpage listings only on base type nodes.
      return AccessResult::forbidden();
    }
    // Check if the current user has update rights on the base node.
    return $node->access('update', NULL, TRUE);
  }

  /**
   * Checks access to the confirmation route for selected direct subpages.
   */
  public function deleteAccess(NodeInterface $node, string $subpages) {
    if (!$this->subpageManager->isBaseTypeNode($node)) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    $account = $this->currentUser();
    $result = $this->subpageManager->getStructureAccess($node, $account);
    // The URL can be altered independently of the listing, so reject IDs that
    // are duplicated, missing, or do not belong directly to this section.
    $ids = explode(',', $subpages);
    if (!$ids || count($ids) !== count(array_unique($ids))) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    $storage = $this->entityTypeManager()->getStorage('node');
    foreach ($ids as $id) {
      if (!ctype_digit($id)) {
        return AccessResult::forbidden()->addCacheableDependency($node);
      }
      $child = $storage->load((int) $id);
      if (!$child) {
        return AccessResult::forbidden()->addCacheableDependency($node);
      }
      $result = $result->andIf($this->subpageManager->getStandardSubpageDeleteAccess($child, $node, $account));
    }
    return $result;
  }

  /**
   * Checks access to creating one missing direct standard subpage.
   */
  public function createAccess(NodeInterface $node, string $bundle) {
    if (!$this->subpageManager->isBaseTypeNode($node)) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    $account = $this->currentUser();
    $result = $this->subpageManager->getStandardSubpageCreateAccess($node, $bundle, $account);
    $matching = $this->entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => $bundle,
      'field_entity_reference' => $node->id(),
    ]);
    if ($matching) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }
    return $result->addCacheableDependency($node);
  }

  /**
   * Restricts direct deletion of standard pages beneath a section.
   */
  public function nodeDeleteAccess(NodeInterface $node) {
    if (!$this->subpageManager->isStandardSubpageTypeNode($node)) {
      return AccessResult::allowed()->addCacheableDependency($node);
    }
    $section = $node->hasField('field_entity_reference') ? $node->get('field_entity_reference')->entity : NULL;
    if (!$section instanceof SectionNodeInterface || !$this->subpageManager->isStandardSubpageForSection($node, $section)) {
      return AccessResult::allowed()->addCacheableDependency($node);
    }
    return $this->subpageManager->getStructureAccess($section, $this->currentUser())->addCacheableDependency($node);
  }

  /**
   * Restricts Drupal's generic bulk deletion of standard section pages.
   */
  public function nodeBulkDeleteAccess() {
    $account = $this->currentUser();
    $selection = $this->tempStoreFactory->get('entity_delete_multiple_confirm')->get($account->id() . ':node');
    if (!is_array($selection) || !$selection) {
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    // The tempstore selection can change between requests for the same user.
    $result = AccessResult::allowed()->setCacheMaxAge(0);
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple(array_keys($selection));
    foreach ($nodes as $node) {
      $result = $result->andIf($this->nodeDeleteAccess($node));
    }
    return $result;
  }

  /**
   * Access callback for the node creation.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function nodeCreateAccess(NodeTypeInterface $node_type) {
    if ($this->subpageManager->isSubpageType($node_type) && !$this->subpageManager->isManualSubpageType($node_type)) {
      // The generic creation form has no section parent to attach a standard
      // subpage to; provisioning and the Subpages task supply that context.
      return AccessResult::forbidden();
    }
    // Fall back to node type access check.
    return $this->entityTypeManager()->getAccessControlHandler('node')->createAccess($node_type->id(), $this->currentUser(), [], TRUE);
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
    $base_type = $this->subpageManager->getBaseTypeNode($node);
    return $this->t('Subpages for @type %label', [
      '@type' => $base_type->type->entity->label(),
      '%label' => $base_type->label(),
    ]);
  }

}
