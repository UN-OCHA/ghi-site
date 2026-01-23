<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ghi_content\Traits\ContentPathTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'DocumentMetaData' block.
 */
#[Block(
  id: 'document_meta_data',
  admin_label: new TranslatableMarkup('Document meta data'),
  category: new TranslatableMarkup('Page'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
  ]
)]
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
