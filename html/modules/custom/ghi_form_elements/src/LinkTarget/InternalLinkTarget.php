<?php

namespace Drupal\ghi_form_elements\LinkTarget;

use Drupal\node\NodeInterface;

/**
 * A link target class representing internal links.
 */
class InternalLinkTarget implements LinkTargetInterface {

  /**
   * The target node.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $node;

  /**
   * Construct a new internal link target.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The target node.
   */
  public function __construct(NodeInterface $node) {
    $this->node = $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminLabel() {
    return strtolower($this->node->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->node->toUrl();
  }

}
