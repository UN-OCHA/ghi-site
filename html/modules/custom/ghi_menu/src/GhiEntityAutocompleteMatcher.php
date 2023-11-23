<?php

namespace Drupal\ghi_menu;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;

/**
 * Matcher class to get autocompletion results for entity reference.
 */
class GhiEntityAutocompleteMatcher extends EntityAutocompleteMatcher {

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_id, $selection_handler, $selection_settings, $string = '') {
    if ($target_id !== 'node') {
      return parent::getMatches($target_id, $selection_handler, $selection_settings, $string);
    }
    $matches = [];
    if (!isset($string)) {
      return $matches;
    }
    // Get the matches with different limits based on type of referenced entity.
    /** @var \Drupal\node\Plugin\EntityReferenceSelection\NodeSelection $handler */
    $handler = $this->selectionManager->getInstance([
      'target_type' => $target_id,
      'handler' => $selection_handler,
      'handler_settings' => $selection_settings,
    ]);
    $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
    $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 10);

    // Customize the labels used in autocomplete.
    foreach ($entity_labels as $bundle => $values) {
      $node_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($bundle);
      $entities = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple(array_keys($values));
      foreach ($values as $entity_id => $label) {
        $entity = $entities[$entity_id];
        if ($entity instanceof Article) {
          $custom_label = $this->getArticleLabel($entity_id, $target_id, $label);
        }
        elseif ($entity instanceof SubpageNodeInterface) {
          $custom_label = $this->getSubpageLabel($entity_id, $target_id, $label);
        }
        else {
          $custom_label = $label;
        }

        if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
          $custom_label = $custom_label . ' - Unpublished';
        }

        // Create a sanitized key.
        $key = "$label ($entity_id)";
        $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
        $key = Tags::encode($key);
        $matches[] = ['value' => $key, 'label' => $node_type->label() . ': ' . $custom_label];
      }
    }
    return $matches;
  }

  /**
   * Get the label for an article.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $entity_type_id
   *   The entity type.
   * @param string $label
   *   The label so far.
   *
   * @return string
   *   The resulting label.
   */
  protected function getArticleLabel($entity_id, $entity_type_id, $label) {
    /** @var \Drupal\ghi_content\Entity\Article $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    $tags = $entity->getTags(TRUE);
    $tag_names = array_map(function ($term) {
      return $term->label();
    }, $tags);
    if (!empty($tag_names)) {
      $label = $label . ' (' . implode(', ', $tag_names) . ')';
    }
    return $label;
  }

  /**
   * Get the label for a subpage.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $entity_type_id
   *   The entity type.
   * @param string $label
   *   The label so far.
   *
   * @return string
   *   The resulting label.
   */
  protected function getSubpageLabel($entity_id, $entity_type_id, $label) {
    /** @var \Drupal\ghi_subpages\Entity\SubpageNodeInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    $parent = $entity->getParentNode();
    if ($parent) {
      $label = $label . ' (' . $parent->label() . ')';
    }
    return $label;
  }

}
