<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\ghi_content\Traits\ContentPathTrait;

/**
 * Provides a 'DocumentMetaData' block.
 *
 * @Block(
 *  id = "document_meta_data",
 *  admin_label = @Translation("Document meta data"),
 *  category = @Translation("Page"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class DocumentMetaData extends BlockBase {

  use ContentPathTrait;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $document = $this->getCurrentDocumentNode();
    if (!$document || $this->getCurrentSectionNode()) {
      return NULL;
    }
    $metadata = $document->getPageMetaData();
    if (!$metadata) {
      return NULL;
    }
    return [
      '#theme' => 'item_list',
      '#items' => $metadata,
      '#full_width' => TRUE,
      '#cache' => [
        'contexts' => ['url.path'],
      ],
    ];
  }

}
