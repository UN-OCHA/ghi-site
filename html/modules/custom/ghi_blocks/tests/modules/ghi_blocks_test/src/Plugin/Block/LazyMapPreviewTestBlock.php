<?php

namespace Drupal\ghi_blocks_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Interfaces\LazyMapDataFragmentBlockInterface;
use Drupal\ghi_blocks\Map\MapDataFragment;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a test block for lazy map preview fragments.
 */
#[Block(
  id: 'ghi_blocks_lazy_map_preview_test',
  admin_label: new TranslatableMarkup('Lazy map preview test'),
  category: new TranslatableMarkup('GHI Blocks Test'),
  context_definitions: [
    'user' => new EntityContextDefinition('entity:user', new TranslatableMarkup('User'), required: FALSE),
    'year' => new ContextDefinition(data_type: 'integer', label: new TranslatableMarkup('Year'), required: FALSE),
  ],
)]
class LazyMapPreviewTestBlock extends GHIBlockBase implements LazyMapDataFragmentBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    return ['#markup' => $this->getPreviewStateToken()];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildLazyMapDataFragment(string $map_id, string $data_index, ?string $variant_id = NULL): ?MapDataFragment {
    return new MapDataFragment([
      'map_id' => $map_id,
      'data_index' => $data_index,
      'variant_id' => $variant_id,
      'current_uri' => $this->getCurrentUri(),
    ], new CacheableMetadata());
  }

  /**
   * {@inheritdoc}
   */
  public function buildLazyMapModalFragment(string $map_id, string $data_index, string $object_id, ?string $variant_id = NULL): ?MapDataFragment {
    return new MapDataFragment([
      'map_id' => $map_id,
      'data_index' => $data_index,
      'object_id' => $object_id,
      'variant_id' => $variant_id,
      'current_uri' => $this->getCurrentUri(),
    ], new CacheableMetadata());
  }

}
