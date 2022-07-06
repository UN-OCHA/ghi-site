<?php

namespace Drupal\ghi_sections;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_common\Plugin\Condition\PageParameterCondition;
use Drupal\hpc_common\Traits\PageManagerTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Section context manager class.
 */
class SectionContextManager {

  use PageManagerTrait;
  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a section create form.
   */
  public function __construct(RequestStack $request_stack, RouteMatchInterface $route_match) {
    $this->requestStack = $request_stack;
    $this->routeMatch = $route_match;
  }

  /**
   * Get the current year context.
   */
  public function getYearContext() {
    $context = NULL;
    $request_attributes = $this->requestStack->getCurrentRequest()->attributes;
    if ($year = $request_attributes->get('year')) {
      $context = new Context($this->getContextDefinition(), $year);
    }
    elseif ($year = $this->getSelectionYearFromPageVariant()) {
      $context = new Context($this->getContextDefinition(), $year);
    }
    return $context;
  }

  /**
   * Get the current year context.
   */
  public function getYearContextFromEntity(EntityInterface $entity) {
    if (!$entity || !$entity instanceof NodeInterface) {
      return NULL;
    }
    $context = new Context($this->getContextDefinition(), $this->getYearFromNode($entity));

    // We are reusing cache contexts.
    $cacheContexts = $entity->getCacheContexts();
    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts($cacheContexts);
    $context->addCacheableDependency($cacheability);
    return $context;
  }

  /**
   * Get the context definition for this context.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinition
   *   A context definition object.
   */
  private function getContextDefinition() {
    return new ContextDefinition('integer', $this->t('Year'));
  }

  /**
   * Get a year value from the given node.
   *
   * This logic bubbles up an entity reference chain if available, and also
   * looks at associated base objects.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object to get a year for.
   *
   * @return int|null
   *   The year value if one can be found.
   */
  private function getYearFromNode(NodeInterface $node) {
    if ($node && $node->hasField('field_year') && $node->get('field_year')->value) {
      return $node->get('field_year')->value;
    }
    if ($node->hasField('field_entity_reference') && count($node->get('field_entity_reference')->referencedEntities()) == 1) {
      // The node is a subpage of a section and references a section node.
      $entities = $node->get('field_entity_reference')->referencedEntities();
      $base_entity = reset($entities);
      return $this->getYearFromNode($base_entity);
    }
    if ($node->hasField('field_base_object')) {
      // The page node is already a section node.
      /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
      $base_object = $node->get('field_base_object')->entity;
      return $base_object && $base_object->hasField('field_year') ? $base_object->get('field_year')->value : NULL;
    }
    return NULL;
  }

  /**
   * Try to get a selection criteria year from a page variant.
   *
   * @return int|null
   *   The year if one can be found.
   */
  private function getSelectionYearFromPageVariant() {
    $page_variant = $this->getCurrentPageVariant($this->requestStack->getCurrentRequest(), $this->routeMatch);
    if (!$page_variant) {
      return NULL;
    }
    $plugin_collection = $page_variant->getPluginCollections();
    $selection_criteria = $plugin_collection['selection_criteria'];
    foreach ($selection_criteria as $selection_criteria) {
      if (!$selection_criteria instanceof PageParameterCondition) {
        continue;
      }
      /** @var \Drupal\hpc_common\Plugin\Condition\PageParameterCondition $selection_criteria */
      $configuration = $selection_criteria->getConfiguration();
      if ($configuration['parameter'] == 'year') {
        return $configuration['value'];
      }
    }
    return NULL;
  }

}
