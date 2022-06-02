<?php

namespace Drupal\hpc_common\EventSubscriber;

use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listen to the block component render event that emitted by layout builder.
 *
 * This is only used to add the blocks uuid to it's configuration, so that the
 * hpc_downnloads module can easily access it.
 *
 * This is only needed for node displays, because page_manager already adds the
 * block uuid to it's blocks.
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
    $block_config = $block->getConfiguration();
    $block_config['uuid'] = $event->getComponent()->getUuid();
    $block->setConfiguration($block_config);
  }

}
