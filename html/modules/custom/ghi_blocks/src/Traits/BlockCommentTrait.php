<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Render\Markup;

/**
 * Helper trait for blocks that support editing and display of a comment.
 */
trait BlockCommentTrait {

  /**
   * Build the form element for the configuration of the soft limit.
   *
   * @param string $default_value
   *   The default value to set.
   *
   * @return array
   *   A form array.
   */
  public function buildBlockCommentFormElement($default_value) {
    return [
      '#type' => 'textarea',
      '#title' => $this->t('Block comment'),
      '#description' => $this->t('You can optionally enter a comment that will be displayed directly under this page element.'),
      '#rows' => 5,
      '#default_value' => $default_value,
    ];
  }

  /**
   * Build a render array to display a block comment.
   *
   * @param string $value
   *   The value to render as a comment.
   *
   * @return array|null
   *   A render array or null if the value argument is not valid.
   */
  public function buildBlockCommentRenderArray($value) {
    if (empty($value)) {
      return NULL;
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['block-comment'],
      ],
      'comment' => [
        '#markup' => Markup::create($value),
      ],
    ];
  }

}
