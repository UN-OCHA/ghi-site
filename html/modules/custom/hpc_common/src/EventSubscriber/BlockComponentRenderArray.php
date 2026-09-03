<?php

namespace Drupal\hpc_common\EventSubscriber;

use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Preserves Layout Builder component identity on HPC blocks.
 *
 * Downloads and lazy rendering use the component UUID and owning entity to
 * find the saved block, including when it is embedded in another page.
 */
class BlockComponentRenderArray implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    // This must be higher than the value in
    // \Drupal\layout_builder\EventSubscriber\BlockComponentRenderArray.
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = [
      'onBuildRender',
      150,
    ];
    return $events;
  }

  /**
   * Builds render arrays for block plugins and sets it on the event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();

    // Get the configuration and add the uuid of the component.
    if ($block instanceof HPCBlockBase) {
      $block_config = $block->getConfiguration();
      $block_config['uuid'] = $event->getComponent()->getUuid();
      $block->setConfiguration($block_config);

      // Blocks without a declared node context still need their layout owner
      // so lazy callbacks can find them outside the current page's layout.
      $contexts = $event->getContexts();
      if (isset($contexts['layout_builder.entity'])) {
        $block->setContext('layout_builder.entity', $contexts['layout_builder.entity']);
      }
    }
  }

}
