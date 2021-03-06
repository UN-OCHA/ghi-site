<?php

namespace Drupal\gho_footnotes;

use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provide trusted callbacks for rendering.
 */
class GhoFootnotes implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'updateFootnotes',
    ];
  }

  /**
   * Post render function to update the footnotes in a html text.
   */
  public static function updateFootnotes($html, $build) {
    $dom = Html::load($html);

    $accumulator = [];
    $accumulated = $build['#view_mode'] !== 'preview';

    // Process the texts with footnotes.
    foreach (iterator_to_array($dom->getElementsByTagName('gho-footnotes-text')) as $node) {
      $id = $node->getAttribute('data-id');
      $node_inner_html = gho_footnotes_get_inner_html($node);

      // Extract references.
      $references = gho_footnotes_extract_references($node_inner_html);

      // Get the footnotes for this text element.
      $footnotes = [];
      $footnote_list_node = $dom->getElementById('gho-footnotes-list-' . $id);
      if (!empty($footnote_list_node)) {
        $footnote_inner_html = gho_footnotes_get_inner_html($footnote_list_node);
        $footnotes = gho_footnotes_generate_footnotes($id, $footnote_inner_html, $references, count($accumulator));

        // Store the footnotes to render the footnote list.
        foreach ($footnotes as $footnote) {
          $accumulator[$footnote['#id']] = $footnote;
        }
      }

      // Update the footnote references and remove the containing div.
      $node_inner_html = gho_footnotes_update_text($id, $node_inner_html, $references, $footnotes);
      $fragment = $dom->createDocumentFragment();
      $fragment->appendXml($node_inner_html);
      $node->parentNode->replaceChild($fragment, $node);
    }

    // Remove all the footnote containers.
    foreach (iterator_to_array($dom->getElementsByTagName('gho-footnotes-list')) as $node) {
      $node->parentNode->removeChild($node);
    }

    // Update the footnote list.
    if (isset($build['footnotes']['#id'])) {
      $id = $build['footnotes']['#id'];
      $node = $dom->getElementById('gho-footnotes-placeholder-' . $id);

      if (!empty($node)) {
        // Render the footnote list and replace the placeholder.
        $content = '';
        if (!empty($accumulator)) {
          $build = gho_footnotes_build_footnotes($id, $accumulator, $accumulated);
          $content = \Drupal::service('renderer')->render($build);
        }
        gho_footnotes_replace_footnote_list($node, $content, $id);
      }
    }

    $html = trim(Html::serialize($dom));
    return $html;
  }

}
