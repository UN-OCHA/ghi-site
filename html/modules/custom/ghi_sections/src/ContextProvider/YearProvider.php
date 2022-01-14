<?php

namespace Drupal\ghi_sections\ContextProvider;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_common\Plugin\Condition\PageParameterCondition;
use Drupal\node\NodeInterface;
use Drupal\page_manager\Entity\PageVariant;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a "year" context.
 */
class YearProvider implements ContextProviderInterface {

  use StringTranslationTrait;

  const SERVICE_KEY = '@ghi_sections.year_context:year';

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new YearProvider.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = [];

    $request_attributes = $this->requestStack->getCurrentRequest()->attributes;

    /** @var \Drupal\node\NodeInterface $node */
    $node = $request_attributes->get('node');

    if ($node && $node instanceof NodeInterface) {
      $context = new Context($this->getContextDefinition(), $this->getYearFromNode($node));

      // We are reusing cache contexts.
      $cacheContexts = $node->getCacheContexts();
      $cacheability = new CacheableMetadata();
      $cacheability->setCacheContexts($cacheContexts);
      $context->addCacheableDependency($cacheability);

      $result[self::SERVICE_KEY] = $context;
      $result['year'] = $context;
    }
    elseif ($year = $request_attributes->get('year')) {
      $context = new Context($this->getContextDefinition(), $year);
      $result[self::SERVICE_KEY] = $context;
      $result['year'] = $context;
    }
    elseif ($year = $this->getSelectionYearFromPageVariant()) {
      $context = new Context($this->getContextDefinition(), $year);
      $result[self::SERVICE_KEY] = $context;
      $result['year'] = $context;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $contexts = $this->getRuntimeContexts([]);
    if (empty($contexts)) {
      $context = new Context($this->getContextDefinition());
      $contexts[self::SERVICE_KEY] = $context;
      $contexts['year'] = $context;
    }
    return $contexts;
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
      return $base_object->hasField('field_year') ? $base_object->get('field_year')->value : NULL;
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
    $page_variant = $this->getPageVariant();
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

  /**
   * Get a page variane from the current request.
   *
   * @return \Drupal\page_manager\Entity\PageVariant|null
   *   A page variant object if one can be found.
   */
  private function getPageVariant() {
    $request_attributes = $this->requestStack->getCurrentRequest()->attributes->all();
    $variant_id = NULL;
    if (array_key_exists('machine_name', $request_attributes) && array_key_exists('step', $request_attributes)) {
      // The step parameter looks like this and holds the variant id:
      // page_variant__homepage-layout_builder-0__layout_builder
      // The variant id in this case is "homepage-layout_builder-0".
      $variant_id = strpos($request_attributes['step'], '__') ? explode('__', $request_attributes['step'])[1] : NULL;
    }
    elseif (array_key_exists('section_storage_type', $request_attributes) && $request_attributes['section_storage_type'] == 'page_manager') {
      $variant_id = $request_attributes['section_storage'];
    }
    return $variant_id !== NULL ? PageVariant::load($variant_id) : NULL;
  }

}
