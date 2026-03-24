<?php

namespace Drupal\ghi_plans\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber to prepare section storage.
 *
 * Section storage works via the
 * \Drupal\layout_builder\Event\PrepareLayoutEvent.
 *
 * @see \Drupal\layout_builder\Event\PrepareLayoutEvent
 * @see \Drupal\layout_builder\Element\LayoutBuilder::prepareLayout()
 */
class PrepareLayout implements EventSubscriberInterface {

  use StringTranslationTrait;
  use PlanQueryTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[LayoutBuilderEvents::PREPARE_LAYOUT][] = ['onPrepareLayout', 10];
    return $events;
  }

  /**
   * Prepares a layout for use in the UI.
   *
   * @param \Drupal\layout_builder\Event\PrepareLayoutEvent $event
   *   The prepare layout event.
   */
  public function onPrepareLayout(PrepareLayoutEvent $event) {
    $section_storage = $event->getSectionStorage();
    foreach ($section_storage->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        if (!$component instanceof GHIBlockBase) {
          continue;
        }
      }
    }
  }

}
