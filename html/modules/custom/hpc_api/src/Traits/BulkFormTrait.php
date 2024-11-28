<?php

namespace Drupal\hpc_api\Traits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;

/**
 * Provide a simple in-memory cache.
 */
trait BulkFormTrait {

  /**
   * Build the bulk form.
   *
   * Do this in a way that plays nice with Gin.
   *
   * @param array $form
   *   The form array.
   * @param array $bulk_form_actions
   *   An array of actions as simple key - label pairs.
   */
  protected function buildBulkForm(array &$form, array $bulk_form_actions) {
    if (empty($bulk_form_actions)) {
      return;
    }

    $form['#after_build'][] = [self::class, 'afterBuild'];

    // Build the bulk form. This is mainly done in a way to be compatible with
    // the gin theme, see gin_form_alter() and gin/styles/base/_views.scss.
    $form['#prefix'] = Markup::create('<div class="view-content"><div class="views-form">');
    $form['#suffix'] = Markup::create('</div></div>');
    $form['header'] = [
      '#type' => 'container',
      '#id' => 'edit-header',
      'subpages_bulk_form' => [
        '#type' => 'container',
        '#id' => 'edit-node-bulk-form',
        'action' => [
          '#type' => 'select',
          '#title' => $this->t('Action'),
          '#options' => $bulk_form_actions,
        ],
        'actions' => [
          '#type' => 'actions',
          'submit' => [
            '#type' => 'submit',
            '#name' => 'bulk_submit',
            '#value' => $this->t('Apply to selected items'),
          ],
        ],
      ],
    ];

    self::imitateViewBulkForm($form, 'subpages_bulk_form', $this->t('Section subpages'));
  }

  /**
   * Pretend to be a views bulk form.
   *
   * This has been copied 1:1 from claro_form_alter, which applies this logic
   * only to views forms unfortunately.
   * Because a strict copy it's been added here as a separate function.
   *
   * @param array $form
   *   The form array.
   * @param string $key
   *   The string key of the bulk actions form.
   * @param string|\Drupal\Component\Render\MarkupInterface $view_title
   *   The pretended title of the view.
   */
  private static function imitateViewBulkForm(array &$form, $key, $view_title) {
    // Move the bulk actions form from the header to its own container.
    $form['bulk_actions_container'] = $form['header'][$key];
    unset($form['header'][$key]);

    // Remove the supplementary bulk operations submit button as it appears
    // in the same location the form was moved to.
    unset($form['actions']);

    $form['bulk_actions_container']['#attributes']['data-drupal-views-bulk-actions'] = '';
    $form['bulk_actions_container']['#attributes']['class'][] = 'views-bulk-actions';
    $form['bulk_actions_container']['actions']['submit']['#button_type'] = 'primary';
    $form['bulk_actions_container']['actions']['submit']['#attributes']['class'][] = 'button--small';
    $label = t('Perform actions on the selected items in the %view_title view', ['%view_title' => $view_title]);
    $label_id = $key . '_group_label';

    // Group the bulk actions select and submit elements, and add a label
    // that makes the purpose of these elements more clear to
    // screen readers.
    $form['bulk_actions_container']['#attributes']['role'] = 'group';
    $form['bulk_actions_container']['#attributes']['aria-labelledby'] = $label_id;
    $form['bulk_actions_container']['group_label'] = [
      '#type' => 'container',
      '#markup' => $label,
      '#attributes' => [
        'id' => $label_id,
        'class' => ['visually-hidden'],
      ],
      '#weight' => -1,
    ];

    // Add a status label for counting the number of items selected.
    $form['bulk_actions_container']['status'] = [
      '#type' => 'container',
      '#markup' => t('No items selected'),
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'js-views-bulk-actions-status',
          'views-bulk-actions__item',
          'views-bulk-actions__item--status',
          'js-show',
        ],
        'data-drupal-views-bulk-actions-status' => '',
      ],
    ];

    // Loop through bulk actions items and add the needed CSS classes.
    $bulk_action_item_keys = Element::children($form['bulk_actions_container'], TRUE);
    $bulk_last_key = NULL;
    $bulk_child_before_actions_key = NULL;
    foreach ($bulk_action_item_keys as $bulk_action_item_key) {
      if (!empty($form['bulk_actions_container'][$bulk_action_item_key]['#type'])) {
        if ($form['bulk_actions_container'][$bulk_action_item_key]['#type'] === 'actions') {
          // We need the key of the element that precedes the actions
          // element.
          $bulk_child_before_actions_key = $bulk_last_key;
          $form['bulk_actions_container'][$bulk_action_item_key]['#attributes']['class'][] = 'views-bulk-actions__item';
        }

        if (!in_array($form['bulk_actions_container'][$bulk_action_item_key]['#type'], ['hidden', 'actions'])) {
          $form['bulk_actions_container'][$bulk_action_item_key]['#wrapper_attributes']['class'][] = 'views-bulk-actions__item';
          $bulk_last_key = $bulk_action_item_key;
        }
      }
    }

    if ($bulk_child_before_actions_key) {
      $form['bulk_actions_container'][$bulk_child_before_actions_key]['#wrapper_attributes']['class'][] = 'views-bulk-actions__item--preceding-actions';
    }
  }

  /**
   * After build callback for the form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The form array.
   */
  public static function afterBuild(array $form, FormStateInterface $form_state) {
    foreach (Element::children($form) as $element_key) {
      $element = &$form[$element_key];
      if (empty($element['#type']) || $element['#type'] != 'tableselect') {
        if (!empty(Element::children($element))) {
          // Check if any of the children is a tableselect.
          $element = self::afterBuild($element, $form_state);
        }
        continue;
      }
      $element['#pre_render'][] = function (array $element) use ($form) {
        // Add a class to the checkbox column of each row, so that the logic in
        // core/themes/claro/js/tableselect.js can find the checkboxes.
        foreach ($element['#rows'] as &$row) {
          $row['data'][0] = [
            'data' => $row['data'][0],
            'class' => $form['#id'] . '-bulk-form',
          ];
        }
        return $element;
      };
    }
    return $form;
  }

}
