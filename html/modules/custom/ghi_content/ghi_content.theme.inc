<?php

/**
 * @file
 * Contains theme preprocess functions.
 */

use Drupal\Core\Template\Attribute;

/**
 * Implements hook_preprocess().
 */
function ghi_content_preprocess_article_collection_cards(&$variables) {
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
  $featured = $variables['options']['featured'] ?? [];
  $items = [];
  $subkey = 0;
  foreach ($variables['articles'] as $article) {
    /** @var \Drupal\node\NodeInterface $article */
    $entity_view = $view_builder->view($article, 'grid');
    $items[$subkey]['content'][] = [
      'content' => $entity_view,
      'attributes' => new Attribute([
        'class' => [
          in_array($article->id(), $featured) ? 'featured' : NULL,
        ],
      ]),
    ];
    if (count($items[$subkey]) == $variables['options']['columns']) {
      $subkey++;
    }
  }

  $variables['items'] = $items;
}

/**
 * Implements hook_preprocess().
 */
function ghi_content_preprocess_article_collection_table(&$variables) {
  // No used yet.
}

/**
 * Implements hook_preprocess().
 */
function ghi_content_preprocess_related_articles_cards(&$variables) {
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
  $items = [];
  foreach ($variables['articles'] as $article) {
    /** @var \Drupal\node\NodeInterface $article */
    $items[] = $view_builder->view($article, 'card_summary');
  }

  $variables['list'] = $items;
}
