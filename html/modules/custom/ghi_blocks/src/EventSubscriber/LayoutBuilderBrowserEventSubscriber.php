<?php

namespace Drupal\ghi_blocks\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Render\Element\VerticalTabs;
use Drupal\Core\Session\AccountInterface;
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
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a LayoutBuilderBrowserEventSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account service.
   */
  public function __construct(AccountInterface $account) {
    $this->currentUser = $account;
  }

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
        $form['category_header'] = [
          '#type' => 'html_tag',
          '#tag' => 'h5',
          '#value' => $this->t('Choose a block type from the following categories:'),
        ];
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
        $form['tabs']['#default_tab'] = Html::getClass($request->query->get('block_category') ?? reset($categories));

        // Let the tab element set itself up.
        VerticalTabs::processVerticalTabs($form['tabs'], $form_state, $complete_form);
        RenderElementBase::processGroup($form['tabs']['group'], $form_state, $complete_form);

        // Default tab is the first one. We have to set #value instead of the
        // #default_value, because this is not a real form and the normal form
        // processing doesn't work.
        $form['tabs']['tabs__active_tab']['#value'] = Html::getClass($request->query->get('block_category') ?? reset($categories));

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

          // Go over all links and add the current block category, so that we
          // can get back here if the user cancels the upcoming block
          // configuration dialog.
          foreach ($form['block_categories'][$element_key]['links']['#links'] ?? [] as &$link) {
            /** @var \Drupal\Core\Url $url */
            $url = &$link['url'];
            $url_options = $url->getOptions();
            $url_options['query'] = $url_options['query'] ?? [];
            $url_options['query'] += ['block_category' => $element_key];
            $url->setOptions($url_options);
          }

          RenderElementBase::processGroup($form['block_categories'][$element_key], $form_state, $complete_form);
        }

        // Add a section for admin specific links.
        $form['block_categories']['admin'] = [
          '#type' => 'details',
          '#title' => $this->t('Admin restricted'),
          '#attributes' => [
            'class' => [
              0 => 'js-layout-builder-category',
            ],
          ],
          '#id' => Html::getId('admin'),
          '#parents' => [
            'block_categories',
            'admin',
          ],
          '#group' => 'tabs',
          'links' => [
            '#theme' => 'links',
            '#links' => [],
          ],
        ];
        RenderElementBase::processGroup($form['block_categories']['admin'], $form_state, $complete_form);

        if ($this->currentUser->hasPermission('import block from configuration code')) {
          $route_params = $request->attributes->get('_route_params');
          $form['block_categories']['admin']['links']['#links'][] = [
            'title' => $this->t('Import from code'),
            'url' => Url::fromRoute('ghi_blocks.import_block', [
              'section_storage_type' => $route_params['section_storage_type'],
              'section_storage' => $route_params['section_storage']->getStorageId(),
              'delta' => $route_params['delta'],
              'region' => $route_params['region'],
            ], [
              'query' => array_filter([
                'position' => $request->query->get('position') ?? NULL,
                'block_category' => 'admin',
              ]),
            ]),
            'attributes' => [
              'class' => ['use-ajax', 'js-layout-builder-block-link'],
              'data-dialog-type' => 'dialog',
              'data-dialog-renderer' => 'off_canvas',
            ],
          ];
        }

        if (array_key_exists('add_block', $build) && !empty($build['add_block']['#access'])) {
          /** @var \Drupal\Core\Url $url */
          $url = $build['add_block']['#url'];
          $url_options = $url->getOptions();
          $url_options['query'] = $url_options['query'] ?? [];
          $url_options['query'] += array_filter([
            'position' => $request->query->get('position') ?? NULL,
            'block_category' => 'admin',
          ]);
          $url->setOptions($url_options);
          if ($this->currentUser->hasPermission('use inline blocks')) {
            $form['block_categories']['admin']['links']['#links'][] = [
              'title' => $this->t('Generic HTML'),
              'url' => $url,
              'attributes' => [
                'class' => ['use-ajax', 'js-layout-builder-block-link'],
                'data-dialog-type' => 'dialog',
                'data-dialog-renderer' => 'off_canvas',
              ],
            ];
          }
          $build['add_block']['#access'] = FALSE;
        }

        if (empty($form['block_categories']['admin']['links']['#links'])) {
          $form['block_categories']['admin']['#access'] = FALSE;
        }
        $this->processVerticalTabs($form, $form_state);

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
