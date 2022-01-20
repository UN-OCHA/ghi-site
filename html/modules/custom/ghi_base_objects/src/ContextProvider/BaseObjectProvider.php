<?php

namespace Drupal\ghi_base_objects\ContextProvider;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\node\ContextProvider\NodeRouteContext;
use Drupal\node\NodeInterface;

/**
 * Provides a "base_object" context.
 */
class BaseObjectProvider implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The context repository.
   *
   * @var \Drupal\node\ContextProvider\NodeRouteContext
   */
  protected $nodeRouteContext;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * Constructs a new BaseObjectProvider.
   *
   * @param \Drupal\node\ContextProvider\NodeRouteContext $node_route_context
   *   The node route context.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   */
  public function __construct(NodeRouteContext $node_route_context, RouteMatchInterface $route_match, LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->nodeRouteContext = $node_route_context;
    $this->routeMatch = $route_match;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = [];

    $node = $this->getCurrentNode();

    // If we don't have a node, try to get one from the route match, maybe this
    // is a layout builder route?
    if (!$node && $section_storage = $this->routeMatch->getParameters()->get('section_storage')) {
      $tempstore_section_storage = $this->layoutTempstoreRepository->get($section_storage);
      try {
        $node = $tempstore_section_storage->getContextValue('entity');
      }
      catch (ContextException $e) {
        // Just catch it silently.
      }
    }

    if ($node && $node instanceof NodeInterface) {
      $context = EntityContext::fromEntity($this->getBaseObjectFromNode($node));

      // We are reusing cache contexts.
      $cacheContexts = $node->getCacheContexts();
      $cacheability = new CacheableMetadata();
      $cacheability->setCacheContexts($cacheContexts);
      $context->addCacheableDependency($cacheability);
      $result['base_object'] = $context;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $contexts = $this->getRuntimeContexts([]);
    if (empty($contexts) && $this->getCurrentNode()) {
      $contexts['base_object'] = new EntityContext(new EntityContextDefinition('base_object'), $this->t('Base object'));
    }
    return $contexts;
  }

  /**
   * Get the current node from the node route context service.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A node object if found.
   */
  private function getCurrentNode() {
    $runtime_contexts = $this->nodeRouteContext->getRuntimeContexts([]);

    /** @var \Drupal\Core\Plugin\Context\ContextInterface $nodeContext */
    $node_context = array_key_exists('node', $runtime_contexts) ? $runtime_contexts['node'] : NULL;

    /** @var \Drupal\node\NodeInterface $node */
    return $node_context && $node_context->hasContextValue() ? $node_context->getContextData()->getValue() : NULL;
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
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   The base object if one can be found.
   */
  private function getBaseObjectFromNode(NodeInterface $node) {
    if ($node->hasField('field_base_object')) {
      // The page node is already a section node.
      /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
      return $node->get('field_base_object')->entity;
    }
    if ($node->hasField('field_entity_reference') && count($node->get('field_entity_reference')->referencedEntities()) == 1) {
      // The node is a subpage of a section and references a section node.
      $entities = $node->get('field_entity_reference')->referencedEntities();
      $base_entity = reset($entities);
      return $this->getBaseObjectFromNode($base_entity);
    }
    return NULL;
  }

}
