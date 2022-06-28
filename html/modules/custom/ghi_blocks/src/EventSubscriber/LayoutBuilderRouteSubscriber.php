<?php

namespace Drupal\ghi_blocks\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\ghi_blocks\LayoutBuilder\SelectionCriteriaArgument;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\views\Plugin\Block\ViewsBlock;

/**
 * Provides an event subscriber that alters routes.
 *
 * This is used to replace the generic "Configure block" title in layout
 * builder modal windows with the admin label of the plugin that is
 * added/updated.
 *
 * @package Drupal\ghi_blocks
 */
class LayoutBuilderRouteSubscriber extends RouteSubscriberBase {

  /**
   * The selection criteria argument service.
   *
   * @var \Drupal\ghi_blocks\LayoutBuilder\SelectionCriteriaArgument
   */
  protected $selectionCriteriaArgument;

  /**
   * Constructs a new RouteCacheContext class.
   *
   * @param \Drupal\ghi_blocks\LayoutBuilder\SelectionCriteriaArgument $selection_criteria_argument
   *   The selection criteria argument service.
   */
  public function __construct(SelectionCriteriaArgument $selection_criteria_argument) {
    $this->selectionCriteriaArgument = $selection_criteria_argument;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -150];
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = [
      'onBuildRender',
      150,
    ];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $title_callback_add = '\Drupal\ghi_blocks\Controller\LayoutBuilderBlockController::getAddBlockFormTitle';
    $title_callback_update = '\Drupal\ghi_blocks\Controller\LayoutBuilderBlockController::getUpdateBlockFormTitle';

    if ($route = $collection->get('layout_builder.add_block')) {
      $route->setDefault('_title_callback', $title_callback_add);
    }

    if ($route = $collection->get('layout_builder.update_block')) {
      $route->setDefault('_title_callback', $title_callback_update);
    }

    // Swap out the move block controller to prevent reloading of the editable
    // area after moving blocks.
    if ($route = $collection->get('layout_builder.move_block')) {
      $route->setDefault('_controller', '\Drupal\ghi_blocks\Controller\LayoutBuilderBlockController::moveBlock');
    }
  }

  /**
   * Builds render arrays for block plugins and sets it on the event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();

    if ($block instanceof HPCBlockBase) {
      $block->setRegion($event->getComponent()->getRegion());
    }

    if (!$block instanceof ViewsBlock) {
      // No views block.
      return;
    }

    $context_definitions = $block->getContextDefinitions();
    if (!array_key_exists('field_year_value', $context_definitions)) {
      // No year context expected.
      return;
    }

    /** @var \Drupal\views\Plugin\Block\ViewsBlock $block */
    $context_value = $this->selectionCriteriaArgument->getArgumentFromSelectionCriteria('year');
    if (!$context_value) {
      return;
    }
    $context_defintion = new ContextDefinition('integer', $context_value);
    $block->setContext('field_year_value', new Context($context_defintion));
    $block->setContextValue('field_year_value', $context_value);
  }

}
