<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\taxonomy\Entity\Term;

/**
 * Helper class for handling taxonomy vocabularies and terms.
 */
class TaxonomyHelper extends EntityHelper {

  /**
   * Load a single term by name.
   *
   * @param string $tid
   *   The term id.
   * @param string $vid
   *   The vocabulary that the term belongs to.
   *
   * @return \Drupal\taxonomy\Entity\TermInterface
   *   The taxonomy term object if found.
   */
  public static function getTermById($tid, $vid) {
    $terms_by_id = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($vid, $terms_by_id)) {
      $terms_by_id[$vid] = [];
      $properties = [
        'vid' => $vid,
      ];
      $_terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
      if (!empty($_terms)) {
        foreach ($_terms as $term) {
          $terms_by_id[$vid][$term->id()] = $term;
        }
      }
    }
    return !empty($terms_by_id[$vid]) && !empty($terms_by_id[$vid][$tid]) ? $terms_by_id[$vid][$tid] : NULL;
  }

  /**
   * Load a single term by name.
   *
   * @param string $name
   *   The name of the term to load.
   * @param string $vid
   *   The vocabulary to search for.
   *
   * @return \Drupal\taxonomy\Entity\TermInterface
   *   The taxonomy term object if found.
   */
  public static function getTermOriginalIdByName($name, $vid) {
    $term_original_ids_by_name = &drupal_static(__FUNCTION__, NULL);
    if ($term_original_ids_by_name === NULL) {
      $term_original_ids_by_name = [];
      $properties = [
        'vid' => $vid,
      ];
      $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
      if (!empty($terms)) {
        foreach ($terms as $term) {
          $term_original_ids_by_name[$term->bundle()][strtolower($term->getName())] = self::getOriginalIdFromTerm($term);
        }
      }
    }
    return !empty($term_original_ids_by_name[$vid]) && !empty($term_original_ids_by_name[$vid][strtolower($name)]) ? $term_original_ids_by_name[$vid][strtolower($name)] : NULL;
  }

  /**
   * Load a single term by name.
   *
   * This assumes that all terms inside a vocabulary have unique names.
   *
   * @param string $name
   *   The name of the term to load.
   * @param string $vid
   *   The vocabulary to search for.
   *
   * @return \Drupal\taxonomy\Entity\TermInterface
   *   The taxonomy term object if found.
   */
  public static function loadTermByName($name, $vid) {
    $terms_by_name = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($vid, $terms_by_name)) {
      $terms_by_name[$vid] = [];
      $properties = ['vid' => $vid];
      $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
      if (!empty($terms)) {
        foreach ($terms as $term) {
          $terms_by_name[$vid][strtolower($term->getName())] = $term;
        }
      }
    }
    return !empty($terms_by_name[$vid]) && !empty($terms_by_name[$vid][strtolower($name)]) ? $terms_by_name[$vid][strtolower($name)] : NULL;
  }

  /**
   * Load multiple terms by names.
   *
   * @param array $names
   *   An array of term names.
   * @param string $vid
   *   The vocabulary that the terms belong to.
   *
   * @return \Drupal\taxonomy\Entity\Term[]
   *   An array of term objects if found.
   */
  public static function loadMultipleTermsByName(array $names, $vid) {
    $properties = ['name' => $names];
    if (!empty($vid)) {
      $properties['vid'] = $vid;
    }
    $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
    return !empty($terms) ? $terms : NULL;
  }

  /**
   * Load multiple terms by vocabulary.
   *
   * @param string $vid
   *   The vocabulary that the terms belong to.
   *
   * @return \Drupal\taxonomy\Entity\Term[]
   *   An array of term objects if found.
   */
  public static function loadMultipleTermsByVocabulary($vid) {
    $properties = ['vid' => $vid];
    $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
    return !empty($terms) ? $terms : NULL;
  }

  /**
   * Retrieve the parent term for a given term name of a child term.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The term object of the parent.
   */
  public static function getParentTermFromChildTermId($child_term_id, $vid) {
    $parent_tree = self::getTermTreeKeyedByParentId($vid);
    foreach ($parent_tree as $parent_id => $childs) {
      $child_ids = array_map(function ($item) {
        return $item->tid;
      }, $childs);
      if (!in_array($child_term_id, $child_ids)) {
        continue;
      }
      if (!$parent_term = self::getTermById($parent_id, $vid)) {
        continue;
      }
      return $parent_term;
    }
    return FALSE;
  }

  /**
   * Retrieve the parent term for a given term name of a child term.
   */
  public static function getParentTermFromChildTermName($child_term_name, $vid) {
    $parent_tree = self::getTermTreeKeyedByParentName($vid);
    foreach ($parent_tree as $parent => $childs) {
      $child_names = array_map(function ($item) {
        return $item->name;
      }, $childs);
      if (!in_array($child_term_name, $child_names)) {
        continue;
      }
      if (!$parent_term = self::loadTermByName($parent, $vid)) {
        continue;
      }
      return $parent_term;
    }
    return FALSE;
  }

  /**
   * Get a tree representation of organization types.
   *
   * As this will be called multiple times, cached the results for this request.
   */
  public static function getTermTreeKeyedByParentId($vid) {
    $tree = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($vid, $tree)) {
      $tree[$vid] = [];
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $terms = $term_storage->loadTree($vid);
      $term_tids = array_map(function ($item) {
        return $item->tid;
      }, $terms);
      $term_entities = $term_storage->loadMultiple($term_tids);
      foreach ($terms as $term) {
        if (empty($term->parents)) {
          continue;
        }
        foreach ($term->parents as $parent_id) {
          if (empty($tree[$vid][$parent_id])) {
            $tree[$vid][$parent_id] = [];
          }
          $term->entity = $term_entities[$term->tid];
          $tree[$vid][$parent_id][] = $term;
        }
      }
    }
    return !empty($tree[$vid]) ? $tree[$vid] : [];
  }

  /**
   * Get a tree representation of organization types.
   *
   * As this will be called multiple times, cached the results for this request.
   */
  public static function getTermTreeKeyedByParentName($vid) {
    $tree = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($vid, $tree)) {
      $tree[$vid] = [];
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $terms = $term_storage->loadTree($vid);
      $terms_keyed = [];
      foreach ($terms as $term) {
        $terms_keyed[$term->tid] = $term;
      }
      $term_entities = $term_storage->loadMultiple(array_keys($terms_keyed));
      foreach ($terms as $term) {
        $parent_ids = array_filter($term->parents);
        if (empty($parent_ids)) {
          $tree[$vid][$term->name][$term->tid] = $term;
          continue;
        }

        foreach ($parent_ids as $parent_id) {
          $parent_name = $terms_keyed[$parent_id]->name;
          if (empty($tree[$vid][$parent_name])) {
            $tree[$vid][$parent_name] = [];
          }
          $term->entity = $term_entities[$term->tid];
          $tree[$vid][$parent_name][$term->tid] = $term;
        }
      }
    }
    return !empty($tree[$vid]) ? $tree[$vid] : [];
  }

  /**
   * Load a taxonomy term ID from it's original ID.
   *
   * @param int $original_id
   *   The original id of the term.
   * @param string $vid
   *   The vocabulary id.
   *
   * @return int
   *   A term id.
   */
  public static function getTermIdFromOriginalId($original_id, $vid) {
    $term_ids = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($vid, $term_ids)) {
      $term_ids[$vid] = [];
      $properties = [
        'vid' => $vid,
      ];
      $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
      if (!empty($terms)) {
        foreach ($terms as $term) {
          $term_ids[$vid][self::getOriginalIdFromTerm($term)] = $term->id();
        }
      }
    }
    return !empty($term_ids[$vid]) && !empty($term_ids[$vid][$original_id]) ? $term_ids[$vid][$original_id] : NULL;
  }

  /**
   * Load an original ID for a term.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   The term object.
   *
   * @return int
   *   The original id of the term.
   */
  public static function getOriginalIdFromTerm(Term $term) {
    return self::getOriginalIdFromEntity($term);
  }

  /**
   * Load multiple term IDs by the value of a field.
   *
   * @param string $field_name
   *   The field name.
   * @param string $value
   *   The value of the field to search for.
   * @param string $vid
   *   The vocabulary id.
   *
   * @return array
   *   An array of term ids if found.
   */
  public static function getTermIdsByFieldValue($field_name, $value, $vid) {
    $query = \Drupal::entityQuery('taxonomy_term');
    $result = $query
      ->condition($field_name, $value)
      ->condition('vid', $vid)
      ->execute();

    if (empty($result)) {
      return NULL;
    }
    return $result;
  }

}
