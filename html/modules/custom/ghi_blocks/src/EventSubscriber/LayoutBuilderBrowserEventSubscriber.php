<?php

namespace Drupal\ghi_blocks\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Render\Element\VerticalTabs;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
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
  use StringTranslationTrait;

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

        $form['block_categories']['import'] = [
          '#type' => 'details',
          '#title' => $this->t('Import'),
          '#attributes' => [
            'class' => [
              0 => 'js-layout-builder-category',
            ],
          ],
          '#id' => Html::getId('import'),
          '#parents' => [
            'block_categories',
            'import',
          ],
          '#open' => TRUE,
          '#group' => 'tabs',
        ];
        RenderElement::processGroup($form['block_categories']['import'], $form_state, $complete_form);

        $route_params = $request->attributes->get('_route_params');
        $form['block_categories']['import']['links'] = [
          '#theme' => 'links',
          '#links' => [
            [
              'title' => $this->t('Import from code'),
              'url' => Url::fromRoute('ghi_blocks.import_block', [
                'section_storage_type' => $route_params['section_storage_type'],
                'section_storage' => $route_params['section_storage']->getStorageId(),
                'delta' => $route_params['delta'],
                'region' => $route_params['region'],
              ], [
                'query' => array_filter([
                  'position' => $request->query->get('position') ?? NULL,
                ]),
              ]),
              'attributes' => [
                'class' => ['use-ajax', 'js-layout-builder-block-link'],
                'data-dialog-type' => 'dialog',
                'data-dialog-renderer' => 'off_canvas',
              ],
            ],
          ],
        ];

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
