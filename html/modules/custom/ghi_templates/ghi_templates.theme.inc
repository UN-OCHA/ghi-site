<?php

/**
 * @file
 * Contains ghi_templates.theme.inc.
 *
 * Theme callback for page template entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for page template templates.
 *
 * Default template: page_template.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element
 *   The base object can be accessed as $variables['elements']['#base_object'].
 */
function template_preprocess_page_template(array &$variables) {
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
