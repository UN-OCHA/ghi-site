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

    // Define view modes that should not use accumulated.
    $single_view_modes = ['preview', 'facts_and_figures'];

    $accumulator = [];
    $accumulated = !in_array(($build['#view_mode'] ?? NULL), $single_view_modes);

    // Process the texts with footnotes.
    foreach (iterator_to_array($dom->getElementsByTagName('gho-footnotes-text')) as $node) {
      $id = $node->getAttribute('data-id');
      $node_inner_html = gho_footnotes_get_inner_html($node);
      $node_inner_html = preg_replace('/<!--(.|\s)*?-->\s*/', '', $node_inner_html);

      // Extract references.
      $references = gho_footnotes_extract_references($node_inner_html);

      // Cleanup faulty footnote links, things like '<a href="#_ftn1">[1]</a>
      // where it should be just '[1]'.
      $updated_html = FALSE;
      $links = $dom->getElementsByTagName('a');
      foreach ($links as $link) {
        if (array_key_exists($link->nodeValue, $references)) {
          $node_inner_html = str_replace($link->ownerDocument->saveXML($link), $link->nodeValue, $node_inner_html);
          $updated_html = TRUE;
        }
      }

      // Extract the references again to make the positions match again.
      if ($updated_html) {
        $references = gho_footnotes_extract_references($node_inner_html);
      }

      // Get the footnotes for this text element.
      $footnotes = [];
      $footnote_list_node = $dom->getElementById('gho-footnotes-list-' . $id);
      if (!empty($footnote_list_node)) {
        // Make sure that footnotes are separated by line breaks.
        $footnote_inner_html = implode("\n", array_map(function ($child_node) {
          return trim(gho_footnotes_get_inner_html($child_node));
        }, iterator_to_array($footnote_list_node->childNodes)));
        $footnotes = gho_footnotes_generate_footnotes($id, $footnote_inner_html, $references, count($accumulator));

        // Store the footnotes to render the footnote list.
        foreach ($footnotes as $footnote) {
          $accumulator[$footnote['#id']] = $footnote;
        }
      }

      // Update the footnote references and remove the containing div.
      $node_inner_html = gho_footnotes_update_text($id, $node_inner_html, $references, $footnotes);
      $fragment = $dom->createDocumentFragment();
      // Note that we add a newline here. This is made to prevent a strange
      // issue with the caption credits, that are sometimes appearing spaceless
      // after the last paragraph of a text.
      $fragment->appendXml($node_inner_html . "\n");
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
