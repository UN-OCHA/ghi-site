<?php

namespace Drupal\ghi_blocks\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Render\Element\VerticalTabs;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class LayoutBuilderBrowserEventSubscriber.
 *
 * Add layout builder css class layout-builder-browser.
 */
class LayoutBuilderBrowserEventSubscriber implements EventSubscriberInterface {

  use VerticalTabsTrait;

  /**
   * Display the block categories as vertical tabs.
   */
  public function onView(ViewEvent $event) {
    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    if ($route == 'layout_builder.choose_block') {
      $build = $event->getControllerResult();

      if (is_array($build)) {
        // Prepare a pseudo form where we can attach vertical tabs.
        $form_state = new FormState();
        $complete_form = [];
        $form = [];
        $form['tabs'] = [
          '#type' => 'vertical_tabs',
          '#parents' => ['tabs'],
        ];
        $form['block_categories'] = $build['block_categories'];
        $form['block_categories']['#tree'] = TRUE;

        // Get the categories and turn the formatted keys into something that
        // is usually used in the Form API.
        $categories = Element::children($form['block_categories']);
        $categories = array_combine($categories, $categories);
        $categories = array_map(function ($category) {
          // This turns "Generic Elements" into "generic_elements".
          return str_replace('-', '_', Html::getClass($category));
        }, $categories);

        // Default tab is the first one.
        $form['tabs']['#default_tab'] = reset($categories);

        // Let the tab element set itself up.
        VerticalTabs::processVerticalTabs($form['tabs'], $form_state, $complete_form);
        RenderElement::processGroup($form['tabs']['group'], $form_state, $complete_form);

        // Now go over the block categories, add some required properties and
        // run the process callback.
        $form['block_categories']['#parents'] = ['block_categories'];
        foreach ($categories as $build_key => $element_key) {
          $form['block_categories'][$element_key] = $form['block_categories'][$build_key];
          unset($form['block_categories'][$build_key]);

          $form['block_categories'][$element_key]['#group'] = 'tabs';
          $form['block_categories'][$element_key]['#id'] = Html::getId($element_key);
          $form['block_categories'][$element_key]['#parents'] = [
            'block_categories',
            $element_key,
          ];
          RenderElement::processGroup($form['block_categories'][$element_key], $form_state, $complete_form);
        }

        // Replace the original render array with our newly built.
        $build['block_categories'] = $form;

        // And while we are here, also disable the filter.
        $build['filter']['#access'] = FALSE;

        $event->setControllerResult($build);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::VIEW][] = ['onView', 50];
    return $events;
  }

}
