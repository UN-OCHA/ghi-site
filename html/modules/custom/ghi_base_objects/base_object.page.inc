<?php

/**
 * @file
 * Contains base_object.page.inc.
 *
 * Page callback for Base object entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Base object templates.
 *
 * Default template: base_object.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element
 *   The base object can be accessed as $variables['elements']['#base_object'].
 */
function template_preprocess_base_object(array &$variables) {
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
