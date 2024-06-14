<?php

namespace Drupal\ghi_embargoed_access\Plugin\search_api\processor;

use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds embargoed access checks for nodes.
 *
 * @SearchApiProcessor(
 *   id = "embargoed_access",
 *   label = @Translation("Embargoed access"),
 *   description = @Translation("Adds embargoed access checks for nodes."),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class EmbargoedAccess extends ProcessorPluginBase {

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
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->embargoedAccessManager = $container->get('ghi_embargoed_access.manager');

    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if (in_array($datasource->getEntityTypeId(), ['node'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    if (!$this->embargoedAccessManager->embargoedAccessEnabled()) {
      return;
    }
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $node = $item->getOriginalObject()->getValue();
      if (!$node instanceof NodeInterface) {
        continue;
      }
      if ($this->embargoedAccessManager->isProtected($node)) {
        // The item itself is protected.
        unset($items[$item_id]);
        continue;
      }
      if ($node instanceof SubpageNodeInterface) {
        $parent = $node->getParentNode();
        if ($parent && $this->embargoedAccessManager->isProtected($parent)) {
          // The direct parent of the item is protected.
          unset($items[$item_id]);
          continue;
        }
        $section_parent = $node->getParentBaseNode();
        if ($section_parent && $this->embargoedAccessManager->isProtected($section_parent)) {
          // The section parent of the item is protected.
          unset($items[$item_id]);
          continue;
        }
      }
    }
  }

}
