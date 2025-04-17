<?php

namespace Drupal\ghi_menu;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcherInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;

/**
 * Matcher class to get autocompletion results for entity reference.
 */
class GhiEntityAutocompleteMatcher implements EntityAutocompleteMatcherInterface {

  use StringTranslationTrait;

  /**
   * Original entity autocomplete matcher.
   *
   * @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface
   */
  protected $entityAutocompleteMatcher;

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Public constructor.
   *
   * @param \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $entity_autocomplete_matcher
   *   The original entity autocomplete matcher service.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityAutocompleteMatcherInterface $entity_autocomplete_matcher, SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityAutocompleteMatcher = $entity_autocomplete_matcher;
    $this->selectionManager = $selection_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_id, $selection_handler, $selection_settings, $string = '') {
    if ($target_id !== 'node') {
      return $this->entityAutocompleteMatcher->getMatches($target_id, $selection_handler, $selection_settings, $string);
    }
    $matches = [];
    if (!isset($string)) {
      return $matches;
    }
    // Get the matches with different limits based on type of referenced entity.
    /** @var \Drupal\node\Plugin\EntityReferenceSelection\NodeSelection $handler */
    $handler = $this->selectionManager->getInstance($selection_settings + [
      'target_type' => $target_id,
      'handler' => $selection_handler,
    ]);
    $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
    $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 100);

    // Customize the labels used in autocomplete.
    foreach ($entity_labels as $bundle => $values) {
      $node_type = $this->entityTypeManager->getStorage('node_type')->load($bundle);
      $entities = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($values));
      foreach ($values as $entity_id => $label) {
        $entity = $entities[$entity_id];
        if ($entity instanceof Article) {
          $custom_label = $node_type->label() . ': ' . $this->getArticleLabel($entity_id, $target_id, $label);
        }
        elseif ($entity instanceof SubpageNodeInterface) {
          $custom_label = $this->t('Subpage: @subpage_label', [
            '@subpage_label' => $this->getSubpageLabel($entity_id, $target_id, $label),
          ]);
        }
        else {
          $custom_label = $node_type->label() . ': ' . $label;
        }

        if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
          $custom_label = $custom_label . ' - Unpublished';
        }

        if (($entity instanceof SectionNodeInterface || $entity instanceof ContentBase) && $entity->isProtected()) {
          $custom_label = new FormattableMarkup('<span class="protected">@label</span>', [
            '@label' => $custom_label,
          ]);
        }

        // Create a sanitized key.
        $key = "$label ($entity_id)";
        $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
        $key = Tags::encode($key);
        $matches[] = ['value' => $key, 'label' => $custom_label];
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
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
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
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    $parent = $entity->getParentBaseNode();
    if ($parent) {
      $label = $label . ' (' . $parent->label() . ')';
    }
    return $label;
  }

}
